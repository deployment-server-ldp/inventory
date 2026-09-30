@extends('layouts.guest')
@section('title', 'First-time setup')
@section('content')
<div class="alert alert-info small"><i class="bi bi-shield-lock me-1"></i>
    Create the first <strong>Super Admin</strong>. This page works only once, while no users exist, and requires the
    <code>SPIMS_SETUP_TOKEN</code> value from your <code>.env</code> file.
</div>
@unless($tokenConfigured)
    <div class="alert alert-warning small">No setup token is configured. Add <code>SPIMS_SETUP_TOKEN=&lt;long random value&gt;</code> to <code>.env</code>
        (then run <code>php artisan config:clear</code> if configuration is cached), or run <code>php artisan app:create-admin</code> over SSH.</div>
@endunless
<form method="post" action="{{ route('setup') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label">Setup token <span class="req">*</span></label>
        <input type="password" name="setup_token" class="form-control @error('setup_token') is-invalid @enderror" required autocomplete="off">
    </div>
    <div class="mb-3">
        <label class="form-label">Full name <span class="req">*</span></label>
        <input name="name" value="{{ old('name') }}" class="form-control" required maxlength="100">
    </div>
    <div class="row g-3 mb-3">
        <div class="col-sm-6"><label class="form-label">Username <span class="req">*</span></label>
            <input name="username" value="{{ old('username') }}" class="form-control" required maxlength="50" autocomplete="username"></div>
        <div class="col-sm-6"><label class="form-label">E-mail</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control" maxlength="150"></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-sm-6"><label class="form-label">Password <span class="req">*</span></label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password"></div>
        <div class="col-sm-6"><label class="form-label">Confirm <span class="req">*</span></label>
            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
        <div class="col-12 form-text mt-1">Minimum 10 characters with upper- and lower-case letters and a number.</div>
    </div>
    <button class="btn btn-primary w-100" type="submit">Create administrator</button>
</form>
@endsection
