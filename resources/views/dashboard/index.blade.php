@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@php($k = $kpi)
@section('title', 'Main Dashboard')
@section('content')
<x-page-header title="Main Dashboard" :subtitle="'Overall inventory, CNC production & alerts · '.$range->label()" />

<form class="filter-bar row g-2 align-items-end" method="get">
    <x-date-filter :range="$range" />
    <div class="col-6 col-md-2"><label class="form-label">Inventory</label><select name="inventory_type" class="form-select form-select-sm">
        <option value="">Both</option><option value="cnc" @selected($type === 'cnc')>CNC manufactured</option><option value="imported" @selected($type === 'imported')>Imported</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    @if($machines->isNotEmpty())<div class="col-6 col-md-1"><label class="form-label">Machine</label><select name="machine_id" class="form-select form-select-sm"><option value="">All</option>
        @foreach($machines as $m)<option value="{{ $m->id }}" @selected(request('machine_id') == $m->id)>{{ $m->code }}</option>@endforeach</select></div>@endif
    <div class="col-md-3"><label class="form-label">Part</label><select name="spare_part_id" class="form-select form-select-sm tom"><option value="">All parts</option>
        @foreach($parts as $p)<option value="{{ $p->id }}" @selected(request('spare_part_id') == $p->id)>{{ $p->sku }} — {{ $p->name }} ({{ $p->inventory_type === 'cnc' ? 'CNC' : 'Imp' }})</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button> <a href="{{ route('dashboard') }}" class="btn btn-sm btn-link">Reset</a></div>
</form>

@php($alertsTotal = ($k['cnc_low'] ?? 0) + ($k['cnc_out'] ?? 0) + ($k['imp_low'] ?? 0) + ($k['imp_out'] ?? 0))
@if($alertsTotal)
    <div class="alert alert-warning d-flex flex-wrap gap-3 align-items-center py-2">
        <i class="bi bi-bell-fill"></i><strong>Stock alerts:</strong>
        @if($showCnc)<a href="{{ route('cnc.stock.index', ['stock' => 'low']) }}" class="link-dark">CNC low: {{ $k['cnc_low'] }}</a><a href="{{ route('cnc.stock.index', ['stock' => 'out']) }}" class="link-danger">CNC out: {{ $k['cnc_out'] }}</a>@endif
        @if($showImp)<a href="{{ route('imported.stock.index', ['stock' => 'low']) }}" class="link-dark">Imported low: {{ $k['imp_low'] }}</a><a href="{{ route('imported.stock.index', ['stock' => 'out']) }}" class="link-danger">Imported out: {{ $k['imp_out'] }}</a>@endif
    </div>
@endif

<div class="row g-3 mb-3">
    @if($showCnc)
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="CNC part SKUs" :value="number_format($k['cnc_skus'])" icon="bi-nut" :href="route('cnc.stock.index')" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="CNC stock (units)" :value="$Q::fmt($k['cnc_units'])" icon="bi-boxes" :href="route('cnc.stock.index', ['stock' => 'in'])" tip="Finished, QC-accepted CNC parts" /></div>
    @endif
    @if($showImp)
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Imported SKUs" :value="number_format($k['imp_skus'])" icon="bi-tags" :href="route('imported.stock.index')" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Imported stock (units)" :value="$Q::fmt($k['imp_units'])" icon="bi-box-seam" :href="route('imported.stock.index', ['stock' => 'in'])" /></div>
    @endif
    @if($showCnc && $showImp)
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Combined units*" :value="$Q::fmt($k['cnc_units'] + $k['imp_units'])" icon="bi-intersect" variant="muted" sub="CNC + imported quantities" tip="Plain sum of quantities across different units of measure (pcs, sets, boxes…). Indicative only; balances are always kept separately." /></div>
    @endif
    @if($showCnc)
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="CNC machines running" :value="$k['machines_running'].' / '.$k['machines_active']" icon="bi-cpu" variant="success" sub="running / active" :href="route('cnc.machines.index')" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="CNC production today" :value="$Q::fmt($k['cnc_today'])" icon="bi-gear-wide-connected" sub="finished (final operation)" :href="route('cnc.production.index', ['period' => 'today'])" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="CNC production this month" :value="$Q::fmt($k['cnc_month'])" icon="bi-calendar-month" sub="finished (final operation)" :href="route('cnc.production.index', ['period' => 'month'])" /></div>
    @endif
    @if($showImp)
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Imported IN today" :value="$Q::fmt($k['in_today'])" icon="bi-box-arrow-in-down" variant="success" :href="route('imported.transactions.index', ['type' => 'in', 'period' => 'today'])" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Imported IN this month" :value="$Q::fmt($k['in_month'])" icon="bi-calendar-plus" variant="success" :href="route('imported.transactions.index', ['type' => 'in', 'period' => 'month'])" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Imported OUT today" :value="$Q::fmt($k['out_today'])" icon="bi-box-arrow-up" variant="danger" :href="route('imported.transactions.index', ['type' => 'out', 'period' => 'today'])" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Imported OUT this month" :value="$Q::fmt($k['out_month'])" icon="bi-calendar-minus" variant="danger" :href="route('imported.transactions.index', ['type' => 'out', 'period' => 'month'])" /></div>
    @endif
