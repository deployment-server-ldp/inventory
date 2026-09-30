@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Completions / QC')
@section('content')
<x-page-header title="Production completions &amp; quality approval" subtitle="Approved completions are the only way CNC production enters finished stock.">
    @can('cnc.completion.create')<a href="{{ route('cnc.completions.create') }}" class="btn btn-primary"><i class="bi bi-patch-check me-1"></i>Record completion</a>@endcan
</x-page-header>
@if($pendingCount)
    <div class="alert alert-warning d-flex justify-content-between align-items-center"><span><i class="bi bi-hourglass-split me-1"></i>{{ $pendingCount }} completion(s) awaiting quality approval.</span>
        <a href="{{ route('cnc.completions.index', ['status' => 'pending', 'period' => 'year']) }}" class="btn btn-sm btn-warning">Show pending</a></div>
@endif
<form class="filter-bar row g-2 align-items-end" method="get">
    <x-date-filter :range="$range" />
    <div class="col-6 col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm"><option value="">All</option>
        @foreach(\App\Models\CncProductionCompletion::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Part</label><select name="spare_part_id" class="form-select form-select-sm tom"><option value="">All</option>
        @foreach($parts as $p)<option value="{{ $p->id }}" @selected(request('spare_part_id') == $p->id)>{{ $p->sku }} — {{ $p->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Reference</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><x-sort-th col="date" label="Date" /><x-sort-th col="ref" label="Reference" /><th>Part</th><th class="num">Inspected</th><x-sort-th col="accepted" label="Accepted" class="num" /><th class="num">Rejected</th><th>Status</th><th>Submitted / approved</th></tr></thead>
    <tbody>@forelse($completions as $c)
        <tr><td class="text-nowrap">{{ $c->completion_date->format('d M Y') }}</td><td class="ref"><a href="{{ route('cnc.completions.show', $c) }}">{{ $c->reference_no }}</a></td>
            <td><x-part-cell :part="$c->sparePart" :name="$c->part_name" :sku="$c->part_sku" size="thumb-sm" /></td>
            <td class="num">{{ $Q::fmt($c->quantity_inspected) }}</td><td class="num fw-semibold text-success">{{ $Q::fmt($c->quantity_accepted) }}</td><td class="num text-danger">{{ $Q::fmt($c->quantity_rejected) }}</td>
            <td><span class="badge badge-soft-{{ $c->statusBadge() }}">{{ \App\Models\CncProductionCompletion::STATUSES[$c->status] }}</span></td>
            <td class="small">{{ $c->submitter?->name }}@if($c->approver) / {{ $c->approver->name }}@endif</td></tr>
    @empty <x-empty colspan="8" icon="bi-patch-check" message="No completions for the selected filters." /> @endforelse</tbody>
</table></div>
@if($completions->hasPages())<div class="card-footer bg-white">{{ $completions->links() }}</div>@endif</div>
@endsection
