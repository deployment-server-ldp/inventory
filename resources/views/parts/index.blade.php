@extends('layouts.app')
@php($isCnc = $type === 'cnc')
@php($base = $isCnc ? 'cnc.parts' : 'imported.products')
@section('title', $isCnc ? 'CNC Parts Master' : 'Imported Products Master')
@section('content')
<x-page-header :title="$isCnc ? 'CNC Parts Master' : 'Imported Products Master'"
    :subtitle="$isCnc ? 'Locally manufactured spare parts. Stock shown is finished, QC-accepted stock only.' : 'Spare parts imported from Dubai and other suppliers.'">
    @if($canManage)<a href="{{ route($base.'.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New {{ $isCnc ? 'part' : 'product' }}</a>@endif
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label">Search</label>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, SKU, part no., brand, category…"></div>
    <div class="col-6 col-md-2"><label class="form-label">Category</label>
        <select name="category_id" class="form-select form-select-sm" data-autosubmit><option value="">All</option>
            @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Stock</label>
        <select name="stock" class="form-select form-select-sm" data-autosubmit><option value="">All</option>
            <option value="ok" @selected(request('stock') === 'ok')>In stock</option>
            <option value="low" @selected(request('stock') === 'low')>Low stock</option>
            <option value="out" @selected(request('stock') === 'out')>Out of stock</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label">Status</label>
        <select name="status" class="form-select form-select-sm" data-autosubmit>
            <option value="active" @selected(request('status', 'active') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            <option value="all" @selected(request('status') === 'all')>All</option></select></div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        <a href="{{ route($base.'.index') }}" class="btn btn-sm btn-link">Reset</a></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr>
                <x-sort-th col="name" label="Part" />
                <th>Category</th>
                <th>{{ $isCnc ? 'Specification' : 'Brand / Part no.' }}</th>
                <x-sort-th col="stock" label="Current stock" class="num" />
                <x-sort-th col="min" label="Min level" class="num" />
                <th>Status</th>
                <th></th>
            </tr></thead>
            <tbody>
            @forelse($parts as $p)
                <tr>
                    <td><x-part-cell :part="$p" :href="route($base.'.show', $p)" /></td>
                    <td>{{ $p->category?->name }}</td>
                    <td class="small">{{ $isCnc ? ($p->specification ?: '—') : (trim(($p->brand ?? '').' '.($p->part_number ? '· '.$p->part_number : '')) ?: '—') }}</td>
                    <td class="num fw-semibold">{{ \App\Support\Qty::fmt($p->current_stock) }} <small class="text-muted">{{ $p->unit?->symbol }}</small></td>
                    <td class="num">{{ \App\Support\Qty::fmt($p->min_stock) }}</td>
                    <td><x-stock-badge :part="$p" /> @unless($p->is_active)<span class="badge badge-soft-secondary">Inactive</span>@endunless</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route($base.'.show', $p) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        @if($canManage)<a href="{{ route($base.'.edit', $p) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>@endif
                    </td>
                </tr>
            @empty
                <x-empty colspan="7" icon="bi-nut" message="No parts found." />
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">{{ $parts->total() }} record(s)</small>{{ $parts->links() }}
    </div>
</div>
@endsection