</div>

<div class="row g-3 mb-3">
    @if($showCnc)
    <div class="col-xl-{{ $showImp ? 6 : 12 }}"><div class="card h-100"><div class="card-header"><span class="card-title">Machine-wise production summary</span><small class="text-muted">click a bar to open the machine</small></div>
        <div class="card-body"><div class="chart-box sm"><canvas data-chart="{{ json_encode(['type' => 'bar', 'labels' => $byMachine->pluck('code'), 'links' => $byMachine->map(fn ($m) => route('cnc.machines.show', [$m->id] + $range->query())),
            'datasets' => [['label' => 'Finished (final op)', 'data' => $byMachine->pluck('f')->map(fn ($v) => (float) $v)], ['label' => 'All operations', 'data' => $byMachine->pluck('q')->map(fn ($v) => (float) $v)]]]) }}"></canvas></div>
            @if($byMachine->isEmpty())<x-empty message="No production in this period." />@endif</div></div></div>
    @endif
    @if($showImp)
    <div class="col-xl-{{ $showCnc ? 6 : 12 }}"><div class="card h-100"><div class="card-header"><span class="card-title">Imported parts consumption by machine</span></div>
        <div class="card-body"><div class="chart-box sm"><canvas data-chart="{{ json_encode(['type' => 'bar', 'horizontal' => true, 'multicolor' => true, 'labels' => $consumptionByModel->pluck('name'),
            'links' => $consumptionByModel->map(fn ($m) => $m->id ? route('imported.transactions.index', ['type' => 'out', 'machinery_model_id' => $m->id] + $range->query()) : null),
            'datasets' => [['label' => 'Qty issued', 'data' => $consumptionByModel->pluck('q')->map(fn ($v) => (float) $v)]]]) }}"></canvas></div>
            @if($consumptionByModel->isEmpty())<x-empty message="No issues in this period." />@endif</div></div></div>
    @endif
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-6"><div class="card h-100"><div class="card-header"><span class="card-title">Category-wise inventory (units)</span></div>
        <div class="card-body"><div class="chart-box sm"><canvas data-chart="{{ json_encode(['type' => 'bar', 'stacked' => true, 'labels' => $categoryChart['labels'], 'datasets' => array_values(array_filter([
            $showCnc ? ['label' => 'CNC', 'data' => $categoryChart['cnc']] : null, $showImp ? ['label' => 'Imported', 'data' => $categoryChart['imported']] : null]))]) }}"></canvas></div></div></div></div>
    @if($showCnc)
    <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-header"><span class="card-title">Top manufactured parts</span></div>
        <ul class="list-group list-group-flush">@forelse($topManufactured as $t)
            <a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('cnc.production.index', ['spare_part_id' => $t->spare_part_id] + $range->query()) }}"><span class="text-truncate"><span class="ref">{{ $t->part_sku }}</span> {{ $t->part_name }}</span><strong>{{ $Q::fmt($t->q) }}</strong></a>
        @empty <li class="list-group-item"><x-empty message="No finished output in this period." /></li> @endforelse</ul></div></div>
    @endif
    @if($showImp)
    <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-header"><span class="card-title">Most issued imported parts</span></div>
        <ul class="list-group list-group-flush">@forelse($mostIssued as $t)
            <a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('imported.transactions.index', ['type' => 'out', 'spare_part_id' => $t->spare_part_id] + $range->query()) }}"><span class="text-truncate"><span class="ref">{{ $t->part_sku }}</span> {{ $t->part_name }}</span><strong>{{ $Q::fmt($t->q) }}</strong></a>
        @empty <li class="list-group-item"><x-empty message="No issues in this period." /></li> @endforelse</ul></div></div>
    @endif
