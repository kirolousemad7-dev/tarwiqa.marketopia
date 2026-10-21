@extends('layouts.app')
@section('title', 'Slider Ads')
@section('eyebrow', 'Marketing')
@section('heading', 'Slider Ads')
@section('subtitle', 'Campaign slots with image, destination, schedule, and live preview.')
@section('content')
<section class="overview-metrics" aria-label="Ad metrics">
    <article class="metric-card"><span>Active ads</span><strong>{{ $metrics['active_ads'] }}</strong></article>
    <article class="metric-card"><span>Scheduled ads</span><strong>{{ $metrics['scheduled_ads'] }}</strong></article>
    <article class="metric-card"><span>Top slot</span><strong>{{ $metrics['top_slot'] }}</strong></article>
    <article class="metric-card"><span>Clicks</span><strong>{{ $metrics['clicks'] }}</strong></article>
</section>
<div class="workspace-page ads-page">
    @if (session('status'))<div class="catalog-notice" role="status">{{ session('status') }}</div>@endif
    <div class="page-actions">
        <button class="ui-button primary" type="button" data-open-dialog="createAdDialog">Create Ad Slot</button>
        <a class="ui-button ghost" href="{{ route('admin.ads.history') }}">Ads History</a>
    </div>
    <div class="ads-grid campaign-grid">
        @forelse ($ads as $ad)
            @php
                $ctr = $ad->impressions > 0 ? number_format(($ad->clicks / $ad->impressions) * 100, 0) : '0';
                $tone = $ad->id % 3;
            @endphp
            <article class="campaign-card campaign-tone-{{ $tone }}">
                <div class="campaign-creative {{ $ad->image_url ? 'has-image' : '' }}" @if($ad->image_url) style="background-image: linear-gradient(160deg, rgba(12,40,32,.72), rgba(18,90,70,.55)), url('{{ $ad->image_url }}')" @endif>
                    <span class="campaign-kicker">{{ $ad->slot_name }}</span>
                    <h3>{{ $ad->title }}</h3>
                    <p>{{ $ad->description ?: 'Campaign creative' }}</p>
                    <span class="status-pill">{{ str($ad->status)->replace('_', ' ')->title() }}</span>
                </div>
                <div class="campaign-meta">
                    <p class="eyebrow">Ad space</p>
                    <strong>{{ $ad->title }}</strong>
                    <p>{{ $ad->starts_at?->format('d M Y') ?? 'Now' }} → {{ $ad->end_date?->format('d M Y') ?? 'Open' }}</p>
                    <p><a href="{{ route('ads.click', $ad) }}">{{ $ad->destination }}</a></p>
                    <p>{{ number_format($ad->impressions) }} impressions · {{ number_format($ad->clicks) }} clicks · {{ $ctr }}% CTR</p>
                    <div class="row-actions">
                        <button class="ui-button primary" type="button" data-open-dialog="edit-ad-{{ $ad->id }}">Edit</button>
                        <form method="POST" action="{{ route('admin.ads.toggle', $ad) }}">@csrf @method('PATCH')<button class="ui-button ghost" type="submit">{{ $ad->status === 'active' ? 'Pause' : 'Activate' }}</button></form>
                        <button class="ui-button ghost" type="button" data-open-dialog="preview-ad-{{ $ad->id }}">Preview</button>
                    </div>
                </div>
            </article>
            <dialog id="edit-ad-{{ $ad->id }}" class="modal-dialog"><form class="modal-card ad-editor" method="POST" action="{{ route('admin.ads.update', $ad) }}">@csrf @method('PATCH')<h2>Edit slot</h2><label class="field"><span>Title</span><input name="title" value="{{ $ad->title }}" required data-ad-field="title"></label><label class="field"><span>Description</span><textarea name="description" rows="2" data-ad-field="description">{{ $ad->description }}</textarea></label><label class="field"><span>Slot</span><input name="slot_name" value="{{ $ad->slot_name }}" required data-ad-field="slot"></label><label class="field"><span>Destination</span><input name="destination" type="url" value="{{ $ad->destination }}" required data-ad-field="link"></label><label class="field"><span>Image URL</span><input name="image_url" type="url" value="{{ $ad->image_url }}" data-ad-field="image"></label><label class="field"><span>Starts</span><input name="starts_at" type="datetime-local" value="{{ $ad->starts_at?->format('Y-m-d\TH:i') }}" data-ad-field="starts"></label><label class="field"><span>Ends</span><input name="end_date" type="datetime-local" value="{{ $ad->end_date?->format('Y-m-d\TH:i') }}" data-ad-field="ends"></label><aside class="campaign-card campaign-tone-{{ $tone }} ad-live-preview"><div class="campaign-creative" data-preview-creative @if($ad->image_url) style="background-image: linear-gradient(160deg, rgba(12,40,32,.72), rgba(18,90,70,.55)), url('{{ $ad->image_url }}')" @endif><span class="campaign-kicker" data-preview-slot>{{ $ad->slot_name }}</span><h3 data-preview-title>{{ $ad->title }}</h3><p data-preview-copy>{{ $ad->description ?: 'Campaign creative' }}</p><span class="status-pill" data-preview-status>{{ str($ad->status)->replace('_', ' ')->title() }}</span></div><div class="campaign-meta"><span data-preview-dates>{{ $ad->starts_at?->format('d M Y') ?? 'Now' }} → {{ $ad->end_date?->format('d M Y') ?? 'Open' }}</span><span data-preview-link>{{ $ad->destination }}</span></div></aside><button class="ui-button primary" type="submit">Save</button></form></dialog>
            <dialog id="preview-ad-{{ $ad->id }}" class="modal-dialog"><div class="modal-card"><article class="campaign-card campaign-tone-{{ $tone }}"><div class="campaign-creative {{ $ad->image_url ? 'has-image' : '' }}" @if($ad->image_url) style="background-image: linear-gradient(160deg, rgba(12,40,32,.72), rgba(18,90,70,.55)), url('{{ $ad->image_url }}')" @endif><span class="campaign-kicker">{{ $ad->slot_name }}</span><h3>{{ $ad->title }}</h3><p>{{ $ad->description ?: 'Campaign creative' }}</p></div><div class="campaign-meta"><p>{{ $ad->destination }}</p><p>{{ number_format($ad->impressions) }} impressions · {{ number_format($ad->clicks) }} clicks · {{ $ctr }}% CTR</p></div></article><form method="dialog"><button class="ui-button ghost">Close</button></form></div></dialog>
        @empty
            <p class="empty-table">No ads created yet.</p>
        @endforelse
    </div>
    {{ $ads->links() }}
    <dialog id="createAdDialog" class="modal-dialog"><form class="modal-card ad-editor" method="POST" action="{{ route('admin.ads.store') }}">@csrf<h2>Create Ad Slot</h2><label class="field"><span>Title</span><input name="title" required data-ad-field="title"></label><label class="field"><span>Description</span><textarea name="description" rows="2" data-ad-field="description"></textarea></label><label class="field"><span>Slot</span><input name="slot_name" required data-ad-field="slot"></label><label class="field"><span>Destination</span><input name="destination" type="url" required data-ad-field="link"></label><label class="field"><span>Image URL</span><input name="image_url" type="url" data-ad-field="image"></label><label class="field"><span>Starts</span><input name="starts_at" type="datetime-local" data-ad-field="starts"></label><label class="field"><span>Ends</span><input name="end_date" type="datetime-local" data-ad-field="ends"></label><aside class="campaign-card campaign-tone-0 ad-live-preview"><div class="campaign-creative" data-preview-creative><span class="campaign-kicker" data-preview-slot>Slot</span><h3 data-preview-title>Ad title</h3><p data-preview-copy>Campaign creative</p><span class="status-pill" data-preview-status>Live</span></div><div class="campaign-meta"><span data-preview-dates>Now → Open</span><span data-preview-link>Link</span></div></aside><button class="ui-button primary" type="submit">Save slot</button></form></dialog>
</div>
@endsection
