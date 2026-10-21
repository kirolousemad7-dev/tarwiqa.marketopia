@extends('layouts.app')
@section('title', 'Messages')
@section('eyebrow', 'Partner')
@section('heading', 'Messages')
@section('subtitle', 'Conversations that include your account.')
@section('content')
<div class="workspace-page">
    @if (session('status'))<div class="catalog-notice" role="status">{{ session('status') }}</div>@endif
    <section class="surface-card">
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Subject</th><th>Latest</th><th>State</th></tr></thead><tbody>
            @forelse ($conversations as $conversation)
                <tr>
                    <td><a href="{{ route('partner.messages', ['conversation' => $conversation->id]) }}">{{ $conversation->subject ?: 'Conversation #'.$conversation->id }}</a></td>
                    <td>{{ $conversation->latestMessage?->body ?? '—' }}</td>
                    <td>{{ $conversation->read_label }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Your inbox is empty.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
    @if ($selected)
        <section class="surface-card">
            <h2>{{ $selected->subject ?: 'Conversation #'.$selected->id }}</h2>
            <div class="chat-thread">
                @foreach ($selected->messages as $message)
                    <article>
                        <strong>{{ $message->sender->name ?? 'Unknown' }}</strong>
                        <time>{{ $message->created_at ? $message->created_at->format('d M Y H:i') : '' }}</time>
                        <p>{{ $message->body }}</p>
                    </article>
                @endforeach
            </div>
            @if (can_access_page('messages.view'))
                <form method="POST" action="{{ route('partner.messages.reply', $selected) }}">
                    @csrf
                    <label class="field"><span>Reply</span><textarea name="body" rows="3" required></textarea></label>
                    <div class="form-actions"><button class="ui-button primary" type="submit">Send</button></div>
                </form>
            @endif
        </section>
    @endif
</div>
@endsection
