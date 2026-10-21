@extends('layouts.app')
@section('title', 'Ads History')
@section('eyebrow', 'Campaign archive')
@section('heading', 'Ads History')
@section('subtitle', 'Published ads, their date range, and tracked impressions, clicks, and CTR.')
@section('page-actions')
    <a class="ui-button ghost" href="{{ route('admin.ads.index') }}">Back to Slider Ads</a>
@endsection
@section('content')
@php($published = $ads->count())
@php($impressions = (int) $ads->sum('impressions'))
@php($clicks = (int) $ads->sum('clicks'))
<section class="overview-metrics">
    <article class="metric-card"><span>Published ads</span><strong>{{ $published }}</strong></article>
    <article class="metric-card"><span>Impressions</span><strong>{{ number_format($impressions) }}</strong></article>
    <article class="metric-card"><span>Clicks</span><strong>{{ number_format($clicks) }}</strong></article>
    <article class="metric-card"><span>CTR</span><strong>{{ $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0 }}%</strong></article>
</section>
<div class="workspace-page">
    <section class="surface-card"><div class="table-wrap"><table class="data-table"><thead><tr><th>Ad</th><th>Slot</th><th>Status</th><th>Range</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Destination</th></tr></thead><tbody>
        @forelse ($ads as $ad)
            @php($ctr = $ad->impressions > 0 ? round(($ad->clicks / $ad->impressions) * 100, 2) : 0)
            <tr>
                <td>{{ $ad->title }}</td>
                <td>{{ $ad->slot_name }}</td>
                <td>{{ $ad->status }}</td>
                <td>{{ $ad->starts_at?->format('d M Y') ?? $ad->created_at?->format('d M Y') }} → {{ $ad->end_date?->format('d M Y') ?? 'Open' }}</td>
                <td>{{ $ad->impressions }}</td>
                <td>{{ $ad->clicks }}</td>
                <td>{{ $ctr }}%</td>
                <td><a href="{{ route('ads.click', $ad) }}">{{ $ad->destination }}</a></td>
            </tr>
        @empty
            <tr><td colspan="8">No ads yet. Metrics stay at zero until an ad is rendered or clicked.</td></tr>
        @endforelse
    </tbody></table></div></section>
</div>
@endsection
