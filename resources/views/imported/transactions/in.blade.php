@extends('layouts.app')
@section('title', 'Inventory IN')
@section('content')
<x-page-header title="Inventory IN — receive imported parts" subtitle="Saving increases available imported stock and creates a permanent ledger entry." :crumbs="['Imported' => route('imported.dashboard'), 'Inventory IN' => null]">
    <a href="{{ route('imported.transactions.index', ['type' => 'in', 'period' => 'today']) }}" class="btn btn-outline-secondary"><i class="bi bi-list me-1"></i>Today's receipts</a>
</x-page-header>
<form method="post" action="{{ route('imported.in.store') }}" data-part-scope>
    @csrf <x-idempotency />
    <div class="row g-3">
        <div class="col-lg-8"><div class="card"><div class="card-body"><div class="row g-3">
            <div class="col-md-4"><label class="form-label">Date <span class="req">*</span></label>
                <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control @error('transaction_date') is-invalid @enderror" required></div>
            <div class="col-md-8"><label class="form-label d-flex justify-content-between"><span>Product / unit name <span class="req">*</span></span>
                @can('imported.products.manage')<a href="#" data-bs-toggle="modal" data-bs-target="#quickPartModal" class="small fw-normal"><i class="bi bi-plus-circle me-1"></i>New product</a>@endcan</label>
                <select name="spare_part_id" id="partPicker" required data-part-picker data-url="{{ route('lookup.parts', 'imported') }}" placeholder="Search by name, SKU, part no., category…" @if($selected) data-selected="{{ json_encode($selected) }}" @endif></select>
                @error('spare_part_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">Category</label><input class="form-control" data-part-field="category" readonly tabindex="-1"></div>
            <div class="col-md-8"><label class="form-label">Size / description</label><input name="specification" value="{{ old('specification') }}" class="form-control" data-part-field="specification" maxlength="255"></div>
            <div class="col-md-4"><label class="form-label">Quantity received <span class="req">*</span></label>
                <div class="input-group"><input type="number" step="any" min="0" name="quantity" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required><span class="input-group-text" data-part-field="unit">—</span></div>
                @error('quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select tom"><option value="">—</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Source (if no supplier)</label><input name="source" value="{{ old('source') }}" class="form-control" maxlength="150" placeholder="e.g. Dubai – Deira market"></div>
            <div class="col-md-4"><label class="form-label">PO / invoice / shipment ref.</label><input name="document_reference" value="{{ old('document_reference') }}" class="form-control" maxlength="100"></div>
            <div class="col-md-4"><label class="form-label">Unit purchase cost</label><input type="number" step="0.01" min="0" name="unit_cost" value="{{ old('unit_cost') }}" class="form-control @error('unit_cost') is-invalid @enderror"></div>
            <div class="col-md-4"><label class="form-label">Currency</label><select name="currency" class="form-select @error('currency') is-invalid @enderror"><option value="">—</option>
                @foreach(config('spims.currencies') as $c)<option @selected(old('currency', \App\Models\AppSetting::get('default_currency')) === $c)>{{ $c }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" rows="2" class="form-control" maxlength="1000">{{ old('remarks') }}</textarea></div>
        </div></div></div></div>
        <div class="col-lg-4">
            @include('imported.transactions._part_panel')
            <div class="small text-muted mt-2">Recorded by <strong>{{ auth()->user()->name }}</strong> · {{ now()->format('d M Y H:i') }}</div>
            <div class="d-grid gap-2 mt-3">
                <button class="btn btn-success" type="submit" name="after" value="show"><i class="bi bi-box-arrow-in-down me-1"></i>Save receipt</button>
                <button class="btn btn-outline-success" type="submit" name="after" value="new">Save &amp; receive another</button>
            </div>
        </div>
    </div>
</form>
@can('imported.products.manage') @push('modals') @include('partials.quick-part-modal', ['type' => 'imported', 'target' => '#partPicker']) @endpush @endcan
@endsection
