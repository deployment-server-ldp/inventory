@extends('layouts.app')
@section('title', $def['title'])
@section('content')
<x-page-header :title="$def['title']" :subtitle="$def['help'] ?? 'Add, edit, search and deactivate records. Records are never deleted so history stays intact.'" :crumbs="['Settings' => null, $def['title'] => null]">
    <a href="{{ route('settings.create', $def['key']) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add {{ $def['singular'] }}</a>
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-5"><label class="form-label">Search</label>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search…"></div>
    <div class="col-6 col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select form-select-sm" data-autosubmit>
            <option value="">All</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive / other</option>
        </select></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        <a href="{{ route('settings.index', $def['key']) }}" class="btn btn-sm btn-link">Reset</a></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr>
                @foreach($def['fields'] as $name => $f)
                    @if($f['list'])<x-sort-th :col="$name" :label="$f['label']" />@endif
                @endforeach
                @if($def['toggle'] === 'is_active')<th>Status</th>@endif
                <th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($def['fields'] as $name => $f)
                        @continue(! $f['list'])
                        <td>
                            @if($f['type'] === 'checkbox')
                                {!! $row->{$name} ? '<i class="bi bi-check-lg text-success"></i>' : '<span class="text-muted">—</span>' !!}
                            @elseif($name === 'status')
                                <span class="badge badge-soft-{{ ['active' => 'success', 'maintenance' => 'warning'][$row->status] ?? 'secondary' }}">{{ $f['options'][$row->status] ?? $row->status }}</span>
                            @elseif($f['type'] === 'select')
                                {{ $f['options'][$row->{$name}] ?? $row->{$name} }}
                            @else
                                {{ \Illuminate\Support\Str::limit((string) $row->{$name}, 60) ?: '—' }}
                            @endif
                        </td>
                    @endforeach
                    @if($def['toggle'] === 'is_active')<td><x-status-badge :active="$row->is_active" /></td>@endif
                    <td class="text-end text-nowrap">
                        <a href="{{ route('settings.edit', [$def['key'], $row->id]) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                        @php($isActive = $def['toggle'] === 'status' ? $row->status === 'active' : $row->is_active)
                        <form method="post" action="{{ route('settings.toggle', [$def['key'], $row->id]) }}" class="d-inline" data-confirm="{{ $isActive ? 'Deactivate' : 'Activate' }} this {{ $def['singular'] }}?">
                            @csrf
                            <button class="btn btn-sm {{ $isActive ? 'btn-outline-danger' : 'btn-outline-success' }}" title="{{ $isActive ? 'Deactivate' : 'Activate' }}"><i class="bi {{ $isActive ? 'bi-slash-circle' : 'bi-check-circle' }}"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <x-empty :colspan="count($def['fields']) + 2" :message="'No '.$def['title'].' found.'" />
            @endforelse
            </tbody>
        </table>
    </div>
    @if($rows->hasPages())<div class="card-footer bg-white">{{ $rows->links() }}</div>@endif
</div>
@endsection
