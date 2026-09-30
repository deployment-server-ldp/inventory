@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', $t->reference_no)
@section('content')
<x-page-header :title="$t->typeLabel().' '.$t->reference_no" :crumbs="['Transactions' => route('imported.transactions.index'), $t->reference_no => null]">
    @can('imported.in.create')<a href="{{ route('imported.in.create') }}" class="btn btn-outline-success"><i class="bi bi-plus-lg me-1"></i>New IN</a>@endcan
    @can('imported.out.create')<a href="{{ route('imported.out.create') }}" class="btn btn-outline-danger"><i class="bi bi-plus-lg me-1"></i>New OUT</a>@endcan
</x-page-header>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-header"><span class="card-title">Transaction</span>
        <span><span class="badge badge-soft-{{ $t->typeBadge() }} fs-6">{{ $t->typeLabel() }}</span> @if($t->is_reversed)<span class="badge badge-soft-secondary fs-6">Reversed</span>@endif</span></div>
        <div class="card-body">
            <div class="mb-3"><x-part-cell :part="$t->sparePart" :name="$t->part_name" :sku="$t->part_sku" size="thumb-lg" :meta="$t->category_name" /></div>
            <dl class="row detail mb-0">
                <div class="col-sm-4"><dt>Date</dt><dd>{{ $t->transaction_date->format('D, d M Y') }}</dd></div>
                <div class="col-sm-4"><dt>Quantity</dt><dd class="fs-5 fw-bold {{ (float) $t->quantity_in ? 'text-success' : 'text-danger' }}">{{ (float) $t->quantity_in ? '+'.$Q::fmt($t->quantity_in) : '−'.$Q::fmt($t->quantity_out) }} {{ $t->unit_name }}</dd></div>
                <div class="col-sm-4"><dt>Balance after posting</dt><dd class="fs-5">{{ $Q::fmt($t->balance_after) }}</dd></div>
                <div class="col-sm-8"><dt>Size / description</dt><dd>{{ $t->specification ?: '—' }}</dd></div>
                @if($t->type === 'in')
                    <div class="col-sm-4"><dt>Supplier / source</dt><dd>{{ $t->supplier?->name ?? $t->source ?? '—' }}</dd></div>
                    <div class="col-sm-4"><dt>PO / invoice / shipment</dt><dd>{{ $t->document_reference ?: '—' }}</dd></div>
                    <div class="col-sm-4"><dt>Unit cost</dt><dd>{{ $t->unit_cost !== null ? $Q::money($t->unit_cost, $t->currency) : '—' }}</dd></div>
                    <div class="col-sm-4"><dt>Line value</dt><dd>{{ $t->unit_cost !== null ? $Q::money($t->unit_cost * $t->quantity_in, $t->currency) : '—' }}</dd></div>
                @elseif($t->type === 'out')
                    <div class="col-sm-4"><dt>Machine / purpose</dt><dd>{{ $t->machineryModel?->name ?? '—' }}{{ $t->purpose ? ' · '.$t->purpose : '' }}</dd></div>
                    <div class="col-sm-4"><dt>Collected by</dt><dd>{{ $t->collected_by }}</dd></div>
                    <div class="col-sm-4"><dt>Department / destination</dt><dd>{{ $t->department ?: '—' }}</dd></div>
                    <div class="col-sm-4"><dt>Assembly</dt><dd>@if($t->assembly)<a href="{{ route('imported.assemblies.show', $t->assembly) }}">{{ $t->assembly->reference_no }} — {{ $t->assembly->name }}</a>@else — @endif</dd></div>
                @endif
                <div class="col-12"><dt>Remarks</dt><dd style="white-space:pre-line">{{ $t->remarks ?: '—' }}</dd></div>
                <div class="col-sm-6"><dt>Recorded by</dt><dd>{{ $t->creator?->name }} · {{ $t->created_at->format('d M Y H:i:s') }}</dd></div>
                @if($t->reversalOf)<div class="col-sm-6"><dt>Reverses</dt><dd><a class="ref" href="{{ route('imported.transactions.show', $t->reversalOf) }}">{{ $t->reversalOf->reference_no }}</a></dd></div>@endif
                @if($t->reversal)<div class="col-sm-6"><dt>Reversed by</dt><dd><a class="ref" href="{{ route('imported.transactions.show', $t->reversal) }}">{{ $t->reversal->reference_no }}</a> · {{ $t->reversal->created_at->format('d M Y H:i') }}</dd></div>@endif
                @if($t->adjustment)<div class="col-sm-6"><dt>Adjustment reason</dt><dd>{{ $t->adjustment->reason }}</dd></div>@endif
            </dl>
        </div></div></div>
    <div class="col-lg-4">
        @if($t->isReversible())
            @can('imported.transactions.reverse')
                <div class="card border-danger-subtle"><div class="card-header"><span class="card-title"><i class="bi bi-arrow-counterclockwise me-1"></i>Reverse transaction</span></div><div class="card-body">
                    <p class="small text-muted">Posts an opposite movement with your name and reason. The original stays in the ledger. {{ $t->type === 'in' ? 'Refused if the received stock has already been issued.' : '' }}</p>
                    <form method="post" action="{{ route('imported.transactions.reverse', $t) }}" data-confirm="Reverse {{ $t->reference_no }}?">@csrf
                        <textarea name="reason" class="form-control mb-2" rows="2" required minlength="5" maxlength="500" placeholder="Reason *"></textarea>
                        <button class="btn btn-outline-danger w-100">Reverse</button></form>
                </div></div>
            @endcan
        @endif
        <div class="card mt-3"><div class="card-body small">
            <a href="{{ route('imported.stock.ledger', ['part_id' => $t->spare_part_id, 'period' => 'year']) }}"><i class="bi bi-journal-text me-1"></i>Product ledger</a><br>
            <a href="{{ route('imported.products.show', $t->spare_part_id) }}"><i class="bi bi-tag me-1"></i>Product details</a>
        </div></div>
    </div>
</div>
@endsection
