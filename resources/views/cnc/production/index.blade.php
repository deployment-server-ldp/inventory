@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Production entries')
@section('content')
<x-page-header title="CNC production entries" :subtitle="$range->label().' · operation-level work records (not stock)'">
    @if(auth()->user()->can('reports.cnc'))<x-export-buttons route="reports.export" :params="['report' => 'cnc-production-daily']" />@endif
    @can('cnc.production.create')<a href="{{ route('cnc.production.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New entry</a>@endcan
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="get">
    <x-date-filter :range="$range" />
    <div class="col-6 col-md-2"><label class="form-label">Machine</label><select name="machine_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($machines as $m)<option value="{{ $m->id }}" @selected(request('machine_id') == $m->id)>{{ $m->code }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3"><label class="form-label">Part</label><select name="spare_part_id" class="form-select form-select-sm tom"><option value="">All parts</option>
        @foreach($parts as $p)<option value="{{ $p->id }}" @selected(request('spare_part_id') == $p->id)>{{ $p->sku }} — {{ $p->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Operator</label><select name="operator_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($operators as $o)<option value="{{ $o->id }}" @selected(request('operator_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Operation</label><select name="operation_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($operations as $o)<option value="{{ $o->id }}" @selected(request('operation_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm"><option value="">Running + completed</option>
        @foreach(\App\Models\CncProductionRecord::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
        <option value="all" @selected(request('status') === 'all')>All incl. cancelled</option></select></div>
    <div class="col-md-3"><label class="form-label">Reference / part</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button> <a href="{{ route('cnc.production.index') }}" class="btn btn-sm btn-link">Reset</a></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-4"><x-kpi label="Entries" :value="number_format($totals->entries)" icon="bi-list-check" /></div>
    <div class="col-4"><x-kpi label="Operation quantity" :value="$Q::fmt($totals->qty)" icon="bi-layers" tip="Sum of all operation quantities in this list (not stock)" /></div>
    <div class="col-4"><x-kpi label="Machine time" :value="floor($totals->minutes / 60).'h '.($totals->minutes % 60).'m'" icon="bi-stopwatch" variant="muted" /></div>
</div>

<div class="card">
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr>
            <x-sort-th col="date" label="Date" /><x-sort-th col="ref" label="Reference" /><x-sort-th col="machine" label="Machine" /><x-sort-th col="part" label="Part" />
            <th>Operation</th><th>Target model</th><th>Time</th><x-sort-th col="duration" label="Duration" class="num" /><x-sort-th col="qty" label="Qty" class="num" /><th>Operator</th><th>Status</th>
        </tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr class="{{ $r->status === 'cancelled' ? 'text-muted text-decoration-line-through' : '' }}">
                <td class="text-nowrap">{{ $r->production_date->format('d M Y') }}</td>
                <td class="ref"><a href="{{ route('cnc.production.show', $r) }}">{{ $r->reference_no }}</a></td>
                <td><a href="{{ route('cnc.machines.show', $r->machine_id) }}">{{ $r->machine->code }}</a></td>
                <td><x-part-cell :part="$r->sparePart" :name="$r->part_name" :sku="$r->part_sku" size="thumb-sm" /></td>
                <td>{{ $r->operation_name }} @if($r->is_final_operation)<span class="badge badge-soft-primary" title="Final operation — counts toward completion">final</span>@endif</td>
                <td class="small">{{ $r->machineryModel?->name ?? '—' }}</td>
                <td class="text-nowrap small">{{ substr($r->start_time, 0, 5) }}–{{ $r->end_time ? substr($r->end_time, 0, 5) : '…' }}</td>
                <td class="num small">{{ $r->durationLabel() }}</td>
                <td class="num fw-semibold">{{ $r->status === 'running' ? '—' : $Q::fmt($r->quantity) }}</td>
                <td class="small">{{ $r->operator->name }}</td>
                <td><span class="badge badge-soft-{{ $r->statusBadge() }}">{{ \App\Models\CncProductionRecord::STATUSES[$r->status] }}</span></td>
            </tr>
        @empty
            <x-empty colspan="11" icon="bi-gear" message="No production entries for the selected filters." />
        @endforelse
        </tbody>
    </table></div>
    <div class="card-footer bg-white d-flex justify-content-between flex-wrap gap-2"><small class="text-muted">{{ $records->total() }} record(s)</small>{{ $records->links() }}</div>
</div>
@endsection
