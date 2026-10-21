@extends('layouts.app')
@php
    $titles = ['under_review' => 'Under Review', 'waiting' => 'Waiting List', 'accepted' => 'Accepted Orders', 'completed' => 'Done Orders', 'cancelled' => 'Cancelled Orders'];
    $title = $titles[$status] ?? 'Orders';
@endphp
@section('title', $title)
@section('eyebrow', 'Order operations')
@section('heading', $title)
@section('subtitle', $status === 'cancelled' ? 'Cancellation queue' : 'Filtered order queue with the columns, actions, and export used in the reference workflow.')
@section('content')
    <div class="workspace-page orders-page">
        @include('orders.partials.navigation')
        @if (session('status'))<div class="catalog-notice" role="status">{{ session('status') }}</div>@endif
        <form class="toolbar" method="GET">
            <label class="field"><span>Search</span><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Order, customer, or phone"></label>
            <label class="field"><span>Date</span><input type="date" name="date" value="{{ $filters['date'] ?? '' }}"></label>
            <label class="field"><span>Sort</span><select name="sort"><option value="">Newest date</option><option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Highest price</option><option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Lowest price</option><option value="priority" @selected(($filters['sort'] ?? '') === 'priority')>Booking priority</option><option value="date_asc" @selected(($filters['sort'] ?? '') === 'date_asc')>Oldest date</option></select></label>
            <label class="field"><span>Partner</span><select name="partner_id"><option value="">All partners</option>@foreach ($partners as $partner)<option value="{{ $partner->id }}" @selected((string) ($filters['partner_id'] ?? '') === (string) $partner->id)>{{ $partner->name }}</option>@endforeach</select></label>
            <label class="field"><span>Governorate</span><select name="governorate_id"><option value="">All governorates</option>@foreach ($governorates as $governorate)<option value="{{ $governorate->id }}" @selected((string) ($filters['governorate_id'] ?? '') === (string) $governorate->id)>{{ $governorate->name }}</option>@endforeach</select></label>
            <button class="ui-button primary" type="submit">Apply</button>
            <a class="ui-button ghost" href="{{ url()->current() }}">Clear Filters</a>
            <a class="ui-button ghost" href="{{ route('admin.orders.export', ['status' => $status] + request()->query()) }}">Export Queue</a>
        </form>
        <section class="surface-card">
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Order ID</th><th>Customer</th><th>Phone</th><th>Governorate</th><th>Address</th><th>Widget</th><th>Category</th><th>Package</th><th>Partner</th><th>Maids</th><th>Extras</th><th>Payment</th><th>Order Date</th><th>Arrival</th><th>Total</th><th>Discount</th><th>Final</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>#{{ $order->id }}</td>
                        <td>{{ $order->customer?->name ?? '—' }}</td>
                        <td>{{ $order->customer?->phone ?? '—' }}</td>
                        <td>{{ $order->address?->governorate?->name ?? $order->address?->city?->name ?? '—' }}</td>
                        <td>{{ $order->address?->street ?? $order->address?->label ?? '—' }}</td>
                        <td>{{ $order->package?->services->map(fn ($service) => $service->category?->type?->title)->filter()->unique()->join(', ') ?: '—' }}</td>
                        <td>{{ $order->package?->services->map(fn ($service) => $service->category?->title)->filter()->unique()->join(', ') ?: '—' }}</td>
                        <td>{{ $order->package?->title ?? '—' }}</td>
                        <td>{{ $order->partner?->name ?? 'Unassigned' }}</td>
                        <td>{{ $order->maids->pluck('name')->join(', ') ?: '—' }}</td>
                        <td>{{ $order->extras->pluck('name')->join(', ') ?: '—' }}</td>
                        <td>{{ str($order->payment_method ?: '—')->replace('_', ' ')->title() }}</td>
                        <td>{{ $order->service_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $order->arrival_time ? substr((string) $order->arrival_time, 0, 5) : '—' }}</td>
                        <td>{{ number_format((float) $order->subtotal, 2) }}</td>
                        <td>{{ number_format((float) $order->discount_total, 2) }}</td>
                        <td>{{ number_format((float) $order->total, 2) }}</td>
                        <td>{{ str($order->status)->replace('_', ' ')->title() }}</td>
                        <td class="row-actions">
                            <button class="ui-button ghost" type="button" data-open-dialog="orderViewDialog" data-view-title="#{{ $order->id }} {{ $order->customer?->name }}" data-view-body="{{ e($order->customer?->name) }} · {{ e($order->address?->street ?? 'No address') }} · {{ e($order->package?->title ?? 'No package') }} · {{ e($order->payment_method ?: 'unpaid') }} · {{ e($order->partner?->name ?? 'No partner') }} · Maids: {{ e($order->maids->pluck('name')->join(', ') ?: 'none') }} · Extras: {{ e($order->extras->pluck('name')->join(', ') ?: 'none') }} · {{ $order->service_date?->format('d M Y') }} {{ substr((string) $order->arrival_time, 0, 5) }} · {{ e($order->status) }} · {{ e($order->internal_notes ?: $order->customer_notes ?: 'No notes') }}">View</button>
                            <button class="ui-button ghost" type="button" data-edit-order data-open-dialog="orderEditDialog" data-order-id="{{ $order->id }}" data-order-status="{{ $order->status }}" data-partner-id="{{ $order->partner_id }}" data-subtotal="{{ $order->subtotal }}" data-wallet-amount="{{ $order->wallet_amount }}" data-deposit-amount="{{ $order->deposit_amount }}" data-discount-total="{{ $order->discount_total }}" data-payment-method="{{ $order->payment_method }}" data-service-date="{{ $order->service_date?->format('Y-m-d') }}" data-arrival-time="{{ substr((string) $order->arrival_time, 0, 5) }}" data-notes="{{ e($order->internal_notes) }}" data-maid-ids='@json($order->maids->pluck('id'))' data-extra-ids='@json($order->extras->pluck('extra_id'))'>Edit</button>
                            @if ($status === 'cancelled')
                                <form method="POST" action="{{ route('admin.orders.workflow.update', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="under_review"><button class="ui-button ghost" type="submit">Back to Under Review</button></form>
                            @endif
                            <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" onsubmit="return confirm('Delete this order? It is retained as a soft-deleted record.')">@csrf @method('DELETE')<button class="ui-button danger" type="submit">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="19">No orders match these filters.</td></tr>
                @endforelse
            </tbody></table></div>
            {{ $orders->links() }}
        </section>
        <dialog id="orderViewDialog" class="modal-dialog"><div class="modal-card"><h2>Order details</h2><p data-view-target></p><form method="dialog"><button class="ui-button ghost">Close</button></form></div></dialog>
    </div>
@endsection
