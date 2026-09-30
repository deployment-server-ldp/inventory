@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', $record->reference_no)
@section('content')
<x-page-header :title="'Production '.$record->reference_no" :crumbs="['Production' => route('cnc.production.index'), $record->reference_no => null]">
    @if($record->status !== 'cancelled')
        @can('cnc.production.edit')<a href="{{ route('cnc.production.edit', $record) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
        @can('cnc.production.cancel')<button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="bi bi-x-circle me-1"></i>Cancel entry</button>@endcan
    @endif
    @can('cnc.production.create')<a href="{{ route('cnc.production.create', ['machine_id' => $record->machine_id]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New entry</a>@endcan
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header"><span class="card-title">Entry details</span><span class="badge badge-soft-{{ $record->statusBadge() }} fs-6">{{ \App\Models\CncProductionRecord::STATUSES[$record->status] }}</span></div>
            <div class="card-body">
                <div class="mb-3"><x-part-cell :part="$record->sparePart" :name="$record->part_name" :sku="$record->part_sku" size="thumb-lg" /></div>
                <dl class="row detail mb-0">
                    <div class="col-sm-4"><dt>Production date</dt><dd>{{ $record->production_date->format('D, d M Y') }}</dd></div>
                    <div class="col-sm-4"><dt>CNC machine</dt><dd><a href="{{ route('cnc.machines.show', $record->machine) }}">{{ $record->machine->label }}</a></dd></div>
                    <div class="col-sm-4"><dt>Operation</dt><dd>{{ $record->operation_name }} @if($record->is_final_operation)<span class="badge badge-soft-primary">final</span>@endif</dd></div>
                    <div class="col-sm-4"><dt>Target machinery</dt><dd>{{ $record->machineryModel?->name ?? '—' }}</dd></div>
                    <div class="col-sm-4"><dt>Operator</dt><dd>{{ $record->operator->label }}</dd></div>
                    <div class="col-sm-4"><dt>Time</dt><dd>{{ substr($record->start_time, 0, 5) }} – {{ $record->end_time ? substr($record->end_time, 0, 5) : 'running' }} ({{ $record->durationLabel() }})</dd></div>
                    <div class="col-sm-4"><dt>Quantity</dt><dd class="fs-5 fw-bold">{{ $record->status === 'running' ? '—' : $Q::fmt($record->quantity).' '.$record->sparePart->unit?->symbol }}</dd></div>
                    <div class="col-sm-8"><dt>Remarks</dt><dd style="white-space:pre-line">{{ $record->remarks ?: '—' }}</dd></div>
                    <div class="col-sm-6"><dt>Recorded by</dt><dd>{{ $record->creator?->name ?? '—' }} · {{ $record->created_at->format('d M Y H:i') }}</dd></div>
                    @if($record->updated_at && $record->updated_at->ne($record->created_at))<div class="col-sm-6"><dt>Last modified</dt><dd>{{ $record->updated_at->format('d M Y H:i') }}</dd></div>@endif
                    @if($record->status === 'cancelled')
                        <div class="col-12"><dt>Cancelled</dt><dd class="text-danger">{{ $record->cancelled_at?->format('d M Y H:i') }} by {{ $record->canceller?->name }} — {{ $record->cancel_reason }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        @if($record->status === 'running')
            @can('cnc.production.create')
            <div class="card mb-3 border-primary"><div class="card-header"><span class="card-title"><i class="bi bi-stop-circle me-1"></i>Finish this job</span></div><div class="card-body">
                <form method="post" action="{{ route('cnc.production.finish', $record) }}">@csrf
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label">End time</label><input type="time" name="end_time" value="{{ old('end_time', now()->format('H:i')) }}" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">Quantity</label><input type="number" step="any" min="0" name="quantity" value="{{ old('quantity') }}" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Remarks</label><input name="finish_remarks" class="form-control" maxlength="1000"></div>
                    </div>
                    <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check2 me-1"></i>Finish</button>
                </form></div></div>
            @endcan
        @endif
        <div class="card"><div class="card-body">
            <div class="kpi-label">Awaiting completion for this part</div>
            <div class="kpi-value">{{ $Q::fmt($awaiting) }}</div>
            <p class="small text-muted mb-2">Final-operation output not yet submitted for QC.</p>
            @can('cnc.completion.create')<a href="{{ route('cnc.completions.create', ['part' => $record->spare_part_id]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-patch-check me-1"></i>Record completion</a>@endcan
            <a href="{{ route('cnc.parts.show', $record->spare_part_id) }}" class="btn btn-sm btn-link">Part details</a>
        </div></div>
    </div>
</div>

@can('cnc.production.cancel')
@push('modals')
<div class="modal fade" id="cancelModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" action="{{ route('cnc.production.cancel', $record) }}">@csrf
    <div class="modal-header"><h5 class="modal-title">Cancel {{ $record->reference_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="small text-muted">The entry is kept for audit but excluded from all production totals. This is refused if its output has already been submitted for completion.</p>
        <label class="form-label">Reason <span class="req">*</span></label><textarea name="reason" class="form-control" rows="3" required minlength="5" maxlength="500"></textarea></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-danger">Cancel entry</button></div>
</form></div></div>
@endpush
@endcan
@endsection
