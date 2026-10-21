<?php

namespace App\Http\Controllers;

use App\Contracts\Services\UserManagementInterface;
use App\Http\Requests\Users\SaveUserRequest;
use App\Models\Area;
use App\Models\City;
use App\Models\Conversation;
use App\Models\Governorate;
use App\Models\Maid;
use App\Models\Order;
use App\Models\ServiceCategory;
use App\Models\AuditLog;
use App\Models\Message;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Support\Str;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class UserViewController extends Controller
{
    public function __construct(private readonly UserManagementInterface $users) {}

    public function bulkStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:deactivate,activate,restore,restrict,ban'],
            'ban_type' => ['nullable', 'required_if:action,ban', 'in:temporary,permanent'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $roles = in_array($data['action'], ['restrict', 'ban'], true)
            ? ['customer']
            : ['customer', User::ROLE_PARTNER, User::ROLE_SUPPORTER];
        $status = match ($data['action']) {
            'deactivate' => 'inactive',
            'restrict' => 'restricted',
            'ban' => 'suspended',
            default => 'active',
        };
        $banType = $data['action'] === 'ban' ? $data['ban_type'] : null;
        $targets = User::query()
            ->whereIn('id', $data['ids'])
            ->whereIn('role', $roles)
            ->where('role', '!=', User::ROLE_SUPER_ADMIN)
            ->whereKeyNot($request->user()->id)
            ->get();
        foreach ($targets as $target) {
            $before = ['status' => $target->status, 'ban_type' => $target->ban_type];
            $target->forceFill(['status' => $status, 'ban_type' => $banType])->save();
            AuditLog::record($request->user(), $target, 'account.'.$data['action'], 'Account status changed to '.$status.'.', $before, ['status' => $status, 'ban_type' => $banType]);
        }
        $updated = $targets->count();
        $label = match ($data['action']) {
            'deactivate' => 'deactivated',
            'restrict' => 'restricted',
            'ban' => $data['ban_type'] === 'permanent' ? 'permanently banned' : 'temporarily banned',
            default => 'activated',
        };

        return back()->with('status', $updated.' account'.($updated === 1 ? '' : 's').' '.$label.'. Orders and history were preserved.');
    }

    public function index(?string $role = 'customer'): View
    {
        $users = User::query()->where('role', $role);

        $metrics = [
            'total_users' => (clone $users)->count(),
            'active_today' => (clone $users)->whereDate('last_active_at', today())->count(),
            'active_users' => (clone $users)->where('status', 'active')->count(),
            'inactive_users' => (clone $users)->where('status', 'inactive')->count(),
            'restricted_users' => (clone $users)->where('status', 'restricted')->count(),
            'suspended_users' => (clone $users)->where('status', 'suspended')->count(),
        ];

        if ($role == 'customer') {
            $metrics['total_wallet'] = Wallet::sum('balance');
        }
        if ($role === 'partner') {
            $metrics['managed_maids'] = Maid::whereIn('partner_id', (clone $users)->select('id'))->count();
            $metrics['completed_orders'] = Order::whereIn('partner_id', (clone $users)->select('id'))->whereIn('status', ['completed', 'done', 'done_orders'])->count();
        }
        if ($role === 'supporter') {
            $metrics['covered_governorates'] = DB::table('user_governorates')->whereIn('user_id', (clone $users)->select('id'))->distinct('governorate_id')->count('governorate_id');
            $metrics['scoped_orders'] = Order::count();
        }

        if ($role === 'partner') {
            $partners = User::query()->where('role', 'partner')
                ->with(['governorate:id,name', 'partnerAreas:id,name'])
                ->withCount(['maids', 'assignedOrders as completed_orders_count' => fn ($orders) => $orders->whereIn('status', ['completed', 'done', 'done_orders'])])
                ->when(request()->filled('status'), fn ($query) => $query->where('status', request('status')))
                ->when(request()->filled('governorate_id'), fn ($query) => $query->where('governorate_id', request()->integer('governorate_id')))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString();
            $partnerIds = $partners->getCollection()->pluck('id');
            $threads = collect();
            if ($partnerIds->isNotEmpty()) {
                Conversation::query()
                    ->with(['messages.sender:id,name', 'participants'])
                    ->whereHas('participants', fn ($query) => $query->whereIn('users.id', $partnerIds))
                    ->latest('last_message_at')
                    ->get()
                    ->each(function (Conversation $conversation) use ($partnerIds, $threads): void {
                        $partnerId = $conversation->participants->first(fn (User $person) => $partnerIds->contains($person->id))?->id;
                        if ($partnerId && ! $threads->has($partnerId)) {
                            $this->labelPartnerReads($conversation, (int) request()->user()->id, (int) $partnerId);
                            $threads->put($partnerId, $conversation);
                        }
                    });
            }
            $chatId = request()->integer('chat');
            if ($chatId && $threads->has($chatId)) {
                $threads[$chatId]->participants()->updateExistingPivot(request()->user()->id, ['last_read_at' => now()]);
                $threads[$chatId]->load('participants');
                $this->labelPartnerReads($threads[$chatId], (int) request()->user()->id, $chatId);
            }

            return view('users.partners', ['partners' => $partners, 'threads' => $threads, 'role' => $role, ...$metrics]);
        }

        return view('users.index', ['role' => $role, ...$metrics]);
    }

    public function data(string $role): JsonResponse
    {
        $query = $this->users->query()->where('role', $role)->with(['governorate:id,name', 'governorates:id,name', 'partnerAreas:id,name'])
            ->when(request()->filled('status'), fn ($query) => $query->where('status', request('status')))
            ->when(request()->filled('governorate_id'), fn ($query) => $query->where(function ($inner): void {
                $inner->where('governorate_id', request()->integer('governorate_id'))
                    ->orWhereHas('governorates', fn ($governorates) => $governorates->where('governorates.id', request()->integer('governorate_id')));
            }));
        if ($role === 'partner') {
            $query->withCount([
                'maids',
                'assignedOrders as completed_orders_count' => fn ($orders) => $orders->whereIn('status', ['completed', 'done', 'done_orders']),
            ]);
        }

        $partnersByGovernorate = collect();
        $ordersByGovernorate = collect();
        if ($role === 'supporter') {
            $partnersByGovernorate = User::query()
                ->where('role', 'partner')
                ->whereNotNull('governorate_id')
                ->select('governorate_id')
                ->selectRaw('count(*) as aggregate')
                ->groupBy('governorate_id')
                ->pluck('aggregate', 'governorate_id');
            $ordersByGovernorate = Order::query()
                ->join('users as partners', 'partners.id', '=', 'orders.partner_id')
                ->where('partners.role', 'partner')
                ->whereNull('partners.deleted_at')
                ->whereNotNull('partners.governorate_id')
                ->select('partners.governorate_id')
                ->selectRaw('count(*) as aggregate')
                ->groupBy('partners.governorate_id')
                ->pluck('aggregate', 'governorate_id');
        }

        $datatables = DataTables::eloquent($query)
            ->addColumn('city_name', fn (User $user): string => $user->governorate?->name ?? $user->city?->name ?? 'Unassigned')
            ->addColumn('governorate_name', fn (User $user): string => $user->governorate?->name ?? 'Unassigned')
            ->addColumn('edit_url', fn (User $user): string => route('admin.users.edit', $user))
            ->addColumn('view_url', fn (User $user): string => route('admin.users.edit', $user))
            ->addColumn('message_url', fn (User $user): string => route('admin.messages.create', ['user' => $user->id]));

        if ($role == 'partner') {
            $datatables->addColumn('location_name', fn (User $user): string => $user->partnerAreas->pluck('name')->join(', ') ?: 'Unassigned')
                ->addColumn('maids_count', fn (User $user): int => (int) $user->maids_count)
                ->addColumn('completed_orders', fn (User $user): int => (int) $user->completed_orders_count);
        }
        if ($role == 'supporter') {
            $datatables->addColumn('access_role', fn (User $user): string => str($user->settings['access_role'] ?? 'viewer')->title()->toString())
                ->addColumn('assign_governorates', fn (User $user): string => $user->governorates->pluck('name')->join(', ') ?: 'None')
                ->addColumn('orders_access', fn (User $user): string => 'Allowed')
                ->addColumn('partners_access', fn (User $user): string => 'Allowed')
                ->addColumn('scoped_orders', function (User $user) use ($ordersByGovernorate): int {
                    return (int) $user->governorates->pluck('id')->unique()->sum(
                        fn ($id): int => (int) ($ordersByGovernorate[$id] ?? 0),
                    );
                })
                ->addColumn('scoped_partners', function (User $user) use ($partnersByGovernorate): int {
                    return (int) $user->governorates->pluck('id')->unique()->sum(
                        fn ($id): int => (int) ($partnersByGovernorate[$id] ?? 0),
                    );
                });
        }

        return $datatables->toJson();
    }

    public function create(): View
    {
        return view('users.create', $this->formOptions());
    }

    public function store(SaveUserRequest $request, WalletService $wallets): RedirectResponse
    {
        $data = $request->validated();
        if ($request->boolean('is_draft')) {
            $data['status'] = 'inactive';
            $data['is_draft'] = true;
        }
        $user = $this->users->create($data);
        AuditLog::record($request->user(), $user, $request->boolean('is_draft') ? 'account.draft_saved' : 'account.created', $request->boolean('is_draft') ? 'Draft account saved.' : 'Account created.', [], ['status' => $user->status, 'role' => $user->role]);
        $this->creditOpeningBalance($user, (float) ($data['wallet_opening'] ?? 0), $request->user(), $wallets);

        return redirect()->route('admin.users.index', ['role' => $data['role']])->with('status', $request->boolean('is_draft') ? 'Draft saved. This account stays inactive until it is finalized.' : 'User created successfully.');
    }

    private function labelPartnerReads(Conversation $conversation, int $adminId, int $partnerId): void
    {
        $conversation->messages->each(function ($message) use ($conversation, $adminId, $partnerId): void {
            $reader = $conversation->participants->firstWhere('id', $message->sender_id === $adminId ? $partnerId : $adminId);
            $readAt = $reader && $reader->pivot && $reader->pivot->last_read_at
                ? \Illuminate\Support\Carbon::parse($reader->pivot->last_read_at)
                : null;
            $message->setAttribute('read_label', $readAt && $message->created_at && $readAt->greaterThanOrEqualTo($message->created_at) ? 'Read' : 'Unread');
        });
    }

    public function temporaryPassword(User $user): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_SUPPORTER, 404);
        $temporary = Str::password(16, symbols: false);
        $user->forceFill(['password' => $temporary])->save();

        return back()->with('temporary_password', $temporary)->with('temporary_password_user', $user->id);
    }

    public function partnerChat(Request $request, User $user, MessagingService $messaging): RedirectResponse
    {
        abort_unless($user->role === User::ROLE_PARTNER, 404);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ]);
        if (! empty($data['conversation_id'])) {
            $conversation = Conversation::query()
                ->whereKey($data['conversation_id'])
                ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
                ->firstOrFail();
            $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $data['body'],
                'type' => 'text',
            ]);
            $conversation->update(['last_message_at' => now(), 'box' => 'inbox']);
        } else {
            $conversation = $messaging->send($request->user(), [
                'recipient_id' => $user->id,
                'channel' => 'in_app',
                'subject' => 'Partner chat',
                'body' => $data['body'],
            ]);
            $conversation->update(['box' => 'inbox']);
        }
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        return redirect()->route('admin.users.index', ['role' => 'partner', 'chat' => $user->id])->with('status', 'Message sent.');
    }

    public function exportPartners(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $partners = User::query()->where('role', 'partner')
            ->when(request()->filled('status'), fn ($query) => $query->where('status', request('status')))
            ->when(request()->filled('governorate_id'), fn ($query) => $query->where('governorate_id', request()->integer('governorate_id')))
            ->with('governorate:id,name')
            ->withCount(['maids', 'assignedOrders as completed_orders_count' => fn ($orders) => $orders->whereIn('status', ['completed', 'done', 'done_orders'])])
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($partners): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Partner', 'Status', 'Governorate', 'Managed maids', 'Completed orders']);
            foreach ($partners as $partner) {
                fputcsv($out, spreadsheet_row([$partner->name, $partner->status, $partner->governorate?->name, $partner->maids_count, $partner->completed_orders_count]));
            }
            fclose($out);
        }, 'partners.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportLogs(User $user): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', $user->id)
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($logs): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Event', 'Description', 'Actor', 'IP address']);
            foreach ($logs as $log) {
                fputcsv($out, spreadsheet_row([$log->created_at, $log->event, $log->description, $log->actor?->name, $log->ip_address]));
            }
            fclose($out);
        }, 'user-'.$user->id.'-logs.csv', ['Content-Type' => 'text/csv']);
    }

    public function edit(User $user): View
    {
        $user = $user->load(['orders', 'addresses', 'wallet.transactions']);
        $activityLogs = AuditLog::query()
            ->with('actor:id,name')
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();
        $messageHistory = Message::query()
            ->with('sender:id,name')
            ->whereHas('conversation.participants', fn ($query) => $query->whereKey($user->id))
            ->latest()
            ->limit(20)
            ->get();

        return view('users.edit', ['user' => $user, 'activityLogs' => $activityLogs, 'messageHistory' => $messageHistory] + $this->formOptions());
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        if (($data['status'] ?? null) !== 'suspended') {
            $data['ban_type'] = null;
        }
        $before = ['status' => $user->status, 'ban_type' => $user->ban_type, 'role' => $user->role];
        $this->users->update($user, $data);
        $user->refresh();
        $this->creditOpeningBalance($user, (float) ($data['wallet_opening'] ?? 0), $request->user(), app(WalletService::class));
        AuditLog::record($request->user(), $user, 'account.updated', 'Account profile updated.', $before, ['status' => $user->status, 'ban_type' => $user->ban_type, 'role' => $user->role]);

        return redirect()->route('admin.users.index', ['role' => $request->validated('role')])->with('status', 'User updated successfully.');
    }

    private function creditOpeningBalance(User $user, float $opening, User $actor, WalletService $wallets): void
    {
        if ($user->status !== 'active' || $opening <= 0) {
            return;
        }

        $wallets->adjust(
            $user,
            'deposit',
            $opening,
            'Opening wallet balance from Add User',
            $actor,
            sprintf('00000000-0000-4000-a000-%012d', $user->id),
        );
    }

    private function cities()
    {
        return City::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function formOptions(): array
    {
        return [
            'cities' => $this->cities(),
            'governorates' => Governorate::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(['id', 'governorate_id', 'name']),
            'serviceCategories' => ServiceCategory::where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'maids' => Maid::where('status', 'active')->where('is_draft', false)->orderBy('name')->get(['id', 'name', 'phone']),
        ];
    }
}
