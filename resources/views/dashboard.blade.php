@extends('layouts.app')
@section('title', 'TARWIQA Dashboard')
@section('eyebrow', 'Executive Overview')
@section('heading', 'TARWIQA Dashboard')
@section('subtitle', 'A clean administration overview for users, orders, partners, revenue, campaigns, and operational performance.')
@php
        $users = $metrics['users'];
        $orders = $metrics['orders'];
        $engagement = $metrics['engagement'];
        $cards = [
            ['Employees Active', number_format($metrics['employees']['active'])],
            ['Users Ordered The Service', number_format($users['ordered'])],
            ['All Users', number_format($users['total'])],
            ['Active Users', number_format($users['active'])],
            ['Female', number_format($users['female'])],
            ['Male', number_format($users['male'])],
            ['Android Platforms', number_format($users['android'])],
            ['iOS Platforms', number_format($users['ios'])],
            ['Shares', number_format($engagement['shares'])],
            ['Logs Inquires', number_format($engagement['inquiries'])],
            ['Inactive Users', number_format($users['inactive'] ?? 0)],
            ['Restricted Users', number_format($users['restricted'])],
            ['Banned Users', number_format($users['suspended'])],
            ['Cancelled Orders Today', number_format($orders['cancelled_today'])],
            ['Had Ordered Today', number_format($engagement['ordered_today'])],
            ['Num Ordered Last Month', number_format($orders['last_month'])],
            ['Num Ordered In This Month', number_format($orders['this_month'])],
            ['Done Orders', number_format($orders['done'])],
            ['Under Review Order', number_format($orders['under_review'])],
            ['Accepted Orders', number_format($orders['accepted'])],
            ['All Cancelled Orders', number_format($orders['cancelled'])],
            ['Total Maids', number_format($metrics['maids']['total'])],
            ['Active Maids', number_format($metrics['maids']['active'])],
            ['Active Partners', number_format($metrics['active_partners']), 'Currently active across all work zones', 'partner-metric'],
            ['Widget Order', number_format($metrics['widget_orders'] ?? 0), $metrics['widget_name'] ?? 'Widget', 'accent'],
            ['North Star Offer', $metrics['north_star_offer'] ?? 'No offer yet', 'Most ordered package', 'accent'],
            ['Total Orders Amount', 'EGP '.format_amount($metrics['total_order_amount']), null, 'money'],
            ['Total Orders Price', 'EGP '.format_amount($metrics['total_order_price']), null, 'money'],
            ['Total Maids Revenue', 'EGP '.format_amount($metrics['total_maids_revenue']), null, 'money'],
            ['Maid revenue minus salaries', 'EGP '.format_amount($metrics['maid_profit'] ?? 0), 'Completed order totals minus the full salaries of maids assigned to those orders. This is not an allocated profit.', 'money profit-metric'],
            ['Partner payable', 'EGP '.format_amount($metrics['partner_profit'] ?? 0), 'Sum of completed order totals assigned to partners this month. This is payable revenue, not profit.', 'money profit-metric'],
            ['Revenue', 'EGP '.format_amount($metrics['revenue']), null, 'money'],
            ['Total Discounts', 'EGP '.format_amount($metrics['total_discounts']), null, 'money'],
            ['Wallet', 'EGP '.format_amount($metrics['total_wallet']), 'Total customer wallets', 'money'],
        ];
@endphp
@section('page-actions')
    <div class="prototype-toolbar header-toolbar">
        <button class="ui-button primary" type="button" data-export-dashboard>Export Dashboard</button>
        <a class="ui-button ghost" href="{{ route('admin.dashboard', ['refresh' => 1]) }}">Refresh KPIs</a>
    </div>
@endsection
@section('content')
    <div class="workspace-page admin-overview-page prototype-dashboard">

        <section class="overview-welcome prototype-snapshot">
            <div>
                <p class="eyebrow">Live Operations</p>
                <h2>Business Health Snapshot</h2>
                <p>Monitor workforce activity, orders, revenue, users, shares, inquiries, and the strongest performing supply and demand signals from one screen.</p>
            </div>
            <div class="prototype-tags">
                <span>Employees Active: {{ number_format($metrics['employees']['active']) }}</span>
                <span>North Star Offer: {{ $metrics['north_star_offer'] ?? 'No offer yet' }}</span>
                <span>{{ $metrics['widget_name'] ?? 'Widget' }} Orders: {{ number_format($metrics['widget_orders'] ?? 0) }}</span>
            </div>
        </section>

        <section class="prototype-kpi-grid" aria-label="Business summary">
            @foreach ($cards as $card)
                <article @class([$card[3] ?? ''])>
                    <span>{{ $card[0] }}</span>
                    <strong>{{ $card[1] }}</strong>
                    @if (! empty($card[2]))
                        <small>{{ $card[2] }}</small>
                    @endif
                </article>
            @endforeach
        </section>

        <div class="leaders-grid">
            <section class="surface-card leader-card">
                <div class="card-head">
                    <div>
                        <p class="eyebrow">Ranking</p>
                        <h2>Top 10 Maids</h2>
                    </div>
                    <span>By completed orders</span>
                </div>
                @forelse ($topMaids as $maid)
                    <div class="leader-item"><strong>{{ $loop->iteration }}. {{ data_get($maid, 'name', is_scalar($maid) ? $maid : 'Maid') }}</strong><span>{{ number_format((int) data_get($maid, 'orders_count', 0)) }} orders</span></div>
                @empty
                    <p class="empty-table">Top maids appear after they are assigned to orders.</p>
                @endforelse
            </section>
            <section class="surface-card leader-card">
                <div class="card-head">
                    <div>
                        <p class="eyebrow">Ranking</p>
                        <h2>Top 10 Extra</h2>
                    </div>
                    <span>By most requested extras</span>
                </div>
                @forelse ($topExtras as $extra)
                    <div class="leader-item"><strong>{{ $loop->iteration }}. {{ data_get($extra, 'name') ?: 'Unnamed extra' }}</strong><span>{{ number_format((int) data_get($extra, 'usage_count')) }} uses</span></div>
                @empty
                    <p class="empty-table">Top extras appear after they are added to orders.</p>
                @endforelse
            </section>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        document.querySelector('[data-export-dashboard]')?.addEventListener('click', () => {
            const rows = [...document.querySelectorAll('.prototype-kpi-grid article')].map((card) => {
                const cells = [...card.children].map((node) => `"${node.textContent.trim().replaceAll('"', '""')}"`);
                return cells.join(',');
            });
            const blob = new Blob([['Metric,Value,Note', ...rows].join('\n')], { type: 'text/csv' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'tarwiqa-dashboard.csv';
            link.click();
            URL.revokeObjectURL(link.href);
        });
    </script>
@endpush
