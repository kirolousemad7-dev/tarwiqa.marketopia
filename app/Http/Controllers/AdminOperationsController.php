<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Address;
use App\Models\AdminSetting;
use App\Models\ApprovalRequest;
use App\Models\AppShare;
use App\Models\Area;
use App\Models\Extra;
use App\Models\Governorate;
use App\Models\InquiryLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\OrderDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOperationsController extends Controller
{
    public function orders(string $status): View
    {
        abort_unless(in_array($status, ['under_review', 'waiting', 'accepted', 'completed', 'cancelled'], true), 404);

        $statusMap = [
            'under_review' => ['under_review'],
            'waiting' => ['waiting', 'waiting_list'],
            'accepted' => ['accepted', 'accepted_orders'],
            'completed' => ['completed', 'done', 'done_orders'],
            'cancelled' => ['cancelled', 'canceled'],
        ];

        return app(AdminParityController::class)->queue($status, $statusMap[$status]);
    }

    public function updateOrder(Request $request, Order $order, OrderDesk $desk): RedirectResponse
    {
        $data = $request->validate($desk->rules(false));
        $desk->update($order, $data, $request->user());

        return back()->with('status', 'Order updated.');
    }

    public function extras(): View
    {
        return view('extras.index', ['extras' => Extra::query()->latest()->paginate(20)->withQueryString(), 'metrics' => [
            'total_extras' => Extra::count(),
            'created_today' => Extra::whereDate('created_at', today())->count(),
            'top_reason' => Extra::query()->whereNotNull('reason')->select('reason')->selectRaw('COUNT(*) AS aggregate')->groupBy('reason')->orderByDesc('aggregate')->value('reason') ?: '—',
            'used_in_confirmations' => $this->extrasUsageRate(),
        ]]);
    }

    public function storeExtra(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', 'unique:extras,slug'],
            'reason' => ['nullable', 'string', 'max:255'],
            'short_note' => ['nullable', 'string', 'max:24'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        Extra::create($data + ['is_active' => true]);

        return back()->with('status', 'Extra created successfully.');
    }

    public function toggleExtra(Extra $extra): RedirectResponse
    {
        $extra->update(['is_active' => ! $extra->is_active]);

        return back()->with('status', 'Extra status updated.');
    }

    public function updateExtra(Request $request, Extra $extra): RedirectResponse
    {
        $extra->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', 'unique:extras,slug,'.$extra->id],
            'reason' => ['nullable', 'string', 'max:255'],
            'short_note' => ['nullable', 'string', 'max:24'],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]));

        return back()->with('status', 'Extra updated.');
    }

    public function exportExtras(Request $request): StreamedResponse
    {
        $extras = Extra::query()
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->string('q').'%'))
            ->orderBy('title')
            ->get();

        return response()->streamDownload(function () use ($extras): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Slug', 'Price', 'Reason', 'Status']);
            foreach ($extras as $extra) {
                fputcsv($out, spreadsheet_row([$extra->id, $extra->title, $extra->slug, $extra->base_price, $extra->reason, $extra->is_active ? 'active' : 'paused']));
            }
            fclose($out);
        }, 'extras.csv', ['Content-Type' => 'text/csv']);
    }

    private function extrasUsageRate(): string
    {
        $accepted = Order::whereIn('status', ['accepted', 'accepted_orders', 'completed', 'done', 'done_orders'])->count();
        if ($accepted === 0) {
            return '0%';
        }
        $used = DB::table('order_extras')->join('orders', 'orders.id', '=', 'order_extras.order_id')->whereIn('orders.status', ['accepted', 'accepted_orders', 'completed', 'done', 'done_orders'])->distinct('order_extras.order_id')->count('order_extras.order_id');

        return round(($used / $accepted) * 100).'%';
    }

    public function approvals(): View
    {
        return view('approvals.index', ['requests' => ApprovalRequest::query()->with(['requester:id,name', 'decider:id,name'])->latest()->paginate(20)->withQueryString(), 'metrics' => [
            'pending' => ApprovalRequest::where('status', 'pending')->count(),
            'urgent' => ApprovalRequest::where('status', 'pending')->where('created_at', '<', now()->subDay())->count(),
            'approved_today' => ApprovalRequest::where('status', 'approved')->whereDate('decided_at', today())->count(),
            'rejected_today' => ApprovalRequest::where('status', 'rejected')->whereDate('decided_at', today())->count(),
        ]]);
    }

    public function decideApproval(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'decision_note' => ['required', 'string', 'max:2000'],
        ]);

        $approvalRequest->update([
            'status' => $data['status'],
            'decision_note' => $data['decision_note'],
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back()->with('status', 'Approval decision recorded.');
    }

    public function coverage(): View
    {
        return view('coverage.index', [
            'customers' => User::query()->where('role', 'customer')->orderBy('name')->limit(100)->get(['id', 'name']),
            'governorates' => Governorate::query()->withCount('areas')->with('areas')->orderBy('name')->get(),
            'metrics' => [
                'total_governorates' => Governorate::count(),
                'total_locations' => Area::count(),
                'most_covered' => Governorate::withCount('areas')->orderByDesc('areas_count')->value('name') ?: '—',
                'sync_status' => 'Live',
            ],
        ]);
    }

    public function updateGovernorate(Request $request, Governorate $governorate): RedirectResponse
    {
        $governorate->update($request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:governorates,name,'.$governorate->id],
            'name_ar' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('status', 'Governorate updated.');
    }

    public function toggleGovernorate(Governorate $governorate): RedirectResponse
    {
        $governorate->update(['is_active' => ! $governorate->is_active]);

        return back()->with('status', 'Governorate status updated.');
    }

    public function storeGovernorate(Request $request): RedirectResponse
    {
        Governorate::create($request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:governorates,name'],
            'name_ar' => ['nullable', 'string', 'max:255'],
        ]) + ['is_active' => true]);

        return back()->with('status', 'Governorate created successfully.');
    }

    public function storeArea(Request $request, Governorate $governorate): RedirectResponse
    {
        $governorate->areas()->create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
        ]) + ['is_active' => true]);

        return back()->with('status', 'Area created successfully.');
    }

    public function toggleArea(Area $area): RedirectResponse
    {
        $area->update(['is_active' => ! $area->is_active]);

        return back()->with('status', 'Location status updated.');
    }

    public function updateArea(Request $request, Area $area): RedirectResponse
    {
        $area->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
        ]));

        return back()->with('status', 'Location updated.');
    }

    public function destroyArea(Area $area): RedirectResponse
    {
        if (Address::query()->where('area_id', $area->id)->exists() || DB::table('partner_areas')->where('area_id', $area->id)->exists()) {
            return back()->withErrors(['area' => 'This location is referenced by customer addresses or partners and was not deleted.']);
        }
        $area->delete();

        return back()->with('status', 'Location deleted.');
    }

    public function syncCoverage(): RedirectResponse
    {
        AdminSetting::putValue('coverage.snapshot', [
            'synced_at' => now()->toIso8601String(),
            'governorate_ids' => Governorate::query()->where('is_active', true)->pluck('id')->all(),
            'area_ids' => Area::query()->where('is_active', true)->pluck('id')->all(),
        ]);

        return back()->with('status', 'App coverage snapshot updated from the current governorates and areas.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        Address::create($request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'street' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
        ]) + ['label' => 'Customer address']);

        return back()->with('status', 'Customer address saved.');
    }

    public function ads(): View
    {
        return view('ads.index', ['ads' => Ad::query()->latest()->paginate(20)->withQueryString(), 'metrics' => [
            'active_ads' => Ad::where('status', 'active')->count(),
            'scheduled_ads' => Ad::where('starts_at', '>', now())->count(),
            'top_slot' => Ad::orderByDesc('clicks')->value('slot_name') ?: '—',
            'clicks' => (int) Ad::sum('clicks'),
        ]]);
    }

    public function storeAd(Request $request): RedirectResponse
    {
        Ad::create($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slot_name' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:2048'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'starts_at' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]) + ['status' => 'active']);
        Cache::forget('api:banners:active');

        return back()->with('status', 'Ad created successfully.');
    }

    public function updateAd(Request $request, Ad $ad): RedirectResponse
    {
        $ad->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slot_name' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:2048'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'starts_at' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]));
        Cache::forget('api:banners:active');

        return back()->with('status', 'Ad updated.');
    }

    public function adsHistory(): View
    {
        $ads = Ad::query()->latest()->get();

        return view('ads.history', ['ads' => $ads]);
    }

    public function trackClick(Ad $ad): RedirectResponse
    {
        $ad->increment('clicks');
        $target = $ad->destination;
        abort_unless(is_string($target) && (str_starts_with($target, 'https://') || str_starts_with($target, 'http://')), 404);

        return redirect()->away($target);
    }

    public function exportShares(): StreamedResponse
    {
        $shares = AppShare::query()->with('user:id,name')->latest('shared_at')->get();

        return response()->streamDownload(function () use ($shares): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Customer', 'Share date', 'Platform', 'Share count', 'Conversions']);
            foreach ($shares as $share) {
                fputcsv($out, spreadsheet_row([$share->user?->name, $share->shared_at, $share->platform, 1, $share->conversion_count]));
            }
            fclose($out);
        }, 'shares.csv', ['Content-Type' => 'text/csv']);
    }

    public function toggleAd(Ad $ad): RedirectResponse
    {
        $ad->update(['status' => $ad->status === 'active' ? 'pause' : 'active']);
        Cache::forget('api:banners:active');

        return back()->with('status', 'Ad status updated.');
    }

    public function shares(): View
    {
        $topSharer = AppShare::query()->select('user_id')->selectRaw('COUNT(*) AS aggregate')->groupBy('user_id')->orderByDesc('aggregate')->with('user:id,name')->first();

        return view('shares.index', ['shares' => AppShare::query()->with('user:id,name,email')->latest('shared_at')->paginate(20)->withQueryString(), 'metrics' => [
            'total_shares' => AppShare::count(),
            'customers_shared' => AppShare::distinct('user_id')->count('user_id'),
            'top_sharer' => $topSharer?->user?->name ?: '—',
            'latest_share' => AppShare::max('shared_at') ?: '—',
        ]]);
    }

    public function inquiries(Request $request): View
    {
        $logs = app(AdminParityController::class)->inquiryQuery($request)->paginate(20)->withQueryString();

        $funnelBase = app(AdminParityController::class)->inquiryQuery($request);
        $total = (clone $funnelBase)->count();
        $withOffer = (clone $funnelBase)->whereNotNull('offer_name')->count();
        $converted = (clone $funnelBase)->where('session_result', 'converted')->count();

        return view('inquiries.index', [
            'logs' => $logs,
            'funnel' => [
                'sessions' => $total,
                'with_offer' => $withOffer,
                'converted' => $converted,
                'offer_rate' => $total > 0 ? round(($withOffer / $total) * 100, 1) : 0,
                'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
                'areas' => (clone $funnelBase)->whereNotNull('governorate_name')->select('governorate_name')->selectRaw('COUNT(*) as aggregate')->groupBy('governorate_name')->orderByDesc('aggregate')->limit(5)->get(),
            ],
            'metrics' => [
                'total' => InquiryLog::query()->count(),
                'open' => InquiryLog::query()->whereNull('resolved_at')->count(),
            ],
        ]);
    }

    private function queueMetrics(array $statuses): array
    {
        $orders = Order::whereIn('status', $statuses);

        return [
            'orders_in_queue' => (clone $orders)->count(),
            'highest_price' => (float) ((clone $orders)->max('total') ?: 0),
            'today_requests' => (clone $orders)->whereDate('created_at', today())->count(),
            'earliest_arrival' => (string) ((clone $orders)->whereNotNull('arrival_time')->orderBy('arrival_time')->value('arrival_time') ?: '—'),
        ];
    }
}
