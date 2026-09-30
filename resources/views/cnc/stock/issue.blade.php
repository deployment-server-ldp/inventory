@extends('layouts.app')
@section('title', 'Issue CNC stock')
@section('content')
<x-page-header title="Issue CNC finished stock" subtitle="Dispatch or consume manufactured parts. Stock can never go negative." :crumbs="['CNC Stock' => route('cnc.stock.index'), 'Issue' => null]" />
<form method="post" action="{{ route('cnc.stock.issue.store') }}" data-part-scope>
    @csrf <x-idempotency />
    <div class="row g-3"><div class="col-lg-8"><div class="card"><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Date <span class="req">*</span></label><input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
        <div class="col-md-8"><label class="form-label">Part <span class="req">*</span></label>
            <select name="spare_part_id" required data-part-picker data-url="{{ route('lookup.parts', 'cnc') }}?in_stock=1" placeholder="Search part in stock…" @if($selected) data-selected="{{ json_encode($selected) }}" @endif></select></div>
        <div class="col-md-4"><label class="form-label">Quantity <span class="req">*</span></label><div class="input-group"><input type="number" step="any" min="0" name="quantity" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required><span class="input-group-text" data-part-field="unit">—</span></div></div>
        <div class="col-md-8"><label class="form-label">Issued to (customer / person) <span class="req">*</span></label><input name="issued_to" value="{{ old('issued_to') }}" class="form-control" required maxlength="150"></div>
        <div class="col-md-6"><label class="form-label">For machinery model</label><select name="machinery_model_id" class="form-select tom"><option value="">—</option>@foreach($models as $m)<option value="{{ $m->id }}" @selected(old('machinery_model_id') == $m->id)>{{ $m->name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Purpose</label><input name="purpose" value="{{ old('purpose') }}" class="form-control" maxlength="255"></div>
        <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" rows="2" class="form-control">{{ old('remarks') }}</textarea></div>
    </div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body">
        <div class="part-preview"><div data-part-field="image"><div class="placeholder-img"><i class="bi bi-image"></i></div></div>
            <div class="small"><div class="fw-semibold" data-part-field="name">—</div><div class="ref" data-part-field="sku">—</div><div data-part-field="category"></div>
                <div class="mt-2">Available: <span class="stock-pill text-primary" data-part-field="stock">—</span> <span data-part-field="unit"></span></div></div></div>
    </div></div><div class="d-grid mt-3"><button class="btn btn-danger"><i class="bi bi-box-arrow-up me-1"></i>Issue stock</button></div></div></div>
</form>
@endsection
