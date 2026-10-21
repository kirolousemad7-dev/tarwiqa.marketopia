@extends('layouts.app')
@section('title', 'Edit User')
@section('eyebrow', 'People and access')
@section('heading', 'Edit User')
@section('subtitle', 'Update account identity, access role, status, or city.')
@section('content')
    <div class="workspace-page catalog-editor-page">
        <section class="surface-card">
            <div class="card-head">
                <div>
                    <p class="eyebrow">Account record</p>
                    <h2>{{ $user->name }}</h2>
                </div>
            </div>
            @if ($user->role === 'partner')
                <p>Governorate: {{ $user->governorate?->name ?? '—' }}. Work zone: {{ $user->partnerAreas->pluck('name')->join(', ') ?: '—' }}. Managed maids: {{ $user->maids()->count() }}. Completed orders: {{ $user->assignedOrders()->whereIn('status', ['completed', 'done', 'done_orders'])->count() }}.</p>
                <p>Permissions: {{ collect($user->settings['permissions'] ?? [])->join(', ') ?: 'No extra permissions stored.' }}</p>
                <p><a class="ui-button ghost" href="{{ route('admin.messages.partners', ['partner' => $user->id]) }}">Open partner chat</a></p>
            @endif
            @if ($user->role === 'supporter')
                @php
                    $sessions = \App\Models\SupporterSession::query()->where('user_id', $user->id)->latest('started_at')->limit(20)->get();
                    $open = $sessions->first(fn ($session) => $session->isOnline());
                    $todayMinutes = \App\Models\SupporterSession::query()->where('user_id', $user->id)->where('started_at', '>=', now()->startOfDay())->get()->sum(fn ($session) => $session->durationMinutes(now()));
                    $workingDays = \App\Models\SupporterSession::query()->where('user_id', $user->id)->where('started_at', '>=', now()->startOfMonth())->get()->map(fn ($session) => $session->started_at?->toDateString())->filter()->unique()->count();
                    $latest = $sessions->first();
                @endphp
                <section class="attendance-card">
                    <div class="attendance-head">
                        <div>
                            <p class="eyebrow">Live activity</p>
                            <h2>Current Session &amp; Attendance</h2>
                        </div>
                        <span class="status-pill {{ $open ? 'is-online' : '' }}">{{ $open ? 'Online Now' : 'Offline' }}</span>
                    </div>
                    <p>Role: {{ str($user->settings['access_role'] ?? 'supporter')->title() }}. Governorates: {{ $user->governorates->count() }}. Permissions: {{ collect($user->settings['permissions'] ?? [])->join(', ') ?: 'No extra permissions stored.' }}</p>
                    <div class="attendance-metrics">
                        <article><span>Dashboard Status</span><strong>{{ $open ? 'Open since '.$open->started_at?->format('h:i A') : 'No open session' }}</strong></article>
                        <article><span>Last Seen</span><strong>{{ $latest?->last_activity_at?->diffForHumans() ?? '—' }}</strong></article>
                        <article><span>Hours Worked Today</span><strong>{{ number_format($todayMinutes / 60, 2) }} hours</strong></article>
                        <article><span>Working Days This Month</span><strong>{{ $workingDays }} days</strong></article>
                    </div>
                    @if ($sessions->isEmpty())
                        <p>No recorded attendance before tracking was enabled.</p>
                    @else
                        <h3>Recent sessions</h3>
                        <table class="data-table"><thead><tr><th>Started</th><th>Ended</th><th>Last activity</th><th>Duration</th></tr></thead><tbody>
                            @foreach ($sessions as $session)
                                <tr><td>{{ $session->started_at?->format('d M Y H:i') }}</td><td>{{ $session->ended_at?->format('d M Y H:i') ?? 'Open' }}</td><td>{{ $session->last_activity_at?->format('d M Y H:i') ?? '—' }}</td><td>{{ $session->durationMinutes() }} min</td></tr>
                            @endforeach
                        </tbody></table>
                    @endif
                </section>
                <section class="attendance-card">
                    <p class="eyebrow">Security</p>
                    <h2>Password &amp; Access</h2>
                    <form method="POST" action="{{ route('admin.users.temporary-password', $user) }}">
                        @csrf
                        <label class="field"><span>Saved Password</span><input value="••••••••" disabled readonly></label>
                        <p>Stored passwords stay hashed and cannot be revealed.</p>
                        <button class="ui-button primary" type="submit">Reset Password</button>
                    </form>
                    @if ((int) session('temporary_password_user') === $user->id && session('temporary_password'))
                        <p class="temporary-password">Temporary password, shown once: <span data-secret hidden>{{ session('temporary_password') }}</span> <button class="ui-button ghost" type="button" data-show-secret>Show</button></p>
                    @endif
                </section>
            @endif
            @if (in_array($user->role, ['customer', 'partner', 'supporter'], true))
                <section class="attendance-card">
                    <p class="eyebrow">Account state</p>
                    <h2><span class="status-pill {{ $user->accountStateClass() }}">{{ $user->accountStateLabel() }}</span></h2>
                    <p>Changing the account state keeps the user row, orders, wallet history, messages, addresses, and related records.</p>
                    <form method="POST" action="{{ route('admin.users.bulk-status') }}">
                        @csrf
                        <input type="hidden" name="ids[]" value="{{ $user->id }}">
                        <input type="hidden" name="ban_type" value="">
                        @if ($user->role === 'customer')
                            <button class="ui-button ghost" type="submit" name="action" value="activate" onclick="return confirm('Activate this user? They will be able to sign in again. Orders and history stay in place.')">Restore / Activate</button>
                            <button class="ui-button ghost" type="submit" name="action" value="deactivate" onclick="return confirm('Deactivate this user? Their account will be disabled, but all orders and history will be preserved.')">Deactivate Account</button>
                            <button class="ui-button ghost" type="submit" name="action" value="restrict" onclick="return confirm('Restrict this user? Sign-in will be disabled until the account is restored. Orders and history will be preserved.')">Restrict Account</button>
                            <button class="ui-button primary" type="submit" name="action" value="ban" onclick="this.form.ban_type.value='temporary'; return confirm('Temporarily ban this user? They cannot sign in until the ban is lifted. Orders and history will be preserved.')">Temporary Ban</button>
                            <button class="ui-button primary" type="submit" name="action" value="ban" onclick="this.form.ban_type.value='permanent'; return confirm('Permanently ban this user? They cannot sign in until an admin restores the account. This does not delete the user or their history.')">Permanent Ban</button>
                            <a class="ui-button ghost" href="{{ route('admin.users.logs', $user) }}">Export Logs</a>
                            <a class="ui-button ghost" href="{{ route('admin.messages.create', ['user' => $user->id]) }}">Send Message</a>
                        @else
                            <button class="ui-button ghost" type="submit" name="action" value="activate" onclick="return confirm('Activate this account? Sign-in will be restored. Related maids, orders, messages, and permissions stay in place.')">Activate</button>
                            <button class="ui-button primary" type="submit" name="action" value="deactivate" onclick="return confirm('Deactivate this account? Sign-in will be disabled, but maids, orders, messages, sessions, and permissions will be preserved.')">Deactivate</button>
                        @endif
                    </form>
                </section>
            @endif
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')
                @include('users.partials.form')
            </form>
        </section>
    </div>

    @includeIf("users.partials.{$user->role}_role_form")
    
@endsection


