@extends('layouts.app')
@section('title', 'Security')
@section('eyebrow', 'Account')
@section('heading', 'Security')
@section('subtitle', 'Update the password for your supporter account.')
@section('content')
<div class="workspace-page">
    @if (session('status'))<div class="catalog-notice" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="catalog-notice danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="surface-card">
        <p>Signed in as {{ $supporter->name }} · {{ $supporter->email }}</p>
        <p>Stored passwords stay hashed and cannot be shown.</p>
        <form method="POST" action="{{ route('supporter.security.update') }}">
            @csrf
            @method('PUT')
            <div class="service-form-grid">
                <label class="field"><span>Current password</span><input name="current_password" type="password" required autocomplete="current-password"></label>
                <label class="field"><span>New password</span><input name="password" type="password" required autocomplete="new-password"></label>
                <label class="field"><span>Confirm new password</span><input name="password_confirmation" type="password" required autocomplete="new-password"></label>
            </div>
            <div class="form-actions"><button class="ui-button primary" type="submit">Update password</button></div>
        </form>
    </section>
</div>
@endsection
