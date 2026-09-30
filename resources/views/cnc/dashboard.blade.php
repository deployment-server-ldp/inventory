@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'CNC Production Dashboard')
@section('content')
<x-page-header title="CNC Production Dashboard" :subtitle="'Live machine status and production for '.$range->label()">
    @can('cnc.production.create')<a href="{{ route('cnc.production.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New production entry</a>@endcan
</x-page-header>

<form class="filter-bar row g-2 align-items-end" method="get">
    <x-date-filter :range="$range" />
    <div class="col-6 col-md-2"><label class="form-label">Machine</label><select name="machine_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($machines as $m)<option value="{{ $m->id }}" @selected(request('machine_id') == $m->id)>{{ $m->code }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Part</label><select name="spare_part_id" class="form-select form-select-sm tom"><option value="">All</option>
        @foreach($parts as $p)<option value="{{ $p->id }}" @selected(request('spare_part_id') == $p->id)>{{ $p->sku }} — {{ $p->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Operator</label><select name="operator_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($operators as $o)<option value="{{ $o->id }}" @selected(request('operator_id') == $o->id)>{{ $o->name }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button> <a href="{{ route('cnc.dashboard') }}" class="btn btn-sm btn-link">Reset</a></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Active machines" :value="$kpi['active']" icon="bi-cpu" :href="route('cnc.machines.index')" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Running now" :value="$kpi['running']" icon="bi-play-circle" variant="success" :href="route('cnc.production.index', ['status' => 'running', 'period' => 'year'])" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Idle machines" :value="$kpi['idle']" icon="bi-pause-circle" variant="muted" :href="route('cnc.machines.index')" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Finished parts today" :value="$Q::fmt($kpi['today_final'])" icon="bi-calendar-day" :sub="'Operation output: '.$Q::fmt($kpi['today_ops'])" tip="Output of each part's final operation today" :href="route('cnc.production.index', ['period' => 'today'] + array_filter(request()->only(['machine_id', 'spare_part_id', 'operator_id'])))" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Finished this month" :value="$Q::fmt($kpi['month_final'])" icon="bi-calendar-month" :href="route('cnc.production.index', ['period' => 'month'])" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Finished year to date" :value="$Q::fmt($kpi['ytd_final'])" icon="bi-calendar3" :href="route('cnc.production.index', ['period' => 'year'])" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Parts in production" :value="$kpi['in_production']" icon="bi-hourglass-split" variant="warning" :href="route('cnc.production.index', ['status' => 'running', 'period' => 'year'])" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Operations completed" :value="number_format($kpi['ops_completed'])" icon="bi-check2-square" :sub="$range->label()" :href="route('cnc.production.index', $filterQs + ['status' => 'completed'])" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Operation output" :value="$Q::fmt($kpi['range_ops'])" icon="bi-layers" variant="muted" tip="Sum of all operations in the period (work done — not stock)" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Final-op output" :value="$Q::fmt($kpi['range_final'])" icon="bi-flag" tip="Finished parts in the selected period" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="QC accepted to stock" :value="$Q::fmt($kpi['accepted_range'])" icon="bi-patch-check" variant="success" :href="route('cnc.completions.index', $range->query() + ['status' => 'approved'])" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Pending QC approvals" :value="$kpi['pending_qc']" icon="bi-clipboard-check" :variant="$kpi['pending_qc'] ? 'warning' : 'muted'" :href="route('cnc.completions.index', ['status' => 'pending', 'period' => 'year'])" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8"><div class="card h-100"><div class="card-header"><span class="card-title">Production trend</span>
        <div class="btn-group btn-group-sm">@foreach(\App\Support\Trend::GRAINS as $g => $label)<a href="{{ request()->fullUrlWithQuery(['grain' => $g]) }}" class="btn {{ $grain === $g ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>@endforeach</div></div>
        <div class="card-body"><div class="chart-box"><canvas data-chart="{{ json_encode(['type' => 'line', 'labels' => $trend['labels'], 'datasets' => [['label' => 'Finished (final operation)', 'data' => $trend['series']['final']], ['label' => 'All operations', 'data' => $trend['series']['ops']]]]) }}"></canvas></div></div></div></div>
    <div class="col-xl-4"><div class="card h-100"><div class="card-header"><span class="card-title">Running now</span><span class="badge badge-soft-success">{{ $running->count() }}</span></div>
        <ul class="list-group list-group-flush" style="max-height:330px;overflow:auto">
            @forelse($running as $r)
                <a href="{{ route('cnc.production.show', $r) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center">
                    <span class="status-dot status-running"></span>
                    <div class="flex-grow-1"><div class="fw-semibold">{{ $r->machine->code }} · {{ $r->part_sku }}</div>
                        <small class="text-muted">{{ $r->operation_name }} · {{ $r->operator->name }} · since {{ substr($r->start_time, 0, 5) }} {{ $r->production_date->isToday() ? '' : $r->production_date->format('d M') }}</small></div>
                </a>
            @empty
                <li class="list-group-item"><x-empty icon="bi-pause-circle" message="No machines are running right now." /></li>
            @endforelse
        </ul></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-6"><div class="card h-100"><div class="card-header"><span class="card-title">Production by machine</span><small class="text-muted">click a bar for machine history</small></div>
        <div class="card-body"><div class="chart-box">
            <canvas data-chart="{{ json_encode(['type' => 'bar', 'labels' => $byMachine->pluck('code'), 'links' => $byMachine->map(fn ($m) => route('cnc.machines.show', [$m->id] + $range->query())), 'datasets' => [['label' => 'Finished (final op)', 'data' => $byMachine->pluck('f')->map(fn ($v) => (float) $v)], ['label' => 'All operations', 'data' => $byMachine->pluck('q')->map(fn ($v) => (float) $v)]]]) }}"></canvas>
        </div>@if($byMachine->isEmpty())<x-empty message="No production in this period." />@endif</div></div></div>
    <div class="col-xl-6"><div class="card h-100"><div class="card-header"><span class="card-title">Production by operator (all operations)</span></div>
        <div class="card-body"><div class="chart-box">
            <canvas data-chart="{{ json_encode(['type' => 'bar', 'horizontal' => true, 'multicolor' => true, 'labels' => $byOperator->pluck('name'), 'links' => $byOperator->map(fn ($o) => route('cnc.production.index', ['operator_id' => $o->id] + $range->query())), 'datasets' => [['label' => 'Quantity', 'data' => $byOperator->pluck('q')->map(fn ($v) => (float) $v)]]]) }}"></canvas>
        </div></div></div></div>
    <div class="col-12"><div class="card"><div class="card-header"><span class="card-title">Production by spare part (top 10)</span>
        <a href="{{ route('cnc.progress') }}" class="small">Operation progress →</a></div>
        <div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>Part</th><th class="num">Finished (final op)</th><th class="num">All operations</th><th></th></tr></thead>
            <tbody>@forelse($byPart as $p)
                <tr><td><span class="ref">{{ $p->part_sku }}</span> — {{ $p->part_name }}</td><td class="num fw-semibold">{{ $Q::fmt($p->f) }}</td><td class="num">{{ $Q::fmt($p->q) }}</td>
                    <td class="text-end"><a href="{{ route('cnc.production.index', ['spare_part_id' => $p->spare_part_id] + $range->query()) }}" class="btn btn-sm btn-link">Entries</a></td></tr>
            @empty <x-empty colspan="4" message="No production in this period." /> @endforelse</tbody>
        </table></div></div></div>
</div>
@endsection
