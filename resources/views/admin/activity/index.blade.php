@extends('layouts.app')
@section('title', 'Activity log')
@section('content')
<x-page-header title="Activity log" subtitle="Logins, production, stock movements, adjustments, reversals, master data and user changes." />
<form class="filter-bar row g-2 align-items-end" method="get">
    <x-date-filter :range="$range" />
    <div class="col-6 col-md-2"><label class="form-label">User</label><select name="user_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Module</label><select name="module" class="form-select form-select-sm"><option value="">All</option>
        @foreach($modules as $m)<option @selected(request('module') === $m)>{{ $m }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Search</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Description or reference"></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Apply</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover">
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th><th></th></tr></thead>
    <tbody>@forelse($logs as $l)
        <tr><td class="text-nowrap small">{{ $l->created_at->format('d M Y H:i:s') }}</td><td>{{ $l->user?->name ?? $l->user_name ?? 'system' }}</td>
            <td><code class="small">{{ $l->action }}</code></td><td class="small">{{ $l->module }}</td><td class="small">{{ $l->description }}</td>
            <td class="small text-muted">{{ $l->ip_address }}</td><td><a href="{{ route('admin.activity.show', $l) }}" class="btn btn-sm btn-link">Details</a></td></tr>
    @empty <x-empty colspan="7" message="No activity for the selected filters." /> @endforelse</tbody>
</table></div>
@if($logs->hasPages())<div class="card-footer bg-white">{{ $logs->links() }}</div>@endif</div>
@endsection
