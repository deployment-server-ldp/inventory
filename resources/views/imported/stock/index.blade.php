@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Imported stock')
@section('content')
<x-page-header title="Imported stock" subtitle="Available quantities of imported spare parts (separate from CNC stock).">
    @can('imported.stock.adjust')<a href="{{ route('adjustments.create', 'imported') }}" class="btn btn-outline-secondary"><i class="bi bi-sliders me-1"></i>Adjust</a>@endcan
    <a href="{{ route('imported.stock.ledger') }}" class="btn btn-outline-primary"><i class="bi bi-journal-text me-1"></i>Ledger</a>
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label">Search</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, SKU, part no., brand, category"></div>
    <div class="col-6 col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select form-select-sm" data-autosubmit><option value="">All</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Stock</label><select name="stock" class="form-select form-select-sm" data-autosubmit><option value="">All</option>
        <option value="in" @selected(request('stock') === 'in')>In stock</option><option value="low" @selected(request('stock') === 'low')>Low</option><option value="out" @selected(request('stock') === 'out')>Out of stock</option></select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div>
</form>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><x-kpi label="Products listed" :value="number_format($totals->n)" icon="bi-tags" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Units in stock" :value="$Q::fmt($totals->units)" icon="bi-box-seam" /></div>
    <div class="col-12 col-md-6"><x-kpi label="Stock value (priced items only)" :value="$values->isEmpty() ? '—' : $values->map(fn ($v, $c) => $Q::money($v, $c ?: '?'))->implode(' · ')" icon="bi-cash-stack" variant="muted" /></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><x-sort-th col="name" label="Product" /><th>Category</th><th>Brand / part no.</th><th>Supplier</th><x-sort-th col="stock" label="Available" class="num" /><x-sort-th col="min" label="Min" class="num" /><th class="num">Value</th><th>Status</th><th></th></tr></thead>
    <tbody>@forelse($parts as $p)
        <tr><td><x-part-cell :part="$p" /></td><td>{{ $p->category?->name }}</td><td class="small">{{ $p->brand }} {{ $p->part_number ? '· '.$p->part_number : '' }}</td><td class="small">{{ $p->supplier?->name }}</td>
            <td class="num fw-bold">{{ $Q::fmt($p->current_stock) }} <small class="text-muted">{{ $p->unit?->symbol }}</small></td><td class="num">{{ $Q::fmt($p->min_stock) }}</td>
            <td class="num small">{{ $p->unit_cost !== null ? $Q::money($p->unit_cost * $p->current_stock, $p->currency) : '—' }}</td><td><x-stock-badge :part="$p" /></td>
            <td class="text-end text-nowrap"><a href="{{ route('imported.stock.ledger', ['part_id' => $p->id, 'period' => 'year']) }}" class="btn btn-sm btn-outline-secondary" title="Ledger"><i class="bi bi-journal-text"></i></a>
                @can('imported.in.create')<a href="{{ route('imported.in.create', ['part' => $p->id]) }}" class="btn btn-sm btn-outline-success" title="IN"><i class="bi bi-box-arrow-in-down"></i></a>@endcan
                @can('imported.out.create')@if((float) $p->current_stock > 0)<a href="{{ route('imported.out.create', ['part' => $p->id]) }}" class="btn btn-sm btn-outline-danger" title="OUT"><i class="bi bi-box-arrow-up"></i></a>@endif @endcan</td></tr>
    @empty <x-empty colspan="9" message="No products found." /> @endforelse</tbody>
</table></div><div class="card-footer bg-white">{{ $parts->links() }}</div></div>
@endsection
