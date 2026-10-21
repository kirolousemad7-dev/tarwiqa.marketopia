<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\AdminSetting;
use App\Models\Conversation;
use App\Models\Extra;
use App\Models\Governorate;
use App\Models\InquiryLog;
use App\Models\Maid;
use App\Models\Message;
use App\Models\Order;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Admin\BookingSchedule;
use App\Services\Admin\OrderDesk;
use App\Services\Admin\OutboundChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminParityController extends Controller
{
    public function scheduled(BookingSchedule $schedule): View
    {
        $state = $schedule->state();
        $month = request()->date('month')?->startOfMonth() ?? now()->startOfMonth();
        $counts = Order::query()
            ->whereBetween('service_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->selectRaw('service_date, COUNT(*) as aggregate')
            ->groupBy('service_date')
            ->pluck('aggregate', 'service_date');
        $orders = Order::query()
            ->with(['customer:id,name', 'partner:id,name', 'package.services.category.type'])
            ->whereBetween('service_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('service_date')
            ->get();

        return view('orders.scheduled', [
            'schedule' => $state,
            'orders' => $orders,
            'counts' => $counts,
            'month' => $month,
            'selected' => request('date'),
        ]);
    }

    public function saveSchedule(Request $request, BookingSchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'today_open' => ['nullable', 'boolean'],
            'tomorrow_open' => ['nullable', 'boolean'],
            'open_hours' => ['nullable', 'array'],
            'open_hours.*' => ['integer', 'between:8,20'],
            'closed_dates' => ['nullable', 'array'],
            'closed_dates.*' => ['date'],
            'closed_until' => ['nullable', 'date'],
        ]);
        $data['today_open'] = $request->boolean('today_open');
        $data['tomorrow_open'] = $request->boolean('tomorrow_open');
        $schedule->save($data);

        return back()->with('status', 'Schedule saved.');
    }

    public function storeOrder(Request $request, OrderDesk $desk): RedirectResponse
    {
        $data = $request->validate($desk->rules(true));
        $order = $desk->create($data, $request->user());

        return back()->with('status', 'Order #'.$order->id.' created.');
    }

    public function destroyOrder(Order $order): RedirectResponse
    {
        $order->delete();

        return back()->with('status', 'Order moved to trash.');
    }

    public function customerLookup(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');
        $customers = User::query()
            ->where('role', 'customer')
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('phone', 'like', '%'.$term.'%')
                        ->orWhere('id', $term)
                        ->orWhere('name', 'like', '%'.$term.'%');
                });
            })
            ->limit(8)
            ->get(['id', 'name', 'phone']);

        return response()->json($customers->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'addresses' => Address::query()->where('user_id', $user->id)->get(['id', 'street', 'label']),
        ]));
    }

    public function notify(Request $request, OutboundChannel $channels): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:push,email,whatsapp'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $users = User::query()->whereIn('id', $data['ids'])->where('role', 'customer')->get();
        $errors = [];
        foreach ($users as $user) {
            $dispatch = $channels->send($data['channel'], $user, $data['body'], $data['subject'] ?? null, $request->user());
            if ($dispatch->error) {
                $errors[] = $dispatch->error;
            }
        }

        if ($errors !== []) {
            return back()->withErrors(['channel' => $errors[0]]);
        }

        return back()->with('status', 'The mail transport accepted the message for '.$users->count().' customer'.($users->count() === 1 ? '' : 's').'. Inbox delivery was not confirmed.');
    }

    public function inboxAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:read,junk,star,inbox,sent'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $conversations = Conversation::query()->whereIn('id', $data['ids']);
        match ($data['action']) {
            'junk' => $conversations->update(['box' => 'junk']),
            'star' => $conversations->update(['is_starred' => true]),
            'inbox' => $conversations->update(['box' => 'inbox', 'is_starred' => false]),
            'sent' => $conversations->update(['box' => 'sent']),
            'read' => DB::table('conversation_participants')->whereIn('conversation_id', $data['ids'])->update(['last_read_at' => now()]),
        };

        return back()->with('status', 'Inbox updated.');
    }

    public function saveInboxRules(Request $request): RedirectResponse
    {
        $data = $request->validate(['routing_note' => ['nullable', 'string', 'max:2000']]);
        AdminSetting::putValue('inbox.rules', $data);

        return back()->with('status', 'Message rules saved.');
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $conversation->update(['last_message_at' => now(), 'box' => 'sent']);

        return back()->with('status', 'Reply saved.');
    }

    public function savePackages(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'rows.*.package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'rows.*.price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.is_active' => ['nullable', 'boolean'],
        ]);
        DB::transaction(function () use ($data, $request): void {
            foreach ($data['rows'] as $row) {
                if (empty($row['package_id'])) {
                    continue;
                }
                ServicePackage::query()->whereKey($row['package_id'])->update([
                    'governorate_id' => $row['governorate_id'],
                    'price' => $row['price'] ?? 0,
                    'is_active' => $request->boolean('rows.'.$row['governorate_id'].'.is_active') || ! empty($row['is_active']),
                ]);
            }
            AdminSetting::putValue('custom_packages.builder', ['rows' => $data['rows'], 'saved_at' => now()->toIso8601String()]);
        });

        return back()->with('status', 'Custom package setup saved.');
    }

    public function exportPackages(): StreamedResponse
    {
        $rows = AdminSetting::getValue('custom_packages.builder')['rows'] ?? [];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['governorate_id', 'package_id', 'price', 'is_active']);
            foreach ($rows as $row) {
                fputcsv($out, spreadsheet_row([$row['governorate_id'] ?? '', $row['package_id'] ?? '', $row['price'] ?? '', $row['is_active'] ?? '']));
            }
            fclose($out);
        }, 'custom-packages.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportInquiries(Request $request): StreamedResponse
    {
        $logs = $this->inquiryQuery($request)->limit(1000)->get();

        return response()->streamDownload(function () use ($logs): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['customer', 'user_id', 'date', 'time', 'governorate', 'widget', 'category', 'offer', 'result']);
            foreach ($logs as $log) {
                fputcsv($out, spreadsheet_row([
                    $log->user?->name,
                    $log->user_id,
                    $log->created_at?->format('d M Y'),
                    $log->created_at?->format('H:i'),
                    $log->governorate_name,
                    $log->widget_name,
                    $log->category_name,
                    $log->offer_name,
                    $log->session_result ?: $log->status,
                ]));
            }
            fclose($out);
        }, 'inquiry-sessions.csv', ['Content-Type' => 'text/csv']);
    }

    public function inquiryQuery(Request $request)
    {
        return InquiryLog::query()
            ->with('user:id,name')
            ->when($request->filled('customer'), fn ($query) => $query->whereHas('user', fn ($users) => $users->where('name', 'like', '%'.$request->string('customer').'%')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->latest();
    }

    public function builderContext(): array
    {
        return [
            'governorates' => Governorate::query()->orderBy('name')->limit(6)->get(),
            'packages' => ServicePackage::query()->orderBy('title')->get(['id', 'title', 'price', 'governorate_id', 'is_active']),
            'widgets' => ServiceType::query()->orderBy('title')->get(['id', 'title']),
            'categories' => ServiceCategory::query()->orderBy('title')->get(['id', 'title', 'type_id']),
            'saved' => AdminSetting::getValue('custom_packages.builder'),
        ];
    }

    public function orderFormContext(): array
    {
        return [
            'partners' => User::query()->where('role', 'partner')->orderBy('name')->get(['id', 'name']),
            'maids' => Maid::query()->where('status', 'active')->where('is_draft', false)->orderBy('name')->get(['id', 'name']),
            'extras' => Extra::query()->where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'packages' => ServicePackage::query()->orderBy('title')->get(['id', 'title', 'price']),
        ];
    }

    public function queue(string $status, array $statuses, array $metrics = []): View
    {
        $orders = $this->filteredOrders($statuses)->with([
            'customer:id,name,phone',
            'partner:id,name',
            'address.city:id,name',
            'address.governorate:id,name',
            'package.services.category.type',
            'maids:id,name',
            'extras',
        ])->paginate(20)->withQueryString();

        return view('orders.queue', [
            'orders' => $orders,
            'status' => $status,
            'metrics' => $metrics,
            'partners' => User::query()->where('role', 'partner')->orderBy('name')->get(['id', 'name']),
            'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name']),
            'filters' => request()->only(['q', 'date', 'sort', 'partner_id', 'governorate_id']),
        ]);
    }

    public function exportQueue(string $status): StreamedResponse
    {
        $map = [
            'under_review' => ['under_review'],
            'waiting' => ['waiting', 'waiting_list'],
            'accepted' => ['accepted', 'accepted_orders'],
            'completed' => ['completed', 'done', 'done_orders'],
            'cancelled' => ['cancelled', 'canceled'],
        ];
        abort_unless(isset($map[$status]), 404);
        $rows = $this->filteredOrders($map[$status])->with(['customer:id,name,phone', 'partner:id,name', 'address.governorate:id,name', 'package.services.category.type'])->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Customer', 'Phone', 'Governorate', 'Widget', 'Category', 'Partner', 'Package', 'Payment', 'Service date', 'Arrival', 'Total', 'Discount', 'Final', 'Status']);
            foreach ($rows as $order) {
                $order->loadMissing('package.services.category.type');
                $categories = $order->package?->services->map(fn ($service) => $service->category?->title)->filter()->unique()->join(', ');
                $widgets = $order->package?->services->map(fn ($service) => $service->category?->type?->title)->filter()->unique()->join(', ');
                fputcsv($out, spreadsheet_row([$order->id, $order->customer?->name, $order->customer?->phone, $order->address?->governorate?->name, $widgets, $categories, $order->partner?->name, $order->package?->title, $order->payment_method, $order->service_date, $order->arrival_time, $order->subtotal, $order->discount_total, $order->total, $order->status]));
            }
            fclose($out);
        }, $status.'-orders.csv', ['Content-Type' => 'text/csv']);
    }

    private function filteredOrders(array $statuses)
    {
        $request = request();
        $query = Order::query()->whereIn('status', $statuses)
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('id', 'like', $term)->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $term)->orWhere('phone', 'like', $term));
                });
            })
            ->when($request->filled('date'), fn ($query) => $query->whereDate('service_date', $request->date('date')))
            ->when($request->filled('partner_id'), fn ($query) => $query->where('partner_id', $request->integer('partner_id')))
            ->when($request->filled('governorate_id'), fn ($query) => $query->whereHas('address', fn ($address) => $address->where('governorate_id', $request->integer('governorate_id'))));

        return match ($request->string('sort')->toString()) {
            'price_desc' => $query->orderByDesc('total'),
            'price_asc' => $query->orderBy('total'),
            'date_asc' => $query->orderBy('service_date'),
            'priority' => $query->orderBy('id'),
            default => $query->latest('service_date'),
        };
    }
}
