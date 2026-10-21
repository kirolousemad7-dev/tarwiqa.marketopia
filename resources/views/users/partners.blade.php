@extends('layouts.app')
@section('title', 'Partners')
@section('eyebrow', 'Partner operations')
@section('heading', 'Partners')
@section('subtitle', 'Operational partner directory with governorate, work zone, maids, and completed orders.')
@section('content')
<div class="workspace-page users-page">
    <section class="overview-metrics">
        <article class="metric-card"><span>Partners</span><strong>{{ $total_users }}</strong></article>
        <article class="metric-card"><span>Managed maids</span><strong>{{ $managed_maids }}</strong></article>
        <article class="metric-card"><span>Completed orders</span><strong>{{ $completed_orders }}</strong></article>
    </section>
    <form class="toolbar" method="GET">
        <label class="field"><span>Status</span><select name="status"><option value="">All</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option><option value="restricted" @selected(request('status') === 'restricted')>Restricted</option><option value="suspended" @selected(request('status') === 'suspended')>Banned</option></select></label>
        <label class="field"><span>Governorate</span><select name="governorate_id"><option value="">All</option>@foreach (\App\Models\Governorate::query()->orderBy('name')->get(['id', 'name']) as $governorate)<option value="{{ $governorate->id }}" @selected((string) request('governorate_id') === (string) $governorate->id)>{{ $governorate->name }}</option>@endforeach</select></label>
        <button class="ui-button ghost" type="submit">Filter</button>
        <a class="ui-button ghost" href="{{ route('admin.partners.export', request()->only(['status', 'governorate_id'])) }}">Export Partners</a>
    </form>
    <section class="surface-card">
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Partner</th><th>Status</th><th>Governorate</th><th>Work Zone</th><th>Managed Maids</th><th>Completed Orders</th><th>Actions</th></tr></thead><tbody>
            @forelse ($partners as $partner)
                <tr>
                    <td>{{ $partner->name }}</td>
                    <td><span class="status-pill {{ $partner->accountStateClass() }}">{{ $partner->accountStateLabel() }}</span></td>
                    <td>{{ $partner->governorate?->name ?? '—' }}</td>
                    <td>{{ $partner->partnerAreas->pluck('name')->join(', ') ?: '—' }}</td>
                    <td>{{ $partner->maids_count }}</td>
                    <td>{{ $partner->completed_orders_count }}</td>
                    <td class="row-actions"><a class="ui-button ghost" href="{{ route('admin.users.edit', $partner) }}">Profile</a><form method="POST" action="{{ route('admin.users.bulk-status') }}">@csrf<input type="hidden" name="ids[]" value="{{ $partner->id }}"><button class="ui-button ghost" type="submit" name="action" value="{{ $partner->status === 'active' ? 'deactivate' : 'activate' }}" onclick="return confirm('{{ $partner->status === 'active' ? 'Deactivate this partner? Sign-in will be disabled, but maids, orders, and messages will be preserved.' : 'Activate this partner? Sign-in will be restored. Related records stay in place.' }}')">{{ $partner->status === 'active' ? 'Deactivate' : 'Activate' }}</button></form><button class="ui-button ghost" type="button" data-open-dialog="partner-chat-{{ $partner->id }}">Chat</button></td>
                </tr>
            @empty
                <tr><td colspan="7">No partners match these filters.</td></tr>
            @endforelse
        </tbody></table></div>
        {{ $partners->links() }}
    </section>
    @foreach ($partners as $partner)
        <dialog id="partner-chat-{{ $partner->id }}" class="modal-dialog" @if ((int) request('chat') === $partner->id) open @endif>
            <form class="modal-card partner-chat" method="POST" action="{{ route('admin.partners.chat', $partner) }}">
                @csrf
                <div class="attendance-head">
                    <div>
                        <p class="eyebrow">Partner chat</p>
                        <h2>{{ $partner->name }}</h2>
                    </div>
                    <button class="ui-button ghost" type="button" data-close-dialog>Close</button>
                </div>
                <div class="chat-thread">
                    @forelse (optional($threads->get($partner->id))->messages ?? [] as $message)
                        <article>
                            <strong>{{ $message->sender->name ?? 'Unknown' }}</strong>
                            <time>{{ $message->created_at ? $message->created_at->format('d M Y H:i') : '' }}</time>
                            <span>{{ $message->read_label }}</span>
                            <p>{{ $message->body }}</p>
                        </article>
                    @empty
                        <p>No messages yet. Send the first message to start this thread.</p>
                    @endforelse
                </div>
                @if ($threads->has($partner->id))
                    <input type="hidden" name="conversation_id" value="{{ $threads[$partner->id]->id }}">
                @endif
                <label class="field"><span>Message</span><textarea name="body" rows="3" required placeholder="Write a message"></textarea></label>
                <div class="form-actions"><button class="ui-button primary" type="submit">Send</button></div>
            </form>
        </dialog>
    @endforeach
</div>
@endsection
