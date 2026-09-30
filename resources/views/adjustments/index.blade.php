@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Stock adjustments')
@section('content')
<x-page-header title="Stock adjustments" subtitle="Controlled corrections with a mandatory reason. Every adjustment is also a ledger entry.">
    @foreach($types as $t)<a href="{{ route('adjustments.create', $t) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ $t === 'cnc' ? 'CNC' : 'Imported' }} adjustment</a>@endforeach
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get"><x-date-filter :range="$range" />
    <div class="col-6 col-md-2"><label class="form-label">Inventory</label><select name="type" class="form-select form-select-sm"><option value="">All</option>
        @foreach($types as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ \App\Models\SparePart::TYPES[$t] }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Search</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Ref, part, reason"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Date</th><th>Reference</th><th>Inventory</th><th>Part</th><th class="num">Qty</th><th>Reason</th><th>By</th></tr></thead>
    <tbody>@forelse($adjustments as $a)
        <tr><td>{{ $a->adjustment_date->format('d M Y') }}</td><td class="ref">{{ $a->reference_no }}</td><td>{{ $a->inventory_type === 'cnc' ? 'CNC' : 'Imported' }}</td>
            <td><x-part-cell :part="$a->sparePart" size="thumb-sm" /></td>
            <td class="num fw-bold {{ $a->direction === 'in' ? 'text-success' : 'text-danger' }}">{{ $a->direction === 'in' ? '+' : '−' }}{{ $Q::fmt($a->quantity) }}</td>
            <td class="small">{{ $a->reason }}</td><td class="small">{{ $a->creator?->name }}<br>{{ $a->created_at->format('d M H:i') }}</td></tr>
    @empty <x-empty colspan="7" message="No adjustments in this period." /> @endforelse</tbody>
</table></div>@if($adjustments->hasPages())<div class="card-footer bg-white">{{ $adjustments->links() }}</div>@endif</div>
@endsection
