@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', $machine->code.' monthly sheet')
@section('content')
<x-page-header :title="'Monthly production sheet — '.$machine->code" :subtitle="$start->format('F Y').' · completed operation quantities by date'" :crumbs="['CNC Machines' => route('cnc.machines.index'), $machine->code => route('cnc.machines.show', $machine), 'Monthly' => null]">
    <x-export-buttons route="cnc.machines.monthly" :params="['machine' => $machine->id, 'month' => $month]" />
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-auto"><label class="form-label">Month</label><input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm" data-autosubmit></div>
    <div class="col-auto"><a href="{{ route('cnc.machines.monthly', [$machine, 'month' => $start->subMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
        <a href="{{ route('cnc.machines.monthly', [$machine, 'month' => $start->addMonth()->format('Y-m')]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-bordered sheet-table mb-0">
    <thead><tr><th class="sticky-col">Date</th><th>Day</th>@foreach($operations as $o)<th class="num">{{ $o->name }}</th>@endforeach<th class="num">Total qty</th><th class="num">Entries</th><th class="num">Run time</th><th>Parts</th></tr></thead>
    <tbody>
    @foreach($days as $d)
        <tr class="{{ in_array($d['day'], ['Fri', 'Sun']) ? 'table-light' : '' }}">
            <td class="sticky-col text-nowrap"><a href="{{ route('cnc.machines.show', [$machine, 'from' => $d['date'], 'to' => $d['date'], 'period' => 'custom']) }}">{{ \Carbon\Carbon::parse($d['date'])->format('d M') }}</a></td>
            <td class="small text-muted">{{ $d['day'] }}</td>
            @foreach($operations as $o)<td class="num">{{ isset($d['ops'][$o->id]) ? $Q::fmt($d['ops'][$o->id]) : '' }}</td>@endforeach
            <td class="num fw-semibold">{{ $d['total'] ? $Q::fmt($d['total']) : '' }}</td><td class="num">{{ $d['entries'] ?: '' }}</td>
            <td class="num small">{{ $d['minutes'] ? round($d['minutes'] / 60, 1).'h' : '' }}</td><td class="small">{{ $d['parts'] }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot class="fw-bold"><tr><td class="sticky-col">Total</td><td></td>@foreach($operations as $o)<td class="num">{{ $Q::fmt($opTotals[$o->id] ?? 0) }}</td>@endforeach
        <td class="num">{{ $Q::fmt($total) }}</td><td class="num">{{ $entries }}</td><td class="num">{{ round($minutes / 60, 1) }}h</td><td></td></tr></tfoot>
</table></div></div>
<p class="small text-muted mt-2">Totals sum all operations (work performed). Finished parts are the final-operation column for each part; stock is added only via approved completions.</p>
@endsection
