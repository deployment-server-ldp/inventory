@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('content')
<x-page-header :title="$user->exists ? 'Edit '.$user->username : 'New user'" :crumbs="['Users' => route('admin.users.index'), ($user->exists ? 'Edit' : 'New') => null]" />
<form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card"><div class="card-body">
                <div class="mb-3"><label class="form-label">Full name <span class="req">*</span></label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required maxlength="100"></div>
                <div class="mb-3"><label class="form-label">Username <span class="req">*</span></label><input name="username" value="{{ old('username', $user->username) }}" class="form-control" required maxlength="50" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">E-mail</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" maxlength="150"></div>
                <div class="mb-3"><label class="form-label">Role <span class="req">*</span></label>
                    <select name="role_id" class="form-select" required>@foreach($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->display_name }}</option>@endforeach</select>
                    <div class="form-text">The role grants the base permissions; add extras on the right if needed.</div></div>
                @unless($user->exists)
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label">Password <span class="req">*</span></label><input type="password" name="password" class="form-control" required autocomplete="new-password"></div>
                        <div class="col-6"><label class="form-label">Confirm <span class="req">*</span></label><input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
                    </div>
                    <div class="form-check mb-3"><input type="hidden" name="must_change_password" value="0"><input class="form-check-input" type="checkbox" name="must_change_password" value="1" id="mcp" checked><label class="form-check-label" for="mcp">Require password change at first sign-in</label></div>
                @endunless
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="act" @checked(old('is_active', $user->is_active))><label class="form-check-label" for="act">Active</label></div>
            </div></div>
            <div class="d-flex gap-2 mt-3"><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Save user</button><a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </div>
        <div class="col-lg-7">
            <div class="card"><div class="card-header"><span class="card-title">Extra permissions (in addition to the role)</span></div><div class="card-body">
                @php($sel = old('permission_ids', $user->exists ? $user->directPermissions->pluck('id')->all() : []))
                <div class="row g-3">
                @foreach($permissionGroups as $group => $perms)
                    <div class="col-md-6"><div class="perm-group"><div class="fw-semibold mb-2">{{ $group }}</div>
                        @foreach($perms as $p)
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="permission_ids[]" value="{{ $p->id }}" id="p{{ $p->id }}" @checked(in_array($p->id, $sel))>
                                <label class="form-check-label small" for="p{{ $p->id }}">{{ $p->display_name }}</label></div>
                        @endforeach
                    </div></div>
                @endforeach
                </div>
            </div></div>
        </div>
    </div>
</form>
@endsection
