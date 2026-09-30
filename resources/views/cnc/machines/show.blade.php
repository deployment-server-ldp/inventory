@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', $machine->code.' history')
@section('content')
<x-page-header :title="$machine->label" :subtitle="'Production history · '.$range->label()" :crumbs="['CNC Machines' => route('cnc.machines.index'), $machine->code => null]">
    <span class="badge badge-soft-{{ ['active' => 'success', 'maintenance' => 'warning'][$machine->status] ?? 'secondary' }} fs-6 align-self-center">{{ \App\Models\Machine::STATUSES[$machine->status] }}</span>
    <a href="{{ route('cnc.machines.monthly', $machine) }}" class="btn btn-outline-primary"><i class="bi bi-table me-1"></i>Monthly sheet</a>
    @can('cnc.production.create')@if($machine->status === 'active')<a href="{{ route('cnc.production.create', ['machine_id' => $machine->id]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New entry</a>@endif @endcan
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="get">
    <x-date-filter :range="$range" />
    <div class="col-6 col-md-3"><label class="form-label">Part</label><select name="spare_part_id" class="form-select form-select-sm tom"><option value="">All</option>
        @foreach($parts as $p)<option value="{{ $p->id }}" @selected(request('spare_part_id') == $p->id)>{{ $p->sku }} — {{ $p->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Operator</label><select name="operator_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($operators as $o)<option value="{{ $o->id }}" @selected(request('operator_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Operation</label><select name="operation_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($operations as $o)<option value="{{ $o->id }}" @selected(request('operation_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Search</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Ref / part"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg"><x-kpi label="Produced today" :value="$Q::fmt($stats['today'])" icon="bi-calendar-day" /></div>
    <div class="col-6 col-lg"><x-kpi label="This month" :value="$Q::fmt($stats['month'])" icon="bi-calendar-month" /></div>
    <div class="col-6 col-lg"><x-kpi label="Selected range" :value="$Q::fmt($stats['range'])" icon="bi-funnel" :sub="$stats['entries'].' entries'" /></div>
    <div class="col-6 col-lg"><x-kpi label="Run time" :value="floor($stats['minutes'] / 60).'h '.($stats['minutes'] % 60).'m'" icon="bi-stopwatch" variant="muted" /></div>
    <div class="col-12 col-lg"><x-kpi label="Running now" :value="$running->count()" icon="bi-play-circle" :variant="$running->count() ? 'success' : 'muted'" :sub="$running->pluck('part_sku')->implode(', ') ?: 'Idle'" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-6"><div class="card h-100"><div class="card-header"><span class="card-title">Daily production</span></div><div class="card-body"><div class="chart-box sm">
        <canvas data-chart="{{ json_encode(['type' => 'bar', 'labels' => $daily->keys()->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d M'))->values(), 'datasets' => [['label' => 'Quantity', 'data' => $daily->values()->map(fn ($v) => (float) $v)]]]) }}"></canvas></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-header"><span class="card-title">Operation-wise</span></div>
        <table class="table table-sm mb-0"><thead><tr><th>Operation</th><th class="num">Qty</th><th class="num">Time</th></tr></thead><tbody>
        @forelse($byOperation as $o)<tr><td>{{ $o->operation_name }}</td><td class="num">{{ $Q::fmt($o->q) }}</td><td class="num small">{{ round($o->m / 60, 1) }}h</td></tr>@empty<x-empty colspan="3" message="—" />@endforelse
        </tbody></table></div></div>
    <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-header"><span class="card-title">Operator-wise</span></div>
        <table class="table table-sm mb-0"><thead><tr><th>Operator</th><th class="num">Qty</th><th class="num">Time</th></tr></thead><tbody>
        @forelse($byOperator as $o)<tr><td>{{ $o->name }}</td><td class="num">{{ $Q::fmt($o->q) }}</td><td class="num small">{{ round($o->m / 60, 1) }}h</td></tr>@empty<x-empty colspan="3" message="—" />@endforelse
        </tbody></table></div></div>
    <div class="col-12"><div class="card"><div class="card-header"><span class="card-title">Part-wise</span></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Part</th><th class="num">Entries</th><th class="num">Quantity</th><th class="num">Machine time</th></tr></thead><tbody>
        @forelse($byPart as $p)<tr><td><span class="ref">{{ $p->part_sku }}</span> — {{ $p->part_name }}</td><td class="num">{{ $p->n }}</td><td class="num fw-semibold">{{ $Q::fmt($p->q) }}</td><td class="num">{{ round($p->m / 60, 1) }} h</td></tr>@empty<x-empty colspan="4" message="—" />@endforelse
        </tbody></table></div></div></div>
</div>

<div class="card"><div class="card-header"><span class="card-title">Production records</span></div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Date</th><th>Reference</th><th>Part</th><th>Operation</th><th>Time</th><th class="num">Duration</th><th class="num">Qty</th><th>Operator</th><th>Status</th></tr></thead>
        <tbody>@forelse($records as $r)
            <tr><td class="text-nowrap">{{ $r->production_date->format('d M Y') }}</td><td class="ref"><a href="{{ route('cnc.production.show', $r) }}">{{ $r->reference_no }}</a></td>
                <td><x-part-cell :part="$r->sparePart" :name="$r->part_name" :sku="$r->part_sku" size="thumb-sm" /></td><td>{{ $r->operation_name }}</td>
                <td class="small text-nowrap">{{ substr($r->start_time, 0, 5) }}–{{ $r->end_time ? substr($r->end_time, 0, 5) : '…' }}</td><td class="num small">{{ $r->durationLabel() }}</td>
                <td class="num fw-semibold">{{ $r->status === 'running' ? '—' : $Q::fmt($r->quantity) }}</td><td class="small">{{ $r->operator->name }}</td>
                <td><span class="badge badge-soft-{{ $r->statusBadge() }}">{{ ucfirst($r->status) }}</span></td></tr>
        @empty <x-empty colspan="9" message="No production for this machine in the selected range." /> @endforelse</tbody>
    </table></div>
    @if($records->hasPages())<div class="card-footer bg-white">{{ $records->links() }}</div>@endif
</div>
@endsection
