@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Imported transactions')
@section('content')
<x-page-header title="Imported inventory transactions" :subtitle="$range->label()">
    @if(auth()->user()->can('reports.imported'))<x-export-buttons route="reports.export" :params="['report' => request('type') === 'out' ? 'imported-out-daily' : 'imported-in-daily']" />@endif
    @can('imported.in.create')<a href="{{ route('imported.in.create') }}" class="btn btn-success"><i class="bi bi-box-arrow-in-down me-1"></i>IN</a>@endcan
    @can('imported.out.create')<a href="{{ route('imported.out.create') }}" class="btn btn-danger"><i class="bi bi-box-arrow-up me-1"></i>OUT</a>@endcan
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get">
    <x-date-filter :range="$range" />
    <div class="col-6 col-md-2"><label class="form-label">Type</label><select name="type" class="form-select form-select-sm"><option value="">All</option>
        @foreach(\App\Models\ImportedInventoryTransaction::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Machinery model</label><select name="machinery_model_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($models as $m)<option value="{{ $m->id }}" @selected(request('machinery_model_id') == $m->id)>{{ $m->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Assembly</label><select name="machine_assembly_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($assemblies as $a)<option value="{{ $a->id }}" @selected(request('machine_assembly_id') == $a->id)>{{ $a->reference_no }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Search</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Ref, part, invoice, collector"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button> <a href="{{ route('imported.transactions.index') }}" class="btn btn-sm btn-link">Reset</a></div>
</form>
<div class="row g-3 mb-3">
    <div class="col-4"><x-kpi label="Transactions" :value="number_format($totals->n)" icon="bi-arrow-left-right" /></div>
    <div class="col-4"><x-kpi label="Total in" :value="$Q::fmt($totals->qin)" icon="bi-plus-circle" variant="success" /></div>
    <div class="col-4"><x-kpi label="Total out" :value="$Q::fmt($totals->qout)" icon="bi-dash-circle" variant="danger" /></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><x-sort-th col="date" label="Date" /><x-sort-th col="ref" label="Reference" /><x-sort-th col="part" label="Product" /><th>Type</th>
        <x-sort-th col="in" label="In" class="num" /><x-sort-th col="out" label="Out" class="num" /><th class="num">Bal. after</th><th>Details</th><th>By</th></tr></thead>
    <tbody>@forelse($transactions as $t)
        <tr class="{{ $t->is_reversed ? 'text-muted' : '' }}">
            <td class="text-nowrap">{{ $t->transaction_date->format('d M Y') }}</td>
            <td class="ref"><a href="{{ route('imported.transactions.show', $t) }}">{{ $t->reference_no }}</a>@if($t->is_reversed) <span class="badge badge-soft-secondary">reversed</span>@endif</td>
            <td><x-part-cell :part="$t->sparePart" :name="$t->part_name" :sku="$t->part_sku" size="thumb-sm" /></td>
            <td><span class="badge badge-soft-{{ $t->typeBadge() }}">{{ $t->typeLabel() }}</span></td>
            <td class="num text-success">{{ (float) $t->quantity_in ? $Q::fmt($t->quantity_in) : '' }}</td>
            <td class="num text-danger">{{ (float) $t->quantity_out ? $Q::fmt($t->quantity_out) : '' }}</td>
            <td class="num">{{ $Q::fmt($t->balance_after) }}</td>
            <td class="small">@if($t->type === 'out'){{ $t->machineryModel?->name ?? $t->purpose }} · {{ $t->collected_by }}@if($t->assembly) · <a href="{{ route('imported.assemblies.show', $t->assembly) }}">{{ $t->assembly->reference_no }}</a>@endif
                @elseif($t->type === 'in'){{ $t->supplier?->name ?? $t->source }} {{ $t->document_reference ? '· '.$t->document_reference : '' }}@else{{ \Illuminate\Support\Str::limit($t->remarks, 50) }}@endif</td>
            <td class="small">{{ $t->creator?->name }}</td>
        </tr>
    @empty <x-empty colspan="9" icon="bi-arrow-left-right" message="No transactions for the selected filters." /> @endforelse</tbody>
</table></div>
<div class="card-footer bg-white d-flex justify-content-between flex-wrap gap-2"><small class="text-muted">{{ $transactions->total() }} record(s)</small>{{ $transactions->links() }}</div></div>
@endsection
