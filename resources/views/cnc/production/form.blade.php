@extends('layouts.app')
@section('title', $record->exists ? 'Edit production entry' : 'New production entry')
@section('content')
<x-page-header :title="$record->exists ? 'Edit '.$record->reference_no : 'New CNC production entry'"
    subtitle="One entry = one part, on one machine, for one operation, on one date. Leave End time empty while the job is still running."
    :crumbs="['Production' => route('cnc.production.index'), ($record->exists ? 'Edit' : 'New') => null]" />

<form method="post" action="{{ $record->exists ? route('cnc.production.update', $record) : route('cnc.production.store') }}" data-part-scope>
    @csrf @if($record->exists) @method('PUT') @else <x-idempotency /> @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Production date <span class="req">*</span></label>
                        <input type="date" name="production_date" max="{{ now()->toDateString() }}" value="{{ old('production_date', $record->production_date?->toDateString()) }}" class="form-control @error('production_date') is-invalid @enderror" required></div>
                    <div class="col-md-4"><label class="form-label">CNC machine <span class="req">*</span></label>
                        <select name="machine_id" class="form-select @error('machine_id') is-invalid @enderror" required><option value="">Select…</option>
                            @foreach($machines as $m)<option value="{{ $m->id }}" @selected(old('machine_id', $record->machine_id) == $m->id)>{{ $m->label }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Operation <span class="req">*</span></label>
                        <select name="operation_id" class="form-select @error('operation_id') is-invalid @enderror" required><option value="">Select…</option>
                            @foreach($operations as $o)<option value="{{ $o->id }}" @selected(old('operation_id', $record->operation_id) == $o->id)>{{ $o->name }}</option>@endforeach</select></div>

                    <div class="col-12"><label class="form-label d-flex justify-content-between">
                        <span>Part <span class="req">*</span></span>
                        @can('cnc.parts.manage')<a href="#" data-bs-toggle="modal" data-bs-target="#quickPartModal" class="small fw-normal"><i class="bi bi-plus-circle me-1"></i>New part</a>@endcan</label>
                        <select name="spare_part_id" id="partPicker" class="@error('spare_part_id') is-invalid @enderror" required placeholder="Search by name, SKU or category…"
                            data-part-picker data-url="{{ route('lookup.parts', 'cnc') }}" data-quick-create="1" @if($selected) data-selected="{{ json_encode($selected) }}" @endif></select>
                        @error('spare_part_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>

                    <div class="col-md-6"><label class="form-label">Target machine / machinery model</label>
                        <select name="machinery_model_id" class="form-select tom"><option value="">—</option>
                            @foreach($models as $m)<option value="{{ $m->id }}" @selected(old('machinery_model_id', $record->machinery_model_id) == $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Machine operator <span class="req">*</span></label>
                        <select name="operator_id" class="form-select tom @error('operator_id') is-invalid @enderror" required><option value="">Select…</option>
                            @foreach($operators as $o)<option value="{{ $o->id }}" @selected(old('operator_id', $record->operator_id) == $o->id)>{{ $o->label }}</option>@endforeach</select></div>

                    <div class="col-6 col-md-3"><label class="form-label">Start time <span class="req">*</span></label>
                        <input type="time" name="start_time" value="{{ old('start_time', $record->start_time ? substr($record->start_time, 0, 5) : now()->format('H:i')) }}" class="form-control @error('start_time') is-invalid @enderror" required></div>
                    <div class="col-6 col-md-3"><label class="form-label">End time</label>
                        <input type="time" name="end_time" value="{{ old('end_time', $record->end_time ? substr($record->end_time, 0, 5) : '') }}" class="form-control @error('end_time') is-invalid @enderror">
                        <div class="form-text">Empty = still running</div></div>
                    <div class="col-md-3"><label class="form-label">Quantity produced</label>
                        <div class="input-group"><input type="number" step="any" min="0" name="quantity" value="{{ old('quantity', $record->exists && $record->status !== 'running' ? (float) $record->quantity : '') }}" class="form-control @error('quantity') is-invalid @enderror">
                            <span class="input-group-text" data-part-field="unit">—</span></div>
                        <div class="form-text">Required with end time</div></div>
                    <div class="col-12"><label class="form-label">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control" maxlength="1000">{{ old('remarks', $record->remarks) }}</textarea></div>
                </div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <div class="form-section-title mt-0">Selected part</div>
                <div class="part-preview">
                    <div data-part-field="image"><div class="placeholder-img"><i class="bi bi-image"></i></div></div>
                    <div class="small">
                        <div class="fw-semibold text-dark" data-part-field="name">—</div>
                        <div class="ref" data-part-field="sku">—</div>
                        <div>Category: <span data-part-field="category">—</span></div>
                        <div>Final op: <span data-part-field="final_operation">—</span></div>
                        <div>Finished stock: <span data-part-field="stock">—</span> <span data-part-field="unit"></span></div>
                    </div>
                </div>
                <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>
                    Production entries record <strong>operation progress</strong>. Finished stock increases only after a completion is approved by quality/production.</div>
            </div></div>
            <div class="d-grid gap-2 mt-3">
                <button class="btn btn-primary" type="submit" name="after" value="show"><i class="bi bi-check2 me-1"></i>Save entry</button>
                @unless($record->exists)<button class="btn btn-outline-primary" type="submit" name="after" value="new"><i class="bi bi-plus-lg me-1"></i>Save &amp; add another</button>@endunless
                <a href="{{ $record->exists ? route('cnc.production.show', $record) : route('cnc.production.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>
@can('cnc.parts.manage')
    @push('modals') @include('partials.quick-part-modal', ['type' => 'cnc', 'target' => '#partPicker']) @endpush
@endcan
@endsection
