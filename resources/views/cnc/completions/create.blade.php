@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Record completion')
@section('content')
<x-page-header title="Record production completion" subtitle="Submit finished, inspected quantities of a part. Accepted quantity enters CNC stock after approval." :crumbs="['Completions' => route('cnc.completions.index'), 'New' => null]" />
<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="{{ route('cnc.completions.store') }}" class="card" data-part-scope>
            @csrf <x-idempotency />
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Completion date <span class="req">*</span></label>
                        <input type="date" name="completion_date" value="{{ old('completion_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required></div>
                    <div class="col-md-8"><label class="form-label">Part <span class="req">*</span></label>
                        <select name="spare_part_id" id="partPicker" required data-part-picker data-url="{{ route('lookup.parts', 'cnc') }}" placeholder="Search part…" @if($selected) data-selected="{{ json_encode($selected) }}" @endif></select></div>
                    <div class="col-12">
                        <div class="part-preview">
                            <div data-part-field="image"><div class="placeholder-img"><i class="bi bi-image"></i></div></div>
                            <div class="small flex-grow-1">
                                <div class="fw-semibold" data-part-field="name">—</div>
                                <div>Final operation: <span data-part-field="final_operation">—</span></div>
                                <div>Current finished stock: <span data-part-field="stock">—</span> <span data-part-field="unit"></span></div>
                                <div class="mt-1">Awaiting completion: <span class="stock-pill text-warning" id="poolQty">{{ $selected ? $Q::fmt($selected['awaiting']) : '—' }}</span></div>
                                <div id="poolOps" class="text-muted"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4"><label class="form-label">Accepted quantity <span class="req">*</span></label>
                        <input type="number" step="any" min="0" name="quantity_accepted" value="{{ old('quantity_accepted') }}" class="form-control @error('quantity_accepted') is-invalid @enderror" required></div>
                    <div class="col-md-4"><label class="form-label">Rejected quantity</label>
                        <input type="number" step="any" min="0" name="quantity_rejected" value="{{ old('quantity_rejected', 0) }}" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Inspected (total)</label><div class="form-control-plaintext fw-bold" id="inspected">0</div></div>
                    <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" rows="2" class="form-control" maxlength="1000">{{ old('remarks') }}</textarea></div>
                    @if($canApprove)
                        <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" name="approve_now" value="1" id="approveNow" @checked(old('approve_now', true))>
                            <label for="approveNow" class="form-check-label">Approve now as quality inspector (post accepted quantity to stock immediately)</label></div></div>
                    @endif
                </div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Submit completion</button></div>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-header"><span class="card-title">Parts awaiting completion</span></div>
            <ul class="list-group list-group-flush">
                @forelse($waiting as $w)
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('cnc.completions.create', ['part' => $w['part']->id]) }}">
                        <x-part-cell :part="$w['part']" size="thumb-sm" :href="false" /><span class="badge badge-soft-warning fs-6">{{ $Q::fmt($w['awaiting']) }}</span></a>
                @empty <li class="list-group-item"><x-empty icon="bi-check2-all" message="Nothing is waiting for completion." /></li> @endforelse
            </ul></div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    const form = document.querySelector('form[data-part-scope]');
    const acc = form.querySelector('[name=quantity_accepted]'), rej = form.querySelector('[name=quantity_rejected]');
    const upd = () => { document.getElementById('inspected').textContent = ((parseFloat(acc.value) || 0) + (parseFloat(rej.value) || 0)).toLocaleString(); };
    acc.addEventListener('input', upd); rej.addEventListener('input', upd); upd();
    form.addEventListener('part:selected', (e) => {
        const pool = document.getElementById('poolQty'), ops = document.getElementById('poolOps');
        if (!e.detail) { pool.textContent = '—'; ops.textContent = ''; return; }
        pool.textContent = '…';
        fetch('{{ url('/lookup/completion-pool') }}/' + e.detail.id, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => {
            pool.textContent = j.awaiting;
            ops.textContent = j.progress.map(p => p.name + ': ' + p.quantity).join(' · ');
        });
    });
})();
</script>
@endpush
@endsection
