@extends('layouts.app')
@section('title', request('audience') === 'partners' ? 'Partners Messages' : 'Messages Inbox')
@section('eyebrow', 'Customer Messaging')
@section('heading', request('audience') === 'partners' ? 'Partners Messages' : 'Inbox Messages')
@section('subtitle', 'Mailbox for stored conversations, with read, junk, star, and reply actions.')
@section('page-actions')
    <a class="ui-button primary" href="{{ route('admin.messages.create') }}">Compose Reply</a>
    <button class="ui-button ghost" type="button" data-open-dialog="inboxRulesDialog">Open Message Rules</button>
@endsection
@section('content')
    <section class="overview-metrics" aria-label="Message metrics">
        <article class="metric-card"><span>Unread</span><strong>{{ $metrics['unread'] }}</strong></article>
        <article class="metric-card"><span>Pending</span><strong>{{ $metrics['pending_replies'] }}</strong></article>
        <article class="metric-card"><span>Starred</span><strong>{{ $metrics['starred_threads'] }}</strong></article>
        <article class="metric-card"><span>Junk</span><strong>{{ $metrics['junk'] }}</strong></article>
    </section>
    <div class="workspace-page messages-page">
        @if (session('status'))<div class="catalog-notice" role="status">{{ session('status') }}</div>@endif
        <form class="toolbar" method="GET">
            @if (request('audience'))<input type="hidden" name="audience" value="{{ request('audience') }}">@endif
            <a class="ui-button {{ request('box', 'inbox') === 'inbox' ? 'primary' : 'ghost' }}" href="{{ route('admin.messages.index', request()->except('box')) }}">Inbox</a>
            <a class="ui-button {{ request('box') === 'sent' ? 'primary' : 'ghost' }}" href="{{ route('admin.messages.index', ['box' => 'sent'] + request()->except('box')) }}">Sent</a>
            <a class="ui-button {{ request('box') === 'junk' ? 'primary' : 'ghost' }}" href="{{ route('admin.messages.index', ['box' => 'junk'] + request()->except('box')) }}">Junk</a>
            <a class="ui-button {{ request('box') === 'starred' ? 'primary' : 'ghost' }}" href="{{ route('admin.messages.index', ['box' => 'starred'] + request()->except('box')) }}">Starred</a>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search conversations">
            <button class="ui-button ghost" type="submit">Search</button>
        </form>
        <form method="POST" action="{{ route('admin.messages.inbox') }}">
            @csrf
            <div class="page-actions">
                <button class="ui-button ghost" type="submit" name="action" value="read">Mark as Read</button>
                <button class="ui-button ghost" type="submit" name="action" value="junk">Move to Junk</button>
                <button class="ui-button ghost" type="submit" name="action" value="star">Star</button>
                <button class="ui-button ghost" type="submit" name="action" value="inbox">Unstar / Inbox</button>
            </div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th><input type="checkbox" data-select-visible aria-label="Select all"></th><th>Customer Name</th><th>User ID</th><th>Message Preview</th><th>Box</th><th>Status</th><th>Last Update</th><th></th></tr></thead><tbody>
                @forelse ($conversations as $conversation)
                    @php($person = $conversation->participants->firstWhere('id', '!=', auth()->id()))
                    @php($preview = $conversation->messages->first()?->body)
                    <tr>
                        <td><input class="message-checkbox" type="checkbox" name="ids[]" value="{{ $conversation->id }}"></td>
                        <td>{{ $person?->name ?? 'No recipient' }}</td>
                        <td>{{ $person?->id ?? '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($preview ?: $conversation->subject, 80) }}</td>
                        <td>{{ $conversation->box }}{{ $conversation->is_starred ? ' · starred' : '' }}</td>
                        <td>{{ $conversation->box === 'sent' ? 'Sent' : 'Stored' }}</td>
                        <td>{{ $conversation->last_message_at?->diffForHumans() }}</td>
                        <td><button class="ui-button ghost" type="button" data-open-dialog="message-{{ $conversation->id }}">Details</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8">Your inbox is empty</td></tr>
                @endforelse
            </tbody></table></div>
        </form>
        @foreach ($conversations as $conversation)
            <dialog id="message-{{ $conversation->id }}" class="modal-dialog"><div class="modal-card"><h2>{{ $conversation->subject ?: 'Conversation' }}</h2>@foreach ($conversation->messages()->oldest()->limit(20)->get() as $message)<p><strong>{{ $message->sender?->name ?? 'User' }}</strong> {{ $message->body }}</p>@endforeach<form method="POST" action="{{ route('admin.messages.reply', $conversation) }}">@csrf<label class="field wide"><span>Reply</span><textarea name="body" required></textarea></label><button class="ui-button primary" type="submit">Send reply</button></form><form method="dialog"><button class="ui-button ghost">Close</button></form></div></dialog>
        @endforeach
        <dialog id="inboxRulesDialog" class="modal-dialog"><form class="modal-card" method="POST" action="{{ route('admin.messages.rules') }}">@csrf<h2>Message rules</h2><label class="field wide"><span>Routing note</span><textarea name="routing_note">{{ $rules['routing_note'] ?? '' }}</textarea></label><button class="ui-button primary" type="submit">Save rules</button></form></dialog>
    </div>
@endsection
