@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@php($canManage = auth()->user()->can('imported.assembly.manage'))
@php($canIssue = $canManage && auth()->user()->can('imported.out.create') && $assembly->isOpen())
@section('title', $assembly->reference_no)
@section('content')
<x-page-header :title="$assembly->name" :subtitle="$assembly->reference_no.' · '.($assembly->machineryModel?->name ?? 'No model').($assembly->customer ? ' · '.$assembly->customer : '')" :crumbs="['Assemblies' => route('imported.assemblies.index'), $assembly->reference_no => null]">
    <span class="badge badge-soft-{{ $assembly->statusBadge() }} fs-6 align-self-center">{{ \App\Models\MachineAssembly::STATUSES[$assembly->status] }}</span>
    @if($canManage)<a href="{{ route('imported.assemblies.edit', $assembly) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>@endif
    @if(auth()->user()->can('reports.imported'))<a href="{{ route('reports.show', ['report' => 'assembly-consumption', 'machine_assembly_id' => $assembly->id, 'period' => 'year']) }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-bar-graph me-1"></i>Report</a>@endif
</x-page-header>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-kpi label="Assembly progress" :value="$progress.'%'" icon="bi-bar-chart-steps" sub="Issued vs planned quantities" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Planned parts" :value="$rows->count()" icon="bi-list-ol" :sub="$Q::fmt($rows->sum('planned')).' units planned'" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Issued / consumed" :value="$Q::fmt($rows->sum('actual'))" icon="bi-box-arrow-up" variant="success" :sub="$transactions->where('type', 'out')->count().' OUT transactions'" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Lines short of stock" :value="$rows->where('shortage', '>', 0)->count()" icon="bi-exclamation-triangle" :variant="$rows->where('shortage', '>', 0)->count() ? 'danger' : 'muted'" sub="Remaining need exceeds available stock" /></div>
</div>
<div class="progress mb-3" style="height:10px"><div class="progress-bar bg-success" style="width:{{ $progress }}%"></div></div>

<div class="card mb-3"><div class="card-header"><span class="card-title">Bill of materials &amp; availability</span></div>
<div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Part</th><th class="num">Planned</th><th class="num">Issued (actual)</th><th class="num">Remaining</th><th class="num">In stock</th><th>Availability</th>@if($canManage)<th style="min-width:330px">Actions</th>@endif</tr></thead>
    <tbody>@forelse($rows as $r)
        <tr>
            <td><x-part-cell :part="$r['part']" size="thumb-sm" :meta="$r['item']->remarks" /></td>
            <td class="num">{{ $Q::fmt($r['planned']) }}</td>
            <td class="num fw-semibold text-success">{{ $Q::fmt($r['actual']) }}</td>
            <td class="num">{{ $Q::fmt($r['remaining']) }}</td>
            <td class="num">{{ $Q::fmt($r['available']) }} <small class="text-muted">{{ $r['part']->unit?->symbol }}</small></td>
            <td>@if($r['remaining'] <= 0)<span class="badge badge-soft-success">Fully issued</span>@elseif($r['shortage'] > 0)<span class="badge badge-soft-danger">Short {{ $Q::fmt($r['shortage']) }}</span>@else<span class="badge badge-soft-primary">Available</span>@endif
                @if($r['actual'] > $r['planned'] && $r['planned'] > 0)<span class="badge badge-soft-warning">Over plan</span>@endif</td>
            @if($canManage)<td>
                <div class="d-flex gap-1 flex-wrap">
                    <form method="post" action="{{ route('imported.assemblies.items.update', [$assembly, $r['item']]) }}" class="d-flex gap-1">@csrf @method('PUT')
                        <input type="number" step="any" min="0" name="planned_quantity" value="{{ $r['planned'] }}" class="form-control form-control-sm" style="width:80px" title="Planned qty">
                        <button class="btn btn-sm btn-outline-secondary" title="Update plan"><i class="bi bi-save"></i></button></form>
                    @if($canIssue && $r['available'] > 0)
                        <form method="post" action="{{ route('imported.assemblies.issue', $assembly) }}" class="d-flex gap-1">@csrf <x-idempotency />
                            <input type="hidden" name="spare_part_id" value="{{ $r['part']->id }}"><input type="hidden" name="transaction_date" value="{{ now()->toDateString() }}">
                            <input type="number" step="any" min="0" name="quantity" value="{{ min($r['remaining'], $r['available']) ?: '' }}" class="form-control form-control-sm" style="width:75px" required title="Quantity to issue">
                            <input name="collected_by" class="form-control form-control-sm" style="width:110px" placeholder="Collected by" required>
                            <button class="btn btn-sm btn-danger" title="Issue (creates an OUT transaction)"><i class="bi bi-box-arrow-up"></i></button></form>
                    @endif
                </div></td>@endif
        </tr>
    @empty <x-empty :colspan="$canManage ? 7 : 6" icon="bi-list-ol" message="No parts planned yet." /> @endforelse</tbody>
</table></div>
@if($canManage && $assembly->isOpen())
    <div class="card-footer bg-white">
        <form method="post" action="{{ route('imported.assemblies.items.store', $assembly) }}" class="row g-2 align-items-end" data-part-scope>@csrf
            <div class="col-md-5"><label class="form-label">Add required imported part</label>
                <select name="spare_part_id" required data-part-picker data-url="{{ route('lookup.parts', 'imported') }}" placeholder="Search product…"></select></div>
            <div class="col-md-2"><label class="form-label">Planned qty</label><input type="number" step="any" min="0" name="planned_quantity" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Remarks</label><input name="remarks" class="form-control" maxlength="255"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Add</button></div>
        </form>
    </div>
@endif
</div>

<div class="card"><div class="card-header"><span class="card-title">Issue transactions for this assembly</span>
    @if($canIssue)<a href="{{ route('imported.out.create', ['assembly' => $assembly->id]) }}" class="btn btn-sm btn-outline-danger">Issue via OUT form</a>@endif</div>
<div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Date</th><th>Reference</th><th>Part</th><th>Type</th><th class="num">Qty</th><th>Collected by</th><th>By</th></tr></thead>
    <tbody>@forelse($transactions as $t)
        <tr class="{{ $t->is_reversed ? 'text-muted text-decoration-line-through' : '' }}"><td>{{ $t->transaction_date->format('d M Y') }}</td><td class="ref"><a href="{{ route('imported.transactions.show', $t) }}">{{ $t->reference_no }}</a></td>
            <td><x-part-cell :part="$t->sparePart" :name="$t->part_name" :sku="$t->part_sku" size="thumb-sm" /></td><td>{{ $t->typeLabel() }}</td>
            <td class="num">{{ (float) $t->quantity_out ? '−'.$Q::fmt($t->quantity_out) : '+'.$Q::fmt($t->quantity_in) }}</td><td>{{ $t->collected_by }}</td><td class="small">{{ $t->creator?->name }}</td></tr>
    @empty <x-empty colspan="7" message="No parts issued yet." /> @endforelse</tbody>
</table></div></div>
@endsection
