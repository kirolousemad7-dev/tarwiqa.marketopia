@extends('layouts.app')
@section('title', 'Custom Packages')
@section('eyebrow', 'Catalog management')
@section('heading', 'Custom Packages')
@section('subtitle', 'Configure package pricing, discounts, availability windows, and publication status.')
@section('content')
<div class="workspace-page catalog-admin-page"><div class="page-actions"><a class="ui-button accent" href="{{ route('admin.catalog.create', 'packages') }}">Add Package</a></div>@if(session('status'))<div class="catalog-notice" role="status">{{ session('status') }}</div>@endif<section class="surface-card"><div class="card-head catalog-head"><div><p class="eyebrow">Package directory</p><h2>Available Packages</h2></div><div class="catalog-filters" data-table-filters><input data-table-search type="search" placeholder="Search title or slug"><button class="ui-button ghost" data-table-reset type="button">Reset</button></div></div><div class="data-table-shell"><table data-yajra-table data-source="{{ route('admin.catalog.data', 'packages') }}" data-kind="packages"><thead><tr><th>Title</th><th>Price</th><th>Discount</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr><td colspan="5" class="empty-table">Loading packages…</td></tr></tbody></table></div><div class="yajra-table-footer" data-table-footer></div></section>
@php($builder = app(\App\Http\Controllers\AdminParityController::class)->builderContext())
<section class="surface-card">
    <div class="card-head"><div><p class="eyebrow">Governorate setup</p><h2>Six governorate custom packages</h2></div><a class="ui-button ghost" href="{{ route('admin.custom-packages.export') }}">Export Setup</a></div>
    <form method="POST" action="{{ route('admin.custom-packages.save') }}">
        @csrf
        @foreach ($builder['governorates'] as $governorate)
            <fieldset>
                <legend>{{ $governorate->name }}</legend>
                <input type="hidden" name="rows[{{ $loop->index }}][governorate_id]" value="{{ $governorate->id }}">
                <label>Package<select name="rows[{{ $loop->index }}][package_id]"><option value="">None</option>@foreach ($builder['packages'] as $package)<option value="{{ $package->id }}" @selected($package->governorate_id === $governorate->id)>{{ $package->title }}</option>@endforeach</select></label>
                <label>Price<input name="rows[{{ $loop->index }}][price]" type="number" min="0" step="0.01" value="{{ $builder['packages']->firstWhere('governorate_id', $governorate->id)?->price }}"></label>
                <label><input type="checkbox" name="rows[{{ $loop->index }}][is_active]" value="1" checked> Active</label>
            </fieldset>
        @endforeach
        <button class="ui-button primary" type="submit">Save All</button>
    </form>
</section>
</div>
@endsection
