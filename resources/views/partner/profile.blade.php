@extends('layouts.app')
@section('title', 'My profile')
@section('eyebrow', 'Account')
@section('heading', 'My profile')
@section('subtitle', 'Your partner account and assigned work.')
@section('content')
<div class="workspace-page">
    @if (session('status'))<div class="catalog-notice" role="status">{{ session('status') }}</div>@endif
    <section class="surface-card">
        <form method="POST" action="{{ route('partner.profile.update') }}">
            @csrf
            @method('PUT')
            <div class="service-form-grid">
                <label class="field"><span>Name</span><input name="name" value="{{ old('name', $partner->name) }}" required></label>
                <label class="field"><span>Email</span><input value="{{ $partner->email }}" disabled readonly></label>
                <label class="field"><span>Phone</span><input name="phone" value="{{ old('phone', $partner->phone) }}"></label>
                <label class="field"><span>Status</span><input value="{{ str($partner->status)->replace('_', ' ')->title() }}" disabled readonly></label>
                <label class="field"><span>Governorate</span><input value="{{ $partner->governorate->name ?? '—' }}" disabled readonly></label>
                <label class="field"><span>Work zone</span><input value="{{ $partner->partnerAreas->pluck('name')->join(', ') ?: '—' }}" disabled readonly></label>
                <label class="field"><span>Assigned maids</span><input value="{{ $maids }}" disabled readonly></label>
                <label class="field"><span>Completed orders</span><input value="{{ $completedOrders }}" disabled readonly></label>
                <label class="field wide"><span>Permissions</span><input value="{{ collect($partner->settings['permissions'] ?? [])->join(', ') ?: 'No extra permissions stored.' }}" disabled readonly></label>
            </div>
            <div class="form-actions"><button class="ui-button primary" type="submit">Save profile</button></div>
        </form>
    </section>
</div>
@endsection
