@extends('layouts.app')
@section('title', 'Scheduled Orders')
@section('eyebrow', 'Booking availability')
@section('heading', 'Scheduled Orders')
@section('subtitle', 'Control whether customers can book today, tomorrow, weekdays, or specific calendar dates.')
@section('content')
<div class="workspace-page order-page">
    @include('orders.partials.navigation')
    @if (session('status'))<div class="catalog-notice">{{ session('status') }}</div>@endif
    <section class="surface-card">
        <div class="card-head"><h2>{{ $month->format('F Y') }}</h2><div class="page-actions"><a class="ui-button ghost" href="{{ route('admin.orders.scheduled', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}">Previous month</a><a class="ui-button ghost" href="{{ route('admin.orders.scheduled', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">Next month</a></div></div>
        <div class="calendar-grid" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px">
            @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)<strong>{{ $day }}</strong>@endforeach
            @for ($blank = 0; $blank < $month->dayOfWeek; $blank++)<span></span>@endfor
            @for ($day = 1; $day <= $month->daysInMonth; $day++)
                @php($date = $month->copy()->day($day)->toDateString())
                <a href="{{ route('admin.orders.scheduled', ['month' => $month->format('Y-m'), 'date' => $date]) }}" style="display:block;padding:8px;border:1px solid #d9e2ec;border-radius:8px;{{ in_array($date, $schedule['closed_dates'], true) ? 'background:#fde8e8' : '' }}">
                    <strong>{{ $day }}</strong>
                    <small>{{ (int) ($counts[$date] ?? $counts[$date.' 00:00:00'] ?? 0) }} orders</small>
                    <small>{{ in_array($date, $schedule['closed_dates'], true) ? 'Closed' : 'Open' }}</small>
                </a>
            @endfor
        </div>
        @if ($selected)
            <h3>Orders on {{ $selected }}</h3>
            <ul>@forelse ($orders->filter(fn ($order) => $order->service_date?->toDateString() === $selected) as $order)<li>#{{ $order->id }} {{ $order->customer?->name }} · {{ $order->status }} · {{ substr((string) $order->arrival_time, 0, 5) }}</li>@empty<li>No scheduled orders on this date.</li>@endforelse</ul>
        @endif
    </section>
    <form method="POST" action="{{ route('admin.orders.scheduled.save') }}">
        @csrf
        <section class="overview-metrics">
            <article class="metric-card"><span>Open dates</span><strong>{{ collect($schedule['closed_dates'])->count() === 0 ? now()->daysInMonth : max(0, now()->daysInMonth - count($schedule['closed_dates'])) }}</strong></article>
            <article class="metric-card"><span>Closed dates</span><strong>{{ count($schedule['closed_dates']) }}</strong></article>
            <article class="metric-card"><span>Today booking</span><strong>{{ $schedule['today_open'] ? 'Open' : 'Closed' }}</strong></article>
            <article class="metric-card"><span>Tomorrow booking</span><strong>{{ $schedule['tomorrow_open'] ? 'Open' : 'Closed' }}</strong></article>
        </section>
        <section class="surface-card">
            <h2>Booking Door Status</h2>
            <label><input type="checkbox" name="today_open" value="1" @checked($schedule['today_open'])> Today open for booking</label>
            <label><input type="checkbox" name="tomorrow_open" value="1" @checked($schedule['tomorrow_open'])> Tomorrow open for booking</label>
            <h2>Allowed order arrival times</h2>
            <div class="catalog-filters">
                @foreach ($schedule['hours'] as $hour => $open)
                    <label><input type="checkbox" name="open_hours[]" value="{{ $hour }}" @checked($open)> {{ \Illuminate\Support\Carbon::createFromTime($hour)->format('g A') }}</label>
                @endforeach
            </div>
            <h2>Closed dates</h2>
            <label>Add a closed date<input type="date" name="closed_dates[]"></label>
            @foreach ($schedule['closed_dates'] as $date)
                <input type="hidden" name="closed_dates[]" value="{{ $date }}">
                <span class="status-pill">{{ $date }}</span>
            @endforeach
            <label>Closed until<input type="date" name="closed_until" value="{{ $schedule['closed_until'] }}"></label>
            <div class="prototype-dialog-actions"><button class="ui-button ghost" type="reset">Reset</button><button class="ui-button primary" type="submit">Save Schedule</button></div>
        </section>
    </form>
    <section class="surface-card">
        <h2>Current scheduled orders</h2>
        <table><thead><tr><th>Order</th><th>Customer</th><th>Widget</th><th>Category</th><th>Date</th><th>Arrival</th><th>Status</th></tr></thead><tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>#{{ $order->id }}</td>
                    <td>{{ $order->customer?->name ?? '—' }}</td>
                    <td>{{ $order->package?->services->map(fn ($service) => $service->category?->type?->title)->filter()->unique()->join(', ') ?: '—' }}</td>
                    <td>{{ $order->package?->services->map(fn ($service) => $service->category?->title)->filter()->unique()->join(', ') ?: '—' }}</td>
                    <td>{{ $order->service_date?->format('d M Y') }}</td>
                    <td>{{ $order->arrival_time ?: '—' }}</td>
                    <td>{{ str($order->status)->replace('_', ' ')->title() }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-table">No upcoming orders.</td></tr>
            @endforelse
        </tbody></table>
    </section>
</div>
@endsection