</div>

<div class="row g-3 mb-3">
    @if($showCnc)
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><span class="card-title text-danger"><i class="bi bi-bell me-1"></i>CNC stock alerts</span><a href="{{ route('reports.show', ['report' => 'low-stock', 'inventory_type' => 'cnc']) }}" class="small">Report</a></div>
        <ul class="list-group list-group-flush alert-list">@forelse($cncAlerts as $p)
            <li class="list-group-item justify-content-between"><x-part-cell :part="$p" size="thumb-sm" /><span class="text-end"><x-stock-badge :part="$p" /><br><small>{{ $Q::fmt($p->current_stock) }} / min {{ $Q::fmt($p->min_stock) }}</small></span></li>
        @empty <li class="list-group-item"><x-empty icon="bi-check2-circle" message="No CNC stock alerts." /></li> @endforelse</ul></div></div>
    @endif
    @if($showImp)
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><span class="card-title text-danger"><i class="bi bi-bell me-1"></i>Imported stock alerts</span><a href="{{ route('reports.show', ['report' => 'low-stock', 'inventory_type' => 'imported']) }}" class="small">Report</a></div>
        <ul class="list-group list-group-flush alert-list">@forelse($impAlerts as $p)
            <li class="list-group-item justify-content-between"><x-part-cell :part="$p" size="thumb-sm" /><span class="text-end"><x-stock-badge :part="$p" /><br><small>{{ $Q::fmt($p->current_stock) }} / min {{ $Q::fmt($p->min_stock) }}</small></span></li>
        @empty <li class="list-group-item"><x-empty icon="bi-check2-circle" message="No imported stock alerts." /></li> @endforelse</ul></div></div>
    @endif
</div>

<div class="row g-3">
    <div class="col-xl-{{ $showCnc ? 7 : 12 }}"><div class="card h-100"><div class="card-header"><span class="card-title">Recent stock movements</span></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>When</th><th>Inventory</th><th>Reference</th><th>Part</th><th>Type</th><th class="num">Qty</th><th class="num">Balance</th></tr></thead><tbody>
        @forelse($recentMoves as $t)
            <tr><td class="small text-nowrap">{{ $t->created_at->format('d M H:i') }}</td>
                <td><span class="badge {{ $t->stream === 'cnc' ? 'badge-soft-info' : 'badge-soft-primary' }}">{{ $t->stream === 'cnc' ? 'CNC' : 'Imported' }}</span></td>
                <td class="ref">@if($t->stream === 'imported')<a href="{{ route('imported.transactions.show', $t) }}">{{ $t->reference_no }}</a>@else<a href="{{ route('cnc.stock.ledger', ['part_id' => $t->spare_part_id, 'period' => 'year']) }}">{{ $t->reference_no }}</a>@endif</td>
                <td><x-part-cell :part="$t->sparePart" :name="$t->part_name" :sku="$t->part_sku" size="thumb-sm" /></td><td class="small">{{ $t->typeLabel() }}</td>
                <td class="num {{ (float) $t->quantity_in ? 'text-success' : 'text-danger' }}">{{ (float) $t->quantity_in ? '+'.$Q::fmt($t->quantity_in) : '−'.$Q::fmt($t->quantity_out) }}</td><td class="num">{{ $Q::fmt($t->balance_after) }}</td></tr>
        @empty <x-empty colspan="7" message="No stock movements yet." /> @endforelse</tbody></table></div></div></div>
    @if($showCnc)
    <div class="col-xl-5"><div class="card h-100"><div class="card-header"><span class="card-title">Recent production activities</span><a href="{{ route('cnc.production.index') }}" class="small">All</a></div>
        <ul class="list-group list-group-flush">@forelse($recentProduction as $r)
            <a href="{{ route('cnc.production.show', $r) }}" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between"><span><strong>{{ $r->machine->code }}</strong> · {{ $r->part_sku }} · {{ $r->operation_name }}</span><span class="badge badge-soft-{{ $r->statusBadge() }}">{{ $r->status === 'running' ? 'running' : $Q::fmt($r->quantity) }}</span></div>
                <small class="text-muted">{{ $r->production_date->format('d M') }} {{ substr($r->start_time, 0, 5) }} · {{ $r->operator->name }}</small></a>
        @empty <li class="list-group-item"><x-empty message="No production recorded yet." /></li> @endforelse</ul></div></div>
    @endif
</div>
@endsection
