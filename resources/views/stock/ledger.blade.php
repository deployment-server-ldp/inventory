@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@php($isCnc = $type === 'cnc')
@section('title', ($isCnc ? 'CNC' : 'Imported').' stock ledger')
@section('content')
<x-page-header :title="($isCnc ? 'CNC' : 'Imported').' stock ledger'" :subtitle="$range->label().($part ? ' · '.$part->sku.' — '.$part->name : ' · all parts')" :crumbs="[($isCnc ? 'CNC Stock' : 'Imported Stock') => route($isCnc ? 'cnc.stock.index' : 'imported.stock.index'), 'Ledger' => null]">
    @if(auth()->user()->can($isCnc ? 'reports.cnc' : 'reports.imported'))<x-export-buttons route="reports.export" :params="['report' => $isCnc ? 'cnc-ledger' : 'imported-ledger']" />@endif
</x-page-header>
<form class="filter-bar row g-2 align-items-end" method="get">
    <x-date-filter :range="$range" />
    <div class="col-md-3"><label class="form-label">Part</label>
        <select name="part_id" data-part-picker data-url="{{ route('lookup.parts', $type) }}?include_inactive=1" placeholder="All parts" @if($part) data-selected="{{ json_encode(\App\Http\Controllers\LookupController::present($part)) }}" @endif></select></div>
    <div class="col-6 col-md-2"><label class="form-label">Type</label><select name="txn_type" class="form-select form-select-sm"><option value="">All</option>
        @foreach($types as $k => $l)<option value="{{ $k }}" @selected(request('txn_type') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label">Reference</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button> <a href="{{ route($isCnc ? 'cnc.stock.ledger' : 'imported.stock.ledger') }}" class="btn btn-sm btn-link">Reset</a></div>
</form>
@if($summary)
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><x-kpi label="Opening balance" :value="$Q::fmt($summary['opening'])" icon="bi-flag" variant="muted" :sub="'before '.$range->from->format('d M Y')" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Received / in" :value="$Q::fmt($summary['in'])" icon="bi-plus-circle" variant="success" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Issued / out" :value="$Q::fmt($summary['out'])" icon="bi-dash-circle" variant="danger" /></div>
    <div class="col-6 col-md-3"><x-kpi label="Closing balance" :value="$Q::fmt($summary['closing'])" icon="bi-box-seam" :sub="'Current stock: '.$Q::fmt($part->current_stock)" /></div>
</div>
@else
<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Select a part to see opening, running and closing balances.</div>
@endif
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover">
    <thead><tr><th>Date</th><th>Reference</th>@unless($part)<th>Part</th>@endunless<th>Type</th><th>Details</th><th class="num">In</th><th class="num">Out</th>
        @if($part)<th class="num" title="Running balance by transaction date">Balance</th>@else<th class="num" title="Balance of that part right after posting">Bal. after posting</th>@endif<th>By</th><th></th></tr></thead>
    <tbody>
    @if($part && $transactions->currentPage() === 1)
        <tr class="table-light"><td>{{ $range->from->format('d M Y') }}</td><td colspan="5" class="fw-semibold">Opening balance</td><td class="num fw-bold">{{ $Q::fmt($summary['opening']) }}</td><td colspan="2"></td></tr>
    @endif
    @php($running = $pageStart)
    @forelse($transactions as $t)
        @php($running = $running !== null ? $running + (float) $t->quantity_in - (float) $t->quantity_out : null)
        <tr class="{{ $t->is_reversed ? 'text-muted' : '' }}">
            <td class="text-nowrap">{{ $t->transaction_date->format('d M Y') }}</td>
            <td class="ref">@if(! $isCnc)<a href="{{ route('imported.transactions.show', $t) }}">{{ $t->reference_no }}</a>@else{{ $t->reference_no }}@endif
                @if($t->is_reversed)<span class="badge badge-soft-secondary">reversed</span>@endif</td>
            @unless($part)<td><x-part-cell :part="$t->sparePart" :name="$t->part_name" :sku="$t->part_sku" size="thumb-sm" /></td>@endunless
            <td><span class="badge badge-soft-{{ (float) $t->quantity_in > 0 ? 'success' : 'danger' }}">{{ $t->typeLabel() }}</span></td>
            <td class="small">{{ $isCnc ? ($t->issued_to ? 'To: '.$t->issued_to.' ' : '') : ($t->collected_by ? 'By: '.$t->collected_by.' ' : ($t->document_reference ? 'Doc: '.$t->document_reference.' ' : '')) }}{{ \Illuminate\Support\Str::limit($t->remarks, 60) }}</td>
            <td class="num text-success">{{ (float) $t->quantity_in ? $Q::fmt($t->quantity_in) : '' }}</td>
            <td class="num text-danger">{{ (float) $t->quantity_out ? $Q::fmt($t->quantity_out) : '' }}</td>
            <td class="num fw-semibold">{{ $Q::fmt($running ?? $t->balance_after) }}</td>
            <td class="small">{{ $t->creator?->name }}</td>
            <td class="text-end">
                @if($isCnc && $t->type === 'issue' && ! $t->is_reversed && auth()->user()->can('cnc.inventory.reverse'))
                    <form method="post" action="{{ route('cnc.stock.reverse', $t) }}" class="d-inline-flex gap-1" data-confirm="Reverse issue {{ $t->reference_no }}? Stock will be returned.">@csrf
                        <input name="reason" class="form-control form-control-sm" placeholder="Reason" required minlength="5" style="width:120px"><button class="btn btn-sm btn-outline-danger" title="Reverse"><i class="bi bi-arrow-counterclockwise"></i></button></form>
                @endif
            </td>
        </tr>
    @empty
        <x-empty colspan="10" message="No stock movements in this period." />
    @endforelse
    </tbody>
</table></div>
@if($transactions->hasPages())<div class="card-footer bg-white">{{ $transactions->links() }}</div>@endif</div>
@endsection
