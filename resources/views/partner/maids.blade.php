@extends('layouts.app')
@section('title', 'Maids')
@section('eyebrow', 'Partner')
@section('heading', 'Maids')
@section('subtitle', 'Maids managed by your partnership.')
@section('content')
<div class="workspace-page">
    <section class="surface-card">
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Maid</th><th>Phone</th><th>Status</th><th>Off day</th></tr></thead><tbody>
            @forelse ($maids as $maid)
                <tr>
                    <td>{{ $maid->name }}</td>
                    <td>{{ $maid->phone ?: '—' }}</td>
                    <td>{{ str($maid->status)->replace('_', ' ')->title() }}</td>
                    <td>{{ $maid->off_day }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No maids are assigned to you.</td></tr>
            @endforelse
        </tbody></table></div>
        {{ $maids->links() }}
    </section>
</div>
@endsection
