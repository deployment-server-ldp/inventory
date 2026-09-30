@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<form method="post" action="{{ url('/login') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label class="form-label" for="username">Username or e-mail</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input id="username" name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" required autofocus autocomplete="username">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
    </div>
    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
        <label class="form-check-label" for="remember">Keep me signed in on this device</label>
    </div>
    <button class="btn btn-primary w-100 py-2" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
    <p class="text-muted small text-center mt-4 mb-0">Forgot your password? Ask your system administrator to reset it.</p>
</form>
@endsection
