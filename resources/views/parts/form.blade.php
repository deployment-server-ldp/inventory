@extends('layouts.app')
@php($isCnc = $type === 'cnc')
@php($base = $isCnc ? 'cnc.parts' : 'imported.products')
@php($noun = $isCnc ? 'CNC part' : 'imported product')
@section('title', ($part->exists ? 'Edit ' : 'New ').$noun)
@section('content')
<x-page-header :title="$part->exists ? 'Edit '.$part->sku : 'New '.$noun" :crumbs="[($isCnc ? 'CNC Parts' : 'Imported Products') => route($base.'.index'), ($part->exists ? 'Edit' : 'New') => null]" />

<form method="post" enctype="multipart/form-data" action="{{ $part->exists ? route($base.'.update', $part) : route($base.'.store') }}">
    @csrf @if($part->exists) @method('PUT') @endif
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <div class="form-section-title mt-0">Identification</div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">SKU / {{ $isCnc ? 'part' : 'product' }} code <span class="req">*</span></label>
                        <input name="sku" value="{{ old('sku', $part->sku) }}" class="form-control @error('sku') is-invalid @enderror" required maxlength="60">
                        @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-8"><label class="form-label">{{ $isCnc ? 'Part' : 'Product' }} name <span class="req">*</span></label>
                        <input name="name" value="{{ old('name', $part->name) }}" class="form-control @error('name') is-invalid @enderror" required maxlength="191">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">Category <span class="req">*</span></label>
                        <select name="category_id" class="form-select tom @error('category_id') is-invalid @enderror" required><option value="">Select…</option>
                            @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $part->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
                        @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">Unit of measurement <span class="req">*</span></label>
                        <select name="unit_id" class="form-select @error('unit_id') is-invalid @enderror" required><option value="">Select…</option>
                            @foreach($units as $u)<option value="{{ $u->id }}" @selected(old('unit_id', $part->unit_id) == $u->id)>{{ $u->name }} ({{ $u->symbol }})</option>@endforeach</select></div>
                    @unless($isCnc)
                        <div class="col-md-6"><label class="form-label">Brand / manufacturer</label>
                            <input name="brand" value="{{ old('brand', $part->brand) }}" class="form-control" maxlength="100"></div>
                        <div class="col-md-6"><label class="form-label">Model / part number</label>
                            <input name="part_number" value="{{ old('part_number', $part->part_number) }}" class="form-control" maxlength="100"></div>
                    @endunless
                    <div class="col-12"><label class="form-label">Size / specification</label>
                        <input name="specification" value="{{ old('specification', $part->specification) }}" class="form-control" maxlength="255" placeholder="e.g. Ø25 × 180 mm, EN8 steel"></div>
                    <div class="col-12"><label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control" maxlength="5000">{{ old('description', $part->description) }}</textarea></div>
                    <div class="col-12"><label class="form-label">Compatible cigarette machinery model(s)</label>
                        <select name="machinery_model_ids[]" class="form-select tom" multiple placeholder="Select models…">
                            @php($selModels = old('machinery_model_ids', $part->exists ? $part->machineryModels->pluck('id')->all() : []))
                            @foreach($models as $m)<option value="{{ $m->id }}" @selected(in_array($m->id, $selModels))>{{ $m->name }}</option>@endforeach
                        </select></div>
                </div>

                <div class="form-section-title">Stock control</div>
                <div class="row g-3">
                    @if($isCnc)
                        <div class="col-md-4"><label class="form-label">Final operation <span class="req">*</span>
                            <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" title="Only output of this operation can be completed into finished stock. Earlier operations are tracked as work in progress."></i></label>
                            <select name="final_operation_id" class="form-select @error('final_operation_id') is-invalid @enderror" required>
                                @foreach($operations as $o)<option value="{{ $o->id }}" @selected(old('final_operation_id', $part->final_operation_id ?? $defaultFinalOp) == $o->id)>{{ $o->name }}</option>@endforeach
                            </select></div>
                    @endif
                    <div class="col-md-4"><label class="form-label">Minimum stock level</label>
                        <input type="number" step="any" min="0" name="min_stock" value="{{ old('min_stock', $part->exists ? (float) $part->min_stock : 0) }}" class="form-control @error('min_stock') is-invalid @enderror">
                        @error('min_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    @if($part->exists)
                        <div class="col-md-4"><label class="form-label">Opening / current stock</label>
                            <div class="form-control-plaintext">{{ \App\Support\Qty::fmt($part->opening_stock) }} / <strong>{{ \App\Support\Qty::fmt($part->current_stock) }}</strong>
                            <div class="form-text">Change stock only through transactions or adjustments.</div></div></div>
                    @else
                        <div class="col-md-4"><label class="form-label">Opening stock</label>
                            <input type="number" step="any" min="0" name="opening_stock" value="{{ old('opening_stock', 0) }}" class="form-control @error('opening_stock') is-invalid @enderror">
                            @error('opening_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Posted to the ledger as an opening entry.</div></div>
                    @endif
                    @unless($isCnc)
                        <div class="col-md-4"><label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-select tom"><option value="">—</option>
                                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $part->supplier_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">Unit purchase cost</label>
                            <input type="number" step="0.01" min="0" name="unit_cost" value="{{ old('unit_cost', $part->unit_cost) }}" class="form-control @error('unit_cost') is-invalid @enderror"></div>
                        <div class="col-md-4"><label class="form-label">Currency</label>
                            <select name="currency" class="form-select @error('currency') is-invalid @enderror"><option value="">—</option>
                                @foreach(config('spims.currencies') as $cur)<option @selected(old('currency', $part->currency ?? \App\Models\AppSetting::get('default_currency')) === $cur)>{{ $cur }}</option>@endforeach</select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    @endunless
                    <div class="col-12">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $part->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Active (available for new transactions)</label></div>
                    </div>
                </div>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <div class="form-section-title mt-0">Images</div>
                <label class="form-label">Primary image @unless($part->exists)<span class="req">*</span>@endunless</label>
                <div id="primaryPreview" class="mb-2">
                    @if($part->exists && $part->imageUrl())<img src="{{ $part->thumbUrl() }}" class="thumb thumb-lg" alt="">@endif
                </div>
                <input type="file" name="primary_image" accept="image/jpeg,image/png,image/webp" class="form-control @error('primary_image') is-invalid @enderror" data-preview="#primaryPreview" @unless($part->exists) required @endunless>
                @error('primary_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">JPEG, PNG or WebP, max {{ round(config('spims.image_max_kb') / 1024, 1) }} MB. {{ $part->exists ? 'Uploading replaces the current primary image.' : 'Mandatory.' }}</div>

                <label class="form-label mt-3">Additional images</label>
                <input type="file" name="additional_images[]" accept="image/jpeg,image/png,image/webp" class="form-control @error('additional_images.*') is-invalid @enderror" multiple>
                @error('additional_images.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div></div>
            <div class="d-grid gap-2 mt-3">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Save {{ $isCnc ? 'part' : 'product' }}</button>
                <a href="{{ $part->exists ? route($base.'.show', $part) : route($base.'.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>
@endsection
