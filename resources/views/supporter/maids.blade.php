@extends('layouts.app')
@section('title', 'Maids')
@section('eyebrow', 'Support')
@section('heading', 'Maids')
@section('subtitle', 'Maids working with partners in your governorates.')
@section('content')
<div class="workspace-page">
    @if ($governorates->isEmpty())
        <section class="surface-card"><p>No governorates are assigned to this supporter.</p></section>
    @endif
    <section class="surface-card">
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Maid</th><th>Phone</th><th>Partner</th><th>Governorate</th><th>Status</th></tr></thead><tbody>
            @forelse ($maids as $maid)
                <tr>
                    <td>{{ $maid->name }}</td>
                    <td>{{ $maid->phone ?: '—' }}</td>
                    <td>{{ $maid->partner->name ?? '—' }}</td>
                    <td>{{ $maid->partner->governorate->name ?? '—' }}</td>
                    <td>{{ str($maid->status)->replace('_', ' ')->title() }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No maids are assigned to your governorates.</td></tr>
            @endforelse
        </tbody></table></div>
        {{ $maids->links() }}
    </section>
</div>
@endsection
