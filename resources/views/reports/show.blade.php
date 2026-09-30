@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@php($f = array_flip($report->filters))
@section('title', $report->title)
@section('content')
<x-page-header :title="$report->title" :subtitle="$report->description.($report->usesDates ? ' · '.$range->label() : '')" :crumbs="['Reports' => route('reports.index'), $report->title => null]">
    <x-export-buttons route="reports.export" :params="['report' => $report->key]" />
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get">
    @if($report->usesDates)<x-date-filter :range="$range" />@endif
    @isset($options['machines'])<div class="col-6 col-md-2"><label class="form-label">Machine</label><select name="machine_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($options['machines'] as $m)<option value="{{ $m->id }}" @selected(request('machine_id') == $m->id)>{{ $m->code }}</option>@endforeach</select></div>@endisset
    @isset($options['parts'])<div class="col-md-3"><label class="form-label">Part</label><select name="spare_part_id" class="form-select form-select-sm tom"><option value="">All</option>
        @foreach($options['parts'] as $p)<option value="{{ $p->id }}" @selected(request('spare_part_id') == $p->id)>{{ $p->sku }} — {{ $p->name }}</option>@endforeach</select></div>@endisset
    @isset($options['operators'])<div class="col-6 col-md-2"><label class="form-label">Operator</label><select name="operator_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($options['operators'] as $o)<option value="{{ $o->id }}" @selected(request('operator_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>@endisset
    @isset($options['operations'])<div class="col-6 col-md-2"><label class="form-label">Operation</label><select name="operation_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($options['operations'] as $o)<option value="{{ $o->id }}" @selected(request('operation_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>@endisset
    @isset($options['categories'])<div class="col-6 col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($options['categories'] as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>@endisset
    @isset($options['models'])<div class="col-6 col-md-2"><label class="form-label">Machinery model</label><select name="machinery_model_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($options['models'] as $m)<option value="{{ $m->id }}" @selected(request('machinery_model_id') == $m->id)>{{ $m->name }}</option>@endforeach</select></div>@endisset
    @isset($options['assemblies'])<div class="col-6 col-md-2"><label class="form-label">Assembly</label><select name="machine_assembly_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($options['assemblies'] as $a)<option value="{{ $a->id }}" @selected(request('machine_assembly_id') == $a->id)>{{ $a->reference_no }} — {{ $a->name }}</option>@endforeach</select></div>@endisset
    @isset($options['suppliers'])<div class="col-6 col-md-2"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($options['suppliers'] as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>@endisset
    @isset($f['inventory_type'])<div class="col-6 col-md-2"><label class="form-label">Inventory</label><select name="inventory_type" class="form-select form-select-sm"><option value="">All permitted</option>
        @foreach(\App\Models\SparePart::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('inventory_type') === $k)>{{ $l }}</option>@endforeach</select></div>@endisset
    @isset($f['stock'])<div class="col-6 col-md-2"><label class="form-label">Stock</label><select name="stock" class="form-select form-select-sm"><option value="">All</option>
        <option value="in" @selected(request('stock') === 'in')>In stock</option><option value="low" @selected(request('stock') === 'low')>Low</option><option value="out" @selected(request('stock') === 'out')>Out</option></select></div>@endisset
    @isset($f['stock_alert'])<div class="col-6 col-md-2"><label class="form-label">Alert</label><select name="alert" class="form-select form-select-sm"><option value="">Low + out</option>
        <option value="low" @selected(request('alert') === 'low')>Low only</option><option value="out" @selected(request('alert') === 'out')>Out of stock only</option></select></div>@endisset
    @isset($f['txn_status'])<div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm"><option value="">Running + completed</option>
        @foreach(\App\Models\CncProductionRecord::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>@endisset
    @isset($f['assembly_status'])<div class="col-6 col-md-2"><label class="form-label">Assembly status</label><select name="assembly_status" class="form-select form-select-sm"><option value="">All</option>
        @foreach(\App\Models\MachineAssembly::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('assembly_status') === $k)>{{ $l }}</option>@endforeach</select></div>@endisset
    @if(isset($f['ledger_type_cnc']) || isset($f['ledger_type_imported']))<div class="col-6 col-md-2"><label class="form-label">Type</label><select name="txn_type" class="form-select form-select-sm"><option value="">All</option>
        @foreach(isset($f['ledger_type_cnc']) ? \App\Models\CncInventoryTransaction::TYPES : \App\Models\ImportedInventoryTransaction::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('txn_type') === $k)>{{ $l }}</option>@endforeach</select></div>@endif
    <div class="col-md-2"><label class="form-label">Search</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button> <a href="{{ route('reports.show', $report->key) }}" class="btn btn-sm btn-link">Reset</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover">
    <thead><tr>@foreach($report->columns() as $key => [$label, $type])<x-sort-th :col="$key" :label="$label" :class="$type === 'num' ? 'num' : ''" />@endforeach</tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr>@foreach($report->columns() as $key => [$label, $type])
            @php($v = $row->{$key} ?? null)
            <td class="{{ $type === 'num' ? 'num' : '' }} {{ $type === 'ref' ? 'ref' : '' }}">
                @if($v === null || $v === '')<span class="text-muted">—</span>
                @elseif($type === 'num'){{ $Q::fmt($v) }}
                @elseif($type === 'date'){{ \Carbon\Carbon::parse($v)->format('d M Y') }}
                @else{{ $v }}@endif
            </td>@endforeach</tr>
    @empty
        <x-empty :colspan="count($report->columns())" icon="bi-file-earmark-bar-graph" message="No data for the selected filters." />
    @endforelse
    </tbody>
    @if($totals && $rows->total())
        <tfoot class="fw-bold table-light"><tr>@foreach(array_keys($report->columns()) as $i => $key)
            <td class="{{ array_key_exists($key, $totals) ? 'num' : '' }}">{{ $i === 0 ? 'Total (all pages)' : (array_key_exists($key, $totals) ? $Q::fmt($totals[$key]) : '') }}</td>@endforeach</tr></tfoot>
    @endif
</table></div>
<div class="card-footer bg-white d-flex justify-content-between flex-wrap gap-2"><small class="text-muted">{{ $rows->total() }} row(s)</small>{{ $rows->links() }}</div></div>
@endsection
