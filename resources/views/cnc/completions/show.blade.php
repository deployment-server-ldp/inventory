@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', $c->reference_no)
@section('content')
<x-page-header :title="'Completion '.$c->reference_no" :crumbs="['Completions' => route('cnc.completions.index'), $c->reference_no => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-header"><span class="card-title">Details</span><span class="badge badge-soft-{{ $c->statusBadge() }} fs-6">{{ \App\Models\CncProductionCompletion::STATUSES[$c->status] }}</span></div>
        <div class="card-body">
            <div class="mb-3"><x-part-cell :part="$c->sparePart" :name="$c->part_name" :sku="$c->part_sku" size="thumb-lg" /></div>
            <dl class="row detail mb-0">
                <div class="col-sm-3"><dt>Date</dt><dd>{{ $c->completion_date->format('d M Y') }}</dd></div>
                <div class="col-sm-3"><dt>Inspected</dt><dd class="fs-5">{{ $Q::fmt($c->quantity_inspected) }}</dd></div>
                <div class="col-sm-3"><dt>Accepted</dt><dd class="fs-5 text-success fw-bold">{{ $Q::fmt($c->quantity_accepted) }}</dd></div>
                <div class="col-sm-3"><dt>Rejected</dt><dd class="fs-5 text-danger">{{ $Q::fmt($c->quantity_rejected) }}</dd></div>
                <div class="col-sm-6"><dt>Submitted by</dt><dd>{{ $c->submitter?->name }} · {{ $c->created_at->format('d M Y H:i') }}</dd></div>
                @if($c->approver)<div class="col-sm-6"><dt>{{ $c->status === 'rejected' ? 'Rejected' : 'Approved' }} by</dt><dd>{{ $c->approver->name }} · {{ $c->approved_at?->format('d M Y H:i') }}</dd></div>@endif
                <div class="col-12"><dt>Remarks</dt><dd>{{ $c->remarks ?: '—' }}</dd></div>
                @if($c->decision_notes)<div class="col-12"><dt>Decision notes</dt><dd>{{ $c->decision_notes }}</dd></div>@endif
                @if($c->receipt)<div class="col-sm-6"><dt>Stock receipt</dt><dd><a class="ref" href="{{ route('cnc.stock.ledger', ['part_id' => $c->spare_part_id, 'period' => 'year']) }}">{{ $c->receipt->reference_no }}</a> · balance after {{ $Q::fmt($c->receipt->balance_after) }}
                    @if($c->receipt->reversal)<br><span class="text-danger small">Reversed by {{ $c->receipt->reversal->reference_no }}</span>@endif</dd></div>@endif
                @if($c->status === 'reversed')<div class="col-12"><dt>Reversed</dt><dd class="text-danger">{{ $c->reversed_at?->format('d M Y H:i') }} by {{ $c->reverser?->name }} — {{ $c->reversal_reason }}</dd></div>@endif
            </dl>
        </div></div></div>
    <div class="col-lg-4">
        @can('cnc.completion.approve')
            @if($c->status === 'pending')
                <div class="card mb-3 border-success"><div class="card-header"><span class="card-title">Quality decision</span></div><div class="card-body">
                    <form method="post" action="{{ route('cnc.completions.approve', $c) }}" class="mb-3">@csrf
                        <div class="row g-2"><div class="col-6"><label class="form-label">Accepted</label><input type="number" step="any" min="0" name="quantity_accepted" value="{{ (float) $c->quantity_accepted }}" class="form-control"></div>
                            <div class="col-6"><label class="form-label">Rejected</label><input type="number" step="any" min="0" name="quantity_rejected" value="{{ (float) $c->quantity_rejected }}" class="form-control"></div>
                            <div class="col-12 form-text">Accepted + rejected must equal {{ $Q::fmt($c->quantity_inspected) }}.</div>
                            <div class="col-12"><input name="decision_notes" class="form-control" placeholder="Notes (optional)" maxlength="500"></div></div>
                        <button class="btn btn-success w-100 mt-2"><i class="bi bi-check2-circle me-1"></i>Approve &amp; add to stock</button>
                    </form>
                    <form method="post" action="{{ route('cnc.completions.reject', $c) }}" data-confirm="Reject this completion?">@csrf
                        <input name="decision_notes" class="form-control mb-2" placeholder="Reason for rejection *" required minlength="5" maxlength="500">
                        <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i>Reject</button>
                    </form>
                </div></div>
            @elseif($c->status === 'approved')
                <div class="card mb-3"><div class="card-header"><span class="card-title">Reverse completion</span></div><div class="card-body">
                    <p class="small text-muted">Removes the accepted quantity from stock (only if still available) and returns the inspected quantity to the awaiting-completion pool.</p>
                    <form method="post" action="{{ route('cnc.completions.reverse', $c) }}" data-confirm="Reverse this completion and remove its stock?">@csrf
                        <textarea name="reason" class="form-control mb-2" rows="2" placeholder="Reason *" required minlength="5" maxlength="500"></textarea>
                        <button class="btn btn-outline-danger w-100"><i class="bi bi-arrow-counterclockwise me-1"></i>Reverse</button></form>
                </div></div>
            @endif
        @endcan
        <div class="card"><div class="card-body"><div class="kpi-label">Part awaiting completion now</div><div class="kpi-value">{{ $Q::fmt($awaiting) }}</div>
            <div class="small text-muted">Current finished stock: {{ $Q::fmt($c->sparePart->current_stock) }} {{ $c->sparePart->unit?->symbol }}</div></div></div>
    </div>
</div>
@endsection
