@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Imported Inventory Dashboard')
@section('content')
<x-page-header title="Imported Inventory Dashboard" :subtitle="'Dubai & other imports · charts for '.$range->label()">
    @can('imported.in.create')<a href="{{ route('imported.in.create') }}" class="btn btn-success"><i class="bi bi-box-arrow-in-down me-1"></i>Inventory IN</a>@endcan
    @can('imported.out.create')<a href="{{ route('imported.out.create') }}" class="btn btn-danger"><i class="bi bi-box-arrow-up me-1"></i>Inventory OUT</a>@endcan
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get">
    <x-date-filter :range="$range" />
    <div class="col-6 col-md-3"><label class="form-label">Category</label><select name="category_id" class="form-select form-select-sm"><option value="">All categories</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button> <a href="{{ route('imported.dashboard') }}" class="btn btn-sm btn-link">Reset</a></div>
</form>
@php($cq = array_filter(['category_id' => request('category_id')]))
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Unique products" :value="number_format($kpi['products'])" icon="bi-tags" :href="route('imported.stock.index', $cq)" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Units in stock" :value="$Q::fmt($kpi['units'])" icon="bi-box-seam" :href="route('imported.stock.index', $cq + ['stock' => 'in'])" tip="Sum of quantities across all units of measure" /></div>
    <div class="col-12 col-md-4 col-xl-2"><x-kpi label="Stock value (priced items)" :value="$kpi['values']->isEmpty() ? '—' : $kpi['values']->map(fn ($v, $c) => $Q::money($v, $c))->implode(' · ')" icon="bi-cash-stack" variant="muted" :sub="$kpi['priced'].' of '.$kpi['products'].' products have a cost'" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Received today" :value="$Q::fmt($kpi['in_today'])" icon="bi-box-arrow-in-down" variant="success" :sub="$kpi['in_today_n'].' product(s)'" :href="route('imported.transactions.index', ['type' => 'in', 'period' => 'today'] + $cq)" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Issued today" :value="$Q::fmt($kpi['out_today'])" icon="bi-box-arrow-up" variant="danger" :sub="$kpi['out_today_n'].' product(s)'" :href="route('imported.transactions.index', ['type' => 'out', 'period' => 'today'] + $cq)" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="IN this month" :value="$Q::fmt($kpi['in_month'])" icon="bi-calendar-plus" variant="success" :href="route('imported.transactions.index', ['type' => 'in', 'period' => 'month'] + $cq)" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="OUT this month" :value="$Q::fmt($kpi['out_month'])" icon="bi-calendar-minus" variant="danger" :href="route('imported.transactions.index', ['type' => 'out', 'period' => 'month'] + $cq)" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Low stock" :value="$kpi['low']" icon="bi-exclamation-triangle" :variant="$kpi['low'] ? 'warning' : 'muted'" :href="route('imported.stock.index', $cq + ['stock' => 'low'])" /></div>
    <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Out of stock" :value="$kpi['out']" icon="bi-x-octagon" :variant="$kpi['out'] ? 'danger' : 'muted'" :href="route('imported.stock.index', $cq + ['stock' => 'out'])" /></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8"><div class="card h-100"><div class="card-header"><span class="card-title">Receipts vs consumption</span>
        <div class="btn-group btn-group-sm">@foreach(\App\Support\Trend::GRAINS as $g => $label)<a href="{{ request()->fullUrlWithQuery(['grain' => $g]) }}" class="btn {{ $grain === $g ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>@endforeach</div></div>
        <div class="card-body"><div class="chart-box"><canvas data-chart="{{ json_encode(['type' => 'bar', 'labels' => $trend['labels'], 'datasets' => [['label' => 'IN', 'data' => $trend['series']['qin'], 'backgroundColor' => '#16a34a'], ['label' => 'OUT', 'data' => $trend['series']['qout'], 'backgroundColor' => '#dc2626']]]) }}"></canvas></div></div></div></div>
    <div class="col-xl-4"><div class="card h-100"><div class="card-header"><span class="card-title">Stock by category</span></div>
        <div class="card-body"><div class="chart-box"><canvas data-chart="{{ json_encode(['type' => 'doughnut', 'labels' => $byCategory->pluck('name'), 'links' => $byCategory->map(fn ($c) => route('imported.stock.index', ['category_id' => $c->id])), 'datasets' => [['label' => 'Units', 'data' => $byCategory->pluck('q')->map(fn ($v) => (float) $v)]]]) }}"></canvas></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-4"><div class="card h-100"><div class="card-header"><span class="card-title text-danger"><i class="bi bi-bell me-1"></i>Stock alerts</span><a href="{{ route('imported.stock.index', ['stock' => 'low']) }}" class="small">All</a></div>
        <ul class="list-group list-group-flush alert-list">@forelse($alerts as $p)
            <li class="list-group-item justify-content-between"><x-part-cell :part="$p" size="thumb-sm" />
                <span class="text-end"><span class="fw-bold {{ (float) $p->current_stock <= 0 ? 'text-danger' : 'text-warning' }}">{{ $Q::fmt($p->current_stock) }}</span><br><small class="text-muted">min {{ $Q::fmt($p->min_stock) }}</small></span></li>
        @empty <li class="list-group-item"><x-empty icon="bi-check2-circle" message="No low or out-of-stock products." /></li> @endforelse</ul></div></div>
    <div class="col-xl-4"><div class="card h-100"><div class="card-header"><span class="card-title">Most frequently issued</span></div>
        <table class="table table-sm mb-0"><thead><tr><th>Product</th><th class="num">Issues</th><th class="num">Qty</th></tr></thead><tbody>
        @forelse($mostIssued as $m)<tr><td><a href="{{ route('imported.transactions.index', ['spare_part_id' => $m->spare_part_id, 'type' => 'out'] + $range->query()) }}"><span class="ref">{{ $m->part_sku }}</span></a> {{ \Illuminate\Support\Str::limit($m->part_name, 28) }}</td><td class="num">{{ $m->n }}</td><td class="num fw-semibold">{{ $Q::fmt($m->q) }}</td></tr>
        @empty <x-empty colspan="3" message="No issues in this period." /> @endforelse</tbody></table></div></div>
    <div class="col-xl-4"><div class="card h-100"><div class="card-header"><span class="card-title">Parts issued per machinery model</span></div>
        <div class="card-body"><div class="chart-box sm"><canvas data-chart="{{ json_encode(['type' => 'bar', 'horizontal' => true, 'multicolor' => true, 'labels' => $byModel->pluck('name'), 'links' => $byModel->map(fn ($m) => $m->id ? route('imported.transactions.index', ['machinery_model_id' => $m->id, 'type' => 'out'] + $range->query()) : null), 'datasets' => [['label' => 'Qty', 'data' => $byModel->pluck('q')->map(fn ($v) => (float) $v)]]]) }}"></canvas></div>
        @if($byModel->isEmpty())<x-empty message="No issues in this period." />@endif</div></div></div>
    <div class="col-12"><div class="card"><div class="card-header"><span class="card-title">Recent transactions</span><a href="{{ route('imported.transactions.index') }}" class="small">All transactions</a></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Date</th><th>Reference</th><th>Product</th><th>Type</th><th class="num">Qty</th><th class="num">Balance</th><th>By</th></tr></thead><tbody>
        @forelse($recent as $t)<tr><td>{{ $t->transaction_date->format('d M') }}</td><td class="ref"><a href="{{ route('imported.transactions.show', $t) }}">{{ $t->reference_no }}</a></td>
            <td><x-part-cell :part="$t->sparePart" :name="$t->part_name" :sku="$t->part_sku" size="thumb-sm" /></td><td><span class="badge badge-soft-{{ $t->typeBadge() }}">{{ $t->typeLabel() }}</span></td>
            <td class="num">{{ (float) $t->quantity_in ? '+'.$Q::fmt($t->quantity_in) : '−'.$Q::fmt($t->quantity_out) }}</td><td class="num">{{ $Q::fmt($t->balance_after) }}</td><td class="small">{{ $t->creator?->name }}</td></tr>
        @empty <x-empty colspan="7" message="No transactions yet." /> @endforelse</tbody></table></div></div></div>
</div>
@endsection
