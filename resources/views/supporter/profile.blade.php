@extends('layouts.app')
@section('title', 'My profile')
@section('eyebrow', 'Account')
@section('heading', 'My profile')
@section('subtitle', 'Your supporter account and assigned scope.')
@section('content')
<div class="workspace-page">
    @if (session('status'))<div class="catalog-notice" role="status">{{ session('status') }}</div>@endif
    <section class="surface-card">
        <form method="POST" action="{{ route('supporter.profile.update') }}">
            @csrf
            @method('PUT')
            <div class="service-form-grid">
                <label class="field"><span>Name</span><input name="name" value="{{ old('name', $supporter->name) }}" required></label>
                <label class="field"><span>Email</span><input value="{{ $supporter->email }}" disabled readonly></label>
                <label class="field"><span>Phone</span><input name="phone" value="{{ old('phone', $supporter->phone) }}"></label>
                <label class="field"><span>Role</span><input value="{{ str($supporter->settings['access_role'] ?? $supporter->role)->replace('_', ' ')->title() }}" disabled readonly></label>
                <label class="field wide"><span>Governorates</span><input value="{{ $supporter->governorates->pluck('name')->join(', ') ?: 'None assigned' }}" disabled readonly></label>
                <label class="field wide"><span>Permissions</span><input value="{{ collect($supporter->settings['permissions'] ?? [])->join(', ') ?: 'No extra permissions stored.' }}" disabled readonly></label>
            </div>
            <div class="form-actions"><button class="ui-button primary" type="submit">Save profile</button></div>
        </form>
    </section>
    <section class="surface-card">
        <h2>Attendance</h2>
        @if ($sessions->isEmpty())
            <p>No recorded attendance before tracking was enabled.</p>
        @else
            <p>{{ $openSession ? 'Online Now' : 'Offline' }}. Hours worked today: {{ number_format($sessions->sum(fn ($session) => $session->started_at && $session->started_at->isToday() ? $session->durationMinutes() : 0) / 60, 2) }}.</p>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Started</th><th>Ended</th><th>Last activity</th></tr></thead><tbody>
                @foreach ($sessions as $session)
                    <tr>
                        <td>{{ $session->started_at?->format('d M Y H:i') }}</td>
                        <td>{{ $session->ended_at?->format('d M Y H:i') ?? 'Open' }}</td>
                        <td>{{ $session->last_activity_at?->format('d M Y H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
</div>
@endsection
