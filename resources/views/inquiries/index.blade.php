@extends('layouts.app')
@section('title', 'Logs Inquiries')
@section('eyebrow', 'Inquiry log')
@section('heading', 'Logs Inquiries')
@section('subtitle', 'Customer inquiries and support requests already stored by the application.')
@section('content')
    <section class="overview-metrics" aria-label="Inquiry metrics">
        <article class="metric-card"><span>Total inquiries</span><strong>{{ number_format($metrics['total']) }}</strong></article>
        <article class="metric-card"><span>Open</span><strong>{{ number_format($metrics['open']) }}</strong></article>
    </section>
    <section class="surface-card"><h2>Inquiry funnel</h2><p>Sessions {{ $funnel['sessions'] }}. Offers selected {{ $funnel['with_offer'] }} ({{ $funnel['offer_rate'] }}%). Converted {{ $funnel['converted'] }} ({{ $funnel['conversion_rate'] }}%).</p><ul>@forelse ($funnel['areas'] as $area)<li>{{ $area->governorate_name }}: {{ $area->aggregate }}</li>@empty<li>No governorate values are stored on the current inquiry rows.</li>@endforelse</ul></section>
    <section class="surface-card">
        <div class="card-head">
            <div>
                <p class="eyebrow">Customer inquiry log</p>
                <h2>Recorded inquiries</h2>
            </div>
            <form class="catalog-filters" method="GET">
                <label>Customer name<input name="customer" value="{{ request('customer') }}"></label>
                <label>User ID<input name="user_id" value="{{ request('user_id') }}"></label>
                <button class="ui-button primary" type="submit">Search</button>
                <a class="ui-button ghost" href="{{ route('admin.inquiries.index') }}">Clear Filters</a>
                <a class="ui-button ghost" href="{{ route('admin.inquiries.export', request()->query()) }}">Export Sessions</a>
            </form>
        </div>
        <div class="data-table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>User ID</th>
                        <th>Session date</th>
                        <th>Time</th>
                        <th>Governorate</th>
                        <th>Widget</th>
                        <th>Category</th>
                        <th>Offer</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->user?->name ?? 'Unknown' }}</td>
                            <td>{{ $log->user_id ?: '—' }}</td>
                            <td>{{ $log->created_at?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $log->created_at?->format('H:i') ?? '—' }}</td>
                            <td>{{ $log->governorate_name ?: '—' }}</td>
                            <td>{{ $log->widget_name ?: '—' }}</td>
                            <td>{{ $log->category_name ?: '—' }}</td>
                            <td>{{ $log->offer_name ?: ($log->subject ?: '—') }}</td>
                            <td>{{ $log->session_result ?: ($log->status ?: '—') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-table">No inquiry records yet. This page is connected to inquiry logs and will fill when customers submit them.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </section>
@endsection
