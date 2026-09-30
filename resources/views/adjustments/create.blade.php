@extends('layouts.app')
@section('title', 'New stock adjustment')
@section('content')
<x-page-header :title="($type === 'cnc' ? 'CNC' : 'Imported').' stock adjustment'" subtitle="Use only for physical count differences, damage, loss or data-entry corrections. The reason is mandatory and audited." :crumbs="['Adjustments' => route('adjustments.index'), 'New' => null]" />
<form method="post" action="{{ route('adjustments.store', $type) }}" data-part-scope>@csrf
<div class="row g-3"><div class="col-lg-8"><div class="card"><div class="card-body row g-3">
    <div class="col-md-8"><label class="form-label">Part <span class="req">*</span></label>
        <select name="spare_part_id" required data-part-picker data-url="{{ route('lookup.parts', $type) }}?include_inactive=1" placeholder="Search…" @if($selected) data-selected="{{ json_encode($selected) }}" @endif></select></div>
    <div class="col-md-4"><label class="form-label">Date <span class="req">*</span></label><input type="date" name="adjustment_date" value="{{ old('adjustment_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Direction <span class="req">*</span></label>
        <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="direction" id="dirIn" value="in" @checked(old('direction') === 'in')><label class="btn btn-outline-success" for="dirIn"><i class="bi bi-plus-lg"></i> Increase</label>
            <input type="radio" class="btn-check" name="direction" id="dirOut" value="out" @checked(old('direction', 'out') === 'out')><label class="btn btn-outline-danger" for="dirOut"><i class="bi bi-dash-lg"></i> Decrease</label>
        </div></div>
    <div class="col-md-6"><label class="form-label">Quantity <span class="req">*</span></label><div class="input-group"><input type="number" step="any" min="0" name="quantity" value="{{ old('quantity') }}" class="form-control" required><span class="input-group-text" data-part-field="unit">—</span></div></div>
    <div class="col-12"><label class="form-label">Reason <span class="req">*</span></label><input name="reason" value="{{ old('reason') }}" class="form-control @error('reason') is-invalid @enderror" required minlength="5" maxlength="500" placeholder="e.g. Physical count 30-Sep: 2 units damaged in storage"></div>
    <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" rows="2" class="form-control">{{ old('remarks') }}</textarea></div>
</div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-body"><div class="part-preview"><div data-part-field="image"><div class="placeholder-img"><i class="bi bi-image"></i></div></div>
    <div class="small"><div class="fw-semibold" data-part-field="name">—</div><div class="ref" data-part-field="sku"></div><div class="mt-2">Current stock: <span class="stock-pill" data-part-field="stock">—</span></div></div></div></div></div>
    <div class="d-grid mt-3"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Post adjustment</button></div></div></div>
</form>
@endsection
