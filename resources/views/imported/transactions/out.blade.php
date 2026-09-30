@extends('layouts.app')
@section('title', 'Inventory OUT')
@section('content')
<x-page-header title="Inventory OUT — issue imported parts" subtitle="Saving deducts the issued quantity. Stock can never become negative." :crumbs="['Imported' => route('imported.dashboard'), 'Inventory OUT' => null]">
    <a href="{{ route('imported.transactions.index', ['type' => 'out', 'period' => 'today']) }}" class="btn btn-outline-secondary"><i class="bi bi-list me-1"></i>Today's issues</a>
</x-page-header>
<form method="post" action="{{ route('imported.out.store') }}" data-part-scope>
    @csrf <x-idempotency />
    <div class="row g-3">
        <div class="col-lg-8"><div class="card"><div class="card-body"><div class="row g-3">
            <div class="col-md-4"><label class="form-label">Date <span class="req">*</span></label>
                <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
            <div class="col-md-8"><label class="form-label">Product / unit name <span class="req">*</span></label>
                <select name="spare_part_id" id="partPicker" required data-part-picker data-url="{{ route('lookup.parts', 'imported') }}?in_stock=1" placeholder="Search products in stock…" @if($selected) data-selected="{{ json_encode($selected) }}" @endif></select>
                @error('spare_part_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">Category</label><input class="form-control" data-part-field="category" readonly tabindex="-1"></div>
            <div class="col-md-8"><label class="form-label">Size / description</label><input name="specification" value="{{ old('specification') }}" class="form-control" data-part-field="specification" maxlength="255"></div>
            <div class="col-md-4"><label class="form-label">Quantity issued <span class="req">*</span></label>
                <div class="input-group"><input type="number" step="any" min="0" name="quantity" id="qtyOut" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required><span class="input-group-text" data-part-field="unit">—</span></div>
                @error('quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text text-danger d-none" id="qtyWarn">Exceeds available stock.</div></div>
            <div class="col-md-8"><label class="form-label">Machine / machinery model <span class="req">*</span></label>
                <select name="machinery_model_id" class="form-select tom @error('machinery_model_id') is-invalid @enderror"><option value="">Select…</option>
                    @foreach($models as $m)<option value="{{ $m->id }}" @selected(old('machinery_model_id') == $m->id)>{{ $m->name }}</option>@endforeach</select>
                @error('machinery_model_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            @if($canManual)
                <div class="col-md-6"><label class="form-label">Purpose (manual entry) <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" title="Use when the machine is not in the machinery model list"></i></label>
                    <input name="purpose" value="{{ old('purpose') }}" class="form-control" maxlength="255"></div>
            @endif
            <div class="col-md-6"><label class="form-label">Machine assembly (optional)</label><select name="machine_assembly_id" class="form-select"><option value="">— not for an assembly —</option>
                @foreach($assemblies as $a)<option value="{{ $a->id }}" @selected($assemblyId == $a->id)>{{ $a->reference_no }} — {{ $a->name }}</option>@endforeach</select>
                <div class="form-text">Issues tagged here count toward the assembly's consumption (no double deduction).</div></div>
            <div class="col-md-6"><label class="form-label">Person collecting the parts <span class="req">*</span></label><input name="collected_by" value="{{ old('collected_by') }}" class="form-control @error('collected_by') is-invalid @enderror" required maxlength="150"></div>
            <div class="col-md-6"><label class="form-label">Department / destination</label><input name="department" value="{{ old('department') }}" class="form-control" maxlength="150"></div>
            <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" rows="2" class="form-control" maxlength="1000">{{ old('remarks') }}</textarea></div>
        </div></div></div></div>
        <div class="col-lg-4">
            @include('imported.transactions._part_panel')
            <div class="small text-muted mt-2">Recorded by <strong>{{ auth()->user()->name }}</strong> · {{ now()->format('d M Y H:i') }}</div>
            <div class="d-grid gap-2 mt-3">
                <button class="btn btn-danger" type="submit" name="after" value="show"><i class="bi bi-box-arrow-up me-1"></i>Save issue</button>
                <button class="btn btn-outline-danger" type="submit" name="after" value="new">Save &amp; issue another</button>
            </div>
        </div>
    </div>
</form>
@push('scripts')
<script>
(function () {
    let available = null;
    const q = document.getElementById('qtyOut'), w = document.getElementById('qtyWarn');
    const check = () => w.classList.toggle('d-none', !(available !== null && parseFloat(q.value) > available));
    document.querySelector('[data-part-scope]').addEventListener('part:selected', e => { available = e.detail ? e.detail.stock_raw : null; check(); });
    q.addEventListener('input', check);
})();
</script>
@endpush
@endsection
