@extends('layouts.app')
@section('title', 'Change password')
@section('content')
<x-page-header title="Change password" subtitle="Use at least 8 characters with letters and numbers." />
<div class="card" style="max-width:520px">
    <div class="card-body">
        <form method="post" action="{{ route('password.change.update') }}">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label">Current password</label>
                <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-3"><label class="form-label">New password</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-4"><label class="form-label">Confirm new password</label>
                <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Update password</button>
        </form>
    </div>
</div>
@endsection
