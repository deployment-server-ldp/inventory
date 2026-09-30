@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'CNC Machines')
@section('content')
<x-page-header title="CNC machines" :subtitle="'Live status · production totals for '.$range->label()">
    @can('settings.machines')<a href="{{ route('settings.index', 'machines') }}" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i>Manage machines</a>@endcan
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get"><x-date-filter :range="$range" autosubmit /><div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div></form>
<div class="d-flex gap-3 small text-muted mb-2">
    <span><span class="status-dot status-running"></span> Running</span><span><span class="status-dot status-idle"></span> Idle</span>
    <span><span class="status-dot status-maintenance"></span> Maintenance</span><span><span class="status-dot status-inactive"></span> Inactive</span>
</div>
<div class="row g-3">
@foreach($machines as $m)
    @php($run = $running[$m->id] ?? collect())
    @php($state = $m->status !== 'active' ? $m->status : ($run->isNotEmpty() ? 'running' : 'idle'))
    @php($rq = $rangeQty[$m->id] ?? null)
    <div class="col-6 col-md-4 col-xl-3">
        <a href="{{ route('cnc.machines.show', [$m] + $range->query()) }}" class="machine-tile">
            <div class="d-flex justify-content-between align-items-center"><span class="code">{{ $m->code }}</span><span class="status-dot status-{{ $state }}" title="{{ ucfirst($state) }}"></span></div>
            <div class="small text-muted mb-2">{{ $m->name !== $m->code ? $m->name : ucfirst($state) }}</div>
            @if($run->isNotEmpty())
                <div class="small text-success mb-1 text-truncate"><i class="bi bi-play-fill"></i>{{ $run->pluck('part_sku')->unique()->implode(', ') }}</div>
            @endif
            <div class="d-flex justify-content-between small"><span>Today</span><strong>{{ $Q::fmt($todayQty[$m->id] ?? 0) }}</strong></div>
            <div class="d-flex justify-content-between small"><span>Period</span><strong>{{ $Q::fmt($rq->q ?? 0) }}</strong></div>
            <div class="d-flex justify-content-between small text-muted"><span>{{ $rq->n ?? 0 }} entries</span><span>{{ round(($rq->m ?? 0) / 60, 1) }} h</span></div>
        </a>
    </div>
@endforeach
</div>
<p class="small text-muted mt-2">Quantities are operation output (all operations).</p>
@endsection
