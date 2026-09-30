@extends('layouts.app')
@section('title', 'Users')
@section('content')
<x-page-header title="Users" subtitle="Create accounts, assign roles and extra permissions, reset passwords and deactivate users.">
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>New user</a>
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label">Search</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, username, e-mail"></div>
    <div class="col-6 col-md-3"><label class="form-label">Role</label><select name="role_id" class="form-select form-select-sm" data-autosubmit><option value="">All</option>
        @foreach($roles as $r)<option value="{{ $r->id }}" @selected(request('role_id') == $r->id)>{{ $r->display_name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm" data-autosubmit><option value="">All</option>
        <option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Apply</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><x-sort-th col="name" label="Name" /><x-sort-th col="username" label="Username" /><th>Role</th><th>Status</th><x-sort-th col="last_login" label="Last login" /><th class="text-end">Actions</th></tr></thead>
    <tbody>
    @forelse($users as $u)
        <tr>
            <td><div class="fw-semibold">{{ $u->name }}</div><small class="text-muted">{{ $u->email }}</small></td>
            <td class="ref">{{ $u->username }}</td>
            <td><span class="badge badge-soft-primary">{{ $u->role?->display_name }}</span></td>
            <td><x-status-badge :active="$u->is_active" /> @if($u->must_change_password)<span class="badge badge-soft-warning" title="Must change password">pwd</span>@endif</td>
            <td class="small">{{ $u->last_login_at?->format('d M Y H:i') ?? 'Never' }}</td>
            <td class="text-end text-nowrap">
                <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                <form method="post" action="{{ route('admin.users.reset-password', $u) }}" class="d-inline" data-confirm="Reset password for {{ $u->username }}? A temporary password will be generated.">@csrf
                    <button class="btn btn-sm btn-outline-warning" title="Reset password"><i class="bi bi-key"></i></button></form>
                @if($u->id !== auth()->id())
                <form method="post" action="{{ route('admin.users.toggle', $u) }}" class="d-inline" data-confirm="{{ $u->is_active ? 'Deactivate' : 'Activate' }} {{ $u->username }}?">@csrf
                    <button class="btn btn-sm {{ $u->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" title="{{ $u->is_active ? 'Deactivate' : 'Activate' }}"><i class="bi {{ $u->is_active ? 'bi-person-slash' : 'bi-person-check' }}"></i></button></form>
                @endif
            </td>
        </tr>
    @empty <x-empty colspan="6" message="No users found." /> @endforelse
    </tbody></table></div>
    @if($users->hasPages())<div class="card-footer bg-white">{{ $users->links() }}</div>@endif
</div>
@endsection
