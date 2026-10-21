@extends('layouts.app')
@section('title', 'Orders')
@section('eyebrow', 'Support')
@section('heading', 'Orders')
@section('subtitle', 'Orders assigned to partners in your governorates.')
@section('content')
<div class="workspace-page">
    @if ($governorates->isEmpty())
        <section class="surface-card"><p>No governorates are assigned to this supporter.</p></section>
    @endif
    <section class="surface-card">
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Order</th><th>Customer</th><th>Partner</th><th>Status</th><th>Service date</th><th>Total</th></tr></thead><tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>#{{ $order->id }}</td>
                    <td>{{ $order->customer->name ?? '—' }}</td>
                    <td>{{ $order->partner->name ?? '—' }}</td>
                    <td>{{ str($order->status)->replace('_', ' ')->title() }}</td>
                    <td>{{ $order->service_date?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $order->total }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No orders are assigned to your governorates.</td></tr>
            @endforelse
        </tbody></table></div>
        {{ $orders->links() }}
    </section>
</div>
@endsection
