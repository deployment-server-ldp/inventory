@extends('layouts.app')
@section('title', $role->exists ? 'Edit role' : 'New role')
@section('content')
<x-page-header :title="$role->exists ? 'Edit role: '.$role->display_name : 'New role'" :crumbs="['Roles' => route('admin.roles.index'), ($role->exists ? 'Edit' : 'New') => null]" />
@php($isSuper = $role->name === \App\Models\Role::SUPER_ADMIN)
<form method="post" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
    @csrf @if($role->exists) @method('PUT') @endif
    <div class="card mb-3"><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Role name <span class="req">*</span></label><input name="display_name" value="{{ old('display_name', $role->display_name) }}" class="form-control" required maxlength="100"></div>
        <div class="col-md-8"><label class="form-label">Description</label><input name="description" value="{{ old('description', $role->description) }}" class="form-control" maxlength="255"></div>
    </div></div>
    @if($isSuper)<div class="alert alert-info">The Super Admin role always has every permission.</div>@endif
    <div class="row g-3">
        @php($sel = old('permission_ids', $selected))
        @foreach($groups as $group => $perms)
            <div class="col-md-6 col-xl-4"><div class="perm-group bg-white"><div class="d-flex justify-content-between mb-2"><span class="fw-semibold">{{ $group }}</span>
                <a href="#" class="small" onclick="this.closest('.perm-group').querySelectorAll('input').forEach(i=>i.checked=true);return false;">all</a></div>
                @foreach($perms as $p)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="permission_ids[]" value="{{ $p->id }}" id="p{{ $p->id }}" @checked($isSuper || in_array($p->id, $sel)) @disabled($isSuper)>
                        <label class="form-check-label small" for="p{{ $p->id }}">{{ $p->display_name }} <code class="text-muted" style="font-size:.7rem">{{ $p->name }}</code></label></div>
                @endforeach
            </div></div>
        @endforeach
    </div>
    <div class="d-flex gap-2 mt-3"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save role</button><a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
</form>
@endsection
