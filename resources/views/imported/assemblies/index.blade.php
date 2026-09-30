@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Machine assemblies')
@section('content')
<x-page-header title="Machine assemblies" subtitle="Plan imported parts per machine being assembled and track actual consumption.">
    @can('imported.assembly.manage')<a href="{{ route('imported.assemblies.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New assembly</a>@endcan
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label">Search</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, reference, customer"></div>
    <div class="col-6 col-md-3"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm" data-autosubmit><option value="">All</option>
        @foreach(\App\Models\MachineAssembly::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Reference</th><th>Machine / project</th><th>Machinery model</th><th>Status</th><th class="num">Parts</th><th class="num">Planned qty</th><th class="num">Issued qty</th><th style="width:160px">Progress</th><th>Target</th></tr></thead>
    <tbody>@forelse($assemblies as $a)
        @php($pl = (float) ($planned[$a->id] ?? 0)) @php($is = (float) ($issued[$a->id] ?? 0))
        <tr><td class="ref"><a href="{{ route('imported.assemblies.show', $a) }}">{{ $a->reference_no }}</a></td><td class="fw-semibold">{{ $a->name }}<br><small class="text-muted">{{ $a->customer }}</small></td>
            <td>{{ $a->machineryModel?->name ?? '—' }}</td><td><span class="badge badge-soft-{{ $a->statusBadge() }}">{{ \App\Models\MachineAssembly::STATUSES[$a->status] }}</span></td>
            <td class="num">{{ $a->items_count }}</td><td class="num">{{ $Q::fmt($pl) }}</td><td class="num">{{ $Q::fmt($is) }}</td>
            <td>@php($pct = $pl > 0 ? min(100, round($is / $pl * 100)) : 0)<div class="progress"><div class="progress-bar bg-success" style="width:{{ $pct }}%"></div></div><small class="text-muted">{{ $pct }}%</small></td>
            <td class="small">{{ $a->target_date?->format('d M Y') ?? '—' }}</td></tr>
    @empty <x-empty colspan="9" icon="bi-tools" message="No assemblies yet." /> @endforelse</tbody>
</table></div>@if($assemblies->hasPages())<div class="card-footer bg-white">{{ $assemblies->links() }}</div>@endif</div>
@endsection
