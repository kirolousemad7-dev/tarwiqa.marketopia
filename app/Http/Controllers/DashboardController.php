<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Maid;
use App\Models\Order;
use App\Models\OrderExtra;
use App\Models\Product;
use App\Models\ServicePackage;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const OVERVIEW_CACHE_KEY = 'admin.dashboard.overview.v3';

    private const OVERVIEW_CACHE_SECONDS = 30;

    public function redirect(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->dashboardRoute());
    }

    public function index(): View
    {
        if (request()->boolean('refresh')) {
            Cache::forget(self::OVERVIEW_CACHE_KEY);
        }

        $overview = Cache::remember(self::OVERVIEW_CACHE_KEY, self::OVERVIEW_CACHE_SECONDS, function (): array {
            $customerRows = User::query()
                ->where('role', 'customer')
                ->select(['status', 'gender', 'platform'])
                ->selectRaw('count(*) as aggregate')
                ->groupBy('status', 'gender', 'platform')
                ->get();
            $customerCount = function (callable $filter) use ($customerRows): int {
                return (int) $customerRows->filter($filter)->sum('aggregate');
            };
            $activePartners = User::query()->where('role', User::ROLE_PARTNER)->where('status', 'active')->count();
            $orderCounts = Order::query()
                ->select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($count): int => (int) $count)
                ->all();
            $orderCount = fn (string ...$statuses): int => (int) array_sum(array_map(
                fn (string $status): int => (int) ($orderCounts[$status] ?? 0),
                $statuses,
            ));
            $completedMoney = Order::query()
                ->where('status', 'completed')
                ->selectRaw('coalesce(sum(total), 0) as total, coalesce(sum(subtotal), 0) as subtotal, coalesce(sum(discount_total), 0) as discount_total')
                ->first();
            $maidTotals = Maid::query()
                ->selectRaw('count(*) as total, coalesce(sum(case when status = ? then 1 else 0 end), 0) as active, coalesce(sum(salary), 0) as salary', ['active'])
                ->first();
            $lastMonth = [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()];
            $thisMonth = [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];

            return [
                'metrics' => [
                    'employees' => [
                        'active' => $activePartners,
                    ],
                    'users' => [
                        'total' => $customerCount(fn ($row): bool => true),
                        'active' => $customerCount(fn ($row): bool => $row->status === 'active'),
                        'inactive' => $customerCount(fn ($row): bool => $row->status === 'inactive'),
                        'restricted' => $customerCount(fn ($row): bool => $row->status === 'restricted'),
                        'suspended' => $customerCount(fn ($row): bool => $row->status === 'suspended'),
                        'male' => $customerCount(fn ($row): bool => $row->gender === 'male'),
                        'female' => $customerCount(fn ($row): bool => $row->gender === 'female'),
                        'android' => $customerCount(fn ($row): bool => $row->platform === 'android'),
                        'ios' => $customerCount(fn ($row): bool => $row->platform === 'ios'),
                        'ordered' => User::query()->where('role', 'customer')->whereHas('orders')->count(),
                    ],
                    'orders' => [
                        'all' => (int) array_sum($orderCounts),
                        'today' => Order::query()->whereDate('created_at', today())->count(),
                        'cancelled_today' => Order::query()->whereIn('status', ['cancelled', 'canceled'])->whereDate('cancelled_at', today())->count(),
                        'under_review' => $orderCount('under_review'),
                        'accepted' => $orderCount('accepted', 'accepted_orders'),
                        'done' => $orderCount('completed', 'done', 'done_orders'),
                        'cancelled' => $orderCount('cancelled', 'canceled'),
                        'last_month' => Order::query()->whereBetween('created_at', $lastMonth)->count(),
                        'this_month' => Order::query()->whereBetween('created_at', $thisMonth)->count(),
                    ],
                    'engagement' => [
                        'shares' => (int) DB::table('app_shares')->count(),
                        'inquiries' => (int) DB::table('inquiry_logs')->count(),
                        'ordered_today' => (int) User::query()->where('role', 'customer')->whereHas('orders', fn ($query) => $query->whereDate('created_at', today()))->count(),
                        'ordered_last_month' => (int) User::query()->where('role', 'customer')->whereHas('orders', fn ($query) => $query->whereBetween('created_at', $lastMonth))->count(),
                        'ordered_this_month' => (int) User::query()->where('role', 'customer')->whereHas('orders', fn ($query) => $query->whereBetween('created_at', $thisMonth))->count(),
                    ],
                    'pending_approvals' => DB::table('approval_requests')->where('status', 'pending')->count(),
                    'active_partners' => $activePartners,
                    'revenue' => $completedMoney->total,
                    'maids' => [
                        'total' => (int) $maidTotals->total,
                        'active' => (int) $maidTotals->active,
                    ],
                    'total_order_amount' => $completedMoney->total,
                    'total_order_price' => $completedMoney->subtotal,
                    'total_maids_revenue' => $maidTotals->salary,
                    'total_discounts' => $completedMoney->discount_total,
                    'total_wallet' => Wallet::query()->sum('balance'),
                    // Count only orders whose package includes a service in a widget titled as cleaning.
                    // An unrelated widget is not used when no cleaning widget exists.
                    'widget_orders' => $this->widgetOrderCount(),
                    'widget_name' => $this->widgetOrderName(),
                    // Most-ordered package title. "No offer yet" when no package has orders.
                    'north_star_offer' => ServicePackage::query()->withCount('orders')->orderByDesc('orders_count')->first()?->title
                        ?: 'No offer yet',
                    // Maid profit = this month's completed order totals minus the monthly salary
                    // of maids assigned to those orders. There is no separate payroll ledger.
                    'maid_profit' => $this->maidProfit(),
                    // Partner profit = this month's completed order value assigned to a partner,
                    // after wallet and discount. No partner payout ledger exists.
                    'partner_profit' => $this->partnerProfit(),
                    // 'active_products' => Product::query()->where('is_active', true)->count(),
                    // 'published_faqs' => Faq::query()->where('is_active', true)->count(),
                ],
                'orderCounts' => $orderCounts,
                'recentOrders' => Order::query()->with('customer:id,name')->latest()->limit(6)
                    ->get(['id', 'customer_id', 'status', 'payment_status', 'total', 'service_date', 'created_at'])
                    ->map(fn (Order $order): array => [
                        'id' => $order->id,
                        'customer_name' => $order->customer?->name ?? 'Deleted customer',
                        'status' => $order->status,
                        'total' => (float) $order->total,
                        'service_date' => $order->service_date->format('d M Y'),
                    ])
                    ->all(),

                'topMaids' => Maid::withCount('orders')
                    ->orderByDesc('orders_count')
                    ->limit(10)
                    ->get(['name', 'orders_count'])->where('orders_count', '!=', 0),
                'topExtras' => OrderExtra::query()
                    ->select('extra_id', 'name')
                    ->selectRaw('SUM(quantity) as usage_count')
                    ->groupBy('extra_id', 'name')
                    ->orderByDesc('usage_count')
                    ->limit(10)
                    ->get(),
            ];
        });

        return view('dashboard', $overview);
    }

    public function profile(): View
    {
        $profileUser = $this->profileUser();

        return view('auth.profile', [
            'profileUser' => $profileUser,
            'walletBalance' => (float) ($profileUser->wallet?->balance ?? 0),
            'orderCount' => $profileUser->orders()->count(),
            'recentOrders' => $profileUser->orders()->latest()->limit(8)->get(),
            'walletTransactions' => $profileUser->wallet?->transactions()->latest()->limit(8)->get() ?? collect(),
            'addressCount' => $profileUser->addresses()->count(),
        ]);
    }

    private function cleaningWidget(): ?ServiceType
    {
        return ServiceType::query()->where('title', 'like', '%clean%')->orderBy('title')->first();
    }

    private function widgetOrderCount(): int
    {
        $widget = $this->cleaningWidget();
        if (! $widget) {
            return 0;
        }

        return Order::query()->whereHas('package.services.category', fn ($query) => $query->where('type_id', $widget->id))->count();
    }

    private function widgetOrderName(): string
    {
        return $this->cleaningWidget()?->title ?? 'No cleaning widget';
    }

    private function maidProfit(): float
    {
        $orders = Order::query()->where('status', 'completed')->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $revenue = (float) (clone $orders)->sum('total');
        $maidIds = DB::table('order_maids')->whereIn('order_id', (clone $orders)->select('id'))->pluck('maid_id');
        $cost = (float) Maid::query()->whereIn('id', $maidIds)->sum('salary');

        return round($revenue - $cost, 2);
    }

    private function partnerProfit(): float
    {
        return round((float) Order::query()
            ->where('status', 'completed')
            ->whereNotNull('partner_id')
            ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('COALESCE(SUM(total), 0) as profit')
            ->value('profit'), 2);
    }

    private function profileUser(): User
    {
        return User::query()
            ->with(['wallet', 'city:id,name', 'governorate:id,name'])
            ->findOrFail(auth()->id());
    }

    public function supporter(): View
    {
        return view('dashboards.supporter');
    }

    public function partner(): View
    {
        return view('dashboards.partner');
    }
}
