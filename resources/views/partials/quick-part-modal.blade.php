{{-- Quick-create part without leaving (or losing) the current form. Params: $type, $target (picker selector) --}}
@php($qcCategories = \App\Models\PartCategory::forType($type)->active()->orderBy('name')->get())
@php($qcUnits = \App\Models\Unit::active()->orderBy('name')->get())
@php($qcOps = \App\Models\Operation::active()->ordered()->get())
<div class="modal fade" id="quickPartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" method="post" enctype="multipart/form-data" action="{{ route($type === 'cnc' ? 'cnc.parts.quick-store' : 'imported.products.quick-store') }}" data-quick-create="{{ $target }}">
            @csrf
            <input type="hidden" name="is_active" value="1">
            <div class="modal-header"><h5 class="modal-title">New {{ $type === 'cnc' ? 'CNC part' : 'imported product' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="quick-errors"></div>
                <p class="small text-muted">Your current form stays as it is. The new item will be selected automatically.</p>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">SKU <span class="req">*</span></label><input name="sku" class="form-control" required maxlength="60"></div>
                    <div class="col-md-8"><label class="form-label">Name <span class="req">*</span></label><input name="name" class="form-control" required maxlength="191"></div>
                    <div class="col-md-4"><label class="form-label">Category <span class="req">*</span></label>
                        <select name="category_id" class="form-select" required><option value="">Select…</option>@foreach($qcCategories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Unit <span class="req">*</span></label>
                        <select name="unit_id" class="form-select" required>@foreach($qcUnits as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                    @if($type === 'cnc')
                        <div class="col-md-4"><label class="form-label">Final operation <span class="req">*</span></label>
                            <select name="final_operation_id" class="form-select" required>@foreach($qcOps as $o)<option value="{{ $o->id }}" @selected($loop->last)>{{ $o->name }}</option>@endforeach</select></div>
                    @else
                        <div class="col-md-4"><label class="form-label">Model / part no.</label><input name="part_number" class="form-control" maxlength="100"></div>
                        <div class="col-md-6"><label class="form-label">Brand</label><input name="brand" class="form-control" maxlength="100"></div>
                    @endif
                    <div class="col-md-6"><label class="form-label">Size / specification</label><input name="specification" class="form-control" maxlength="255"></div>
                    <div class="col-md-6"><label class="form-label">Minimum stock level</label><input type="number" step="any" min="0" name="min_stock" value="0" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Primary image <span class="req">*</span></label>
                        <input type="file" name="primary_image" accept="image/jpeg,image/png,image/webp" class="form-control" required data-preview="#quickPreview"></div>
                    <div class="col-12" id="quickPreview"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create &amp; select</button></div>
        </form>
    </div>
</div>
