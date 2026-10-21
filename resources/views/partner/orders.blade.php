@extends('layouts.app')
@section('title', 'Orders')
@section('eyebrow', 'Partner')
@section('heading', 'Orders')
@section('subtitle', 'Orders assigned to your partnership.')
@section('content')
<div class="workspace-page">
    <section class="surface-card">
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Service date</th><th>Total</th></tr></thead><tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>#{{ $order->id }}</td>
                    <td>{{ $order->customer->name ?? '—' }}</td>
                    <td>{{ str($order->status)->replace('_', ' ')->title() }}</td>
                    <td>{{ $order->service_date ? $order->service_date->format('d M Y') : '—' }}</td>
                    <td>{{ $order->total }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No orders are assigned to you.</td></tr>
            @endforelse
        </tbody></table></div>
        {{ $orders->links() }}
    </section>
</div>
@endsection
