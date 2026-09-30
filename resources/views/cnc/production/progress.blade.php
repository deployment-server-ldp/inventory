@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Operation progress')
@section('content')
<x-page-header title="Operation progress (work in progress)" subtitle="Cumulative completed quantities per operation. Only final-operation output can be completed into stock — earlier operations never add to inventory." />
<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label">Part</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name or SKU"></div>
    <div class="col-md-3"><label class="form-label">Category</label><select name="category_id" class="form-select form-select-sm" data-autosubmit><option value="">All</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Part</th><th>Final op</th>
        @foreach($operations as $op)<th class="num">{{ $op->name }}</th>@endforeach
        <th class="num" title="Final-op output − submitted completions">Awaiting QC</th><th class="num">Pending QC</th><th class="num">Accepted</th><th class="num">Rejected</th><th class="num">Stock</th><th></th></tr></thead>
    <tbody>@forelse($parts as $p)
        @php($c = $comp[$p->id] ?? null)
        @php($awaiting = (float) ($finalOut[$p->id] ?? 0) - (float) ($c->inspected ?? 0))
        <tr>
            <td><x-part-cell :part="$p" size="thumb-sm" :meta="($running[$p->id] ?? 0) ? $running[$p->id].' running' : null" /></td>
            <td class="small">{{ $p->finalOperation?->name ?? 'Last op' }}</td>
            @foreach($operations as $op)
                <td class="num {{ $p->final_operation_id == $op->id ? 'fw-bold text-primary' : '' }}">{{ isset($byOp[$p->id][$op->id]) ? $Q::fmt($byOp[$p->id][$op->id]) : '·' }}</td>
            @endforeach
            <td class="num fw-bold {{ $awaiting > 0 ? 'text-warning' : '' }}">{{ $Q::fmt($awaiting) }}</td>
            <td class="num">{{ $Q::fmt($c->pending ?? 0) }}</td>
            <td class="num text-success">{{ $Q::fmt($c->accepted ?? 0) }}</td>
            <td class="num text-danger">{{ $Q::fmt($c->rejected ?? 0) }}</td>
            <td class="num fw-semibold">{{ $Q::fmt($p->current_stock) }}</td>
            <td class="text-end text-nowrap">@if($awaiting > 0 && auth()->user()->can('cnc.completion.create'))<a href="{{ route('cnc.completions.create', ['part' => $p->id]) }}" class="btn btn-sm btn-outline-success">Complete</a>@endif</td>
        </tr>
    @empty <x-empty :colspan="count($operations) + 8" message="No production recorded yet." /> @endforelse</tbody>
</table></div>
@if($parts->hasPages())<div class="card-footer bg-white">{{ $parts->links() }}</div>@endif</div>
<p class="small text-muted mt-2">Example: 1st op 100, 2nd op 80, 3rd (final) op 60 → only 60 can be completed into stock after QC approval, never 240.</p>
@endsection
