@extends('layouts.app')
@section('title', 'Roles & permissions')
@section('content')
<x-page-header title="Roles & permissions" subtitle="Permissions are enforced on the server for every page and action.">
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New role</a>
</x-page-header>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Role</th><th>Description</th><th class="num">Permissions</th><th class="num">Users</th><th></th></tr></thead>
    <tbody>@foreach($roles as $r)
        <tr><td class="fw-semibold">{{ $r->display_name }} @if($r->is_system)<span class="badge badge-soft-secondary">system</span>@endif</td>
            <td class="small">{{ $r->description }}</td>
            <td class="num">{{ $r->name === \App\Models\Role::SUPER_ADMIN ? 'All' : $r->permissions_count }}</td>
            <td class="num">{{ $r->users_count }}</td>
            <td class="text-end"><a href="{{ route('admin.roles.edit', $r) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a></td></tr>
    @endforeach</tbody></table></div></div>
@endsection
