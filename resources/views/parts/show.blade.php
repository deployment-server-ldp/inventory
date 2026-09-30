@extends('layouts.app')
@php($isCnc = $type === 'cnc')
@php($base = $isCnc ? 'cnc.parts' : 'imported.products')
@php($Q = \App\Support\Qty::class)
@section('title', $part->sku)
@section('content')
<x-page-header :title="$part->name" :subtitle="$part->sku.' · '.($part->category?->name)" :crumbs="[($isCnc ? 'CNC Parts' : 'Imported Products') => route($base.'.index'), $part->sku => null]">
    @if($canManage)<a href="{{ route($base.'.edit', $part) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>@endif
    <a href="{{ route($isCnc ? 'cnc.stock.ledger' : 'imported.stock.ledger', ['part_id' => $part->id, 'period' => 'year']) }}" class="btn btn-outline-primary"><i class="bi bi-journal-text me-1"></i>Stock ledger</a>
    @if(! $isCnc && auth()->user()->can('imported.in.create'))<a href="{{ route('imported.in.create', ['part' => $part->id]) }}" class="btn btn-success"><i class="bi bi-box-arrow-in-down me-1"></i>IN</a>@endif
    @if(! $isCnc && auth()->user()->can('imported.out.create'))<a href="{{ route('imported.out.create', ['part' => $part->id]) }}" class="btn btn-danger"><i class="bi bi-box-arrow-up me-1"></i>OUT</a>@endif
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-kpi label="Current stock" :value="$Q::fmt($part->current_stock).' '.$part->unit?->symbol" icon="bi-box-seam" :variant="['out' => 'danger', 'low' => 'warning'][$part->stockStatus()] ?? 'success'" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Minimum level" :value="$Q::fmt($part->min_stock)" icon="bi-exclamation-diamond" variant="muted" /></div>
    <div class="col-6 col-lg-3"><x-kpi label="Opening stock" :value="$Q::fmt($part->opening_stock)" icon="bi-flag" variant="muted" /></div>
    @if($isCnc)
        <div class="col-6 col-lg-3"><x-kpi label="Awaiting completion" :value="$Q::fmt($awaiting)" icon="bi-hourglass-split" variant="warning" tip="Final-operation output not yet submitted for QC completion"
            :href="auth()->user()->can('cnc.completion.create') ? route('cnc.completions.create', ['part' => $part->id]) : null" /></div>
    @else
        <div class="col-6 col-lg-3"><x-kpi label="Stock value" :value="$part->unit_cost !== null ? $Q::money((float) $part->unit_cost * (float) $part->current_stock, $part->currency) : '—'" icon="bi-cash-stack" variant="muted" :sub="$part->unit_cost !== null ? 'at '.$Q::money($part->unit_cost, $part->currency).' / unit' : 'No unit cost recorded'" /></div>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                @if($part->imageUrl())
                    <a href="{{ $part->imageUrl() }}" target="_blank"><img src="{{ $part->imageUrl() }}" alt="{{ $part->name }}" class="img-fluid rounded border w-100" style="max-height:320px;object-fit:contain;background:#fff"></a>
                @else
                    <div class="empty-state"><i class="bi bi-image"></i>No image</div>
                @endif
                @if($part->images->count() > 1 || $canManage)
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @foreach($part->images as $img)
                            <div class="position-relative text-center">
                                <a href="{{ $img->url() }}" target="_blank"><img src="{{ $img->url('thumb') }}" class="thumb thumb-lg {{ $img->is_primary ? 'border-primary border-2' : '' }}" alt=""></a>
                                @if($canManage)
                                    <div class="d-flex justify-content-center gap-1 mt-1">
                                        @if($img->is_primary)<span class="badge badge-soft-primary">Primary</span>@else
                                            <form method="post" action="{{ route($base.'.images.primary', [$part, $img]) }}">@csrf<button class="btn btn-sm btn-link p-0" title="Make primary"><i class="bi bi-star"></i></button></form>
                                            <form method="post" action="{{ route($base.'.images.destroy', [$part, $img]) }}" data-confirm="Remove this image?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0" title="Remove"><i class="bi bi-trash"></i></button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($canManage)
                        <form method="post" enctype="multipart/form-data" action="{{ route($base.'.images.store', $part) }}" class="mt-3 border-top pt-3">
                            @csrf
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mb-2" required>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check"><input type="checkbox" name="primary" value="1" class="form-check-input" id="asPrimary"><label for="asPrimary" class="form-check-label small">Replace primary</label></div>
                                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i>Upload</button>
                            </div>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><span class="card-title">Details</span> <span><x-stock-badge :part="$part" /> <x-status-badge :active="$part->is_active" /></span></div>
            <div class="card-body">
                <dl class="row detail mb-0">
                    <div class="col-sm-4"><dt>SKU</dt><dd class="ref">{{ $part->sku }}</dd></div>
                    <div class="col-sm-4"><dt>Category</dt><dd>{{ $part->category?->name }}</dd></div>
                    <div class="col-sm-4"><dt>Unit</dt><dd>{{ $part->unit?->name }} ({{ $part->unit?->symbol }})</dd></div>
                    @if($isCnc)
                        <div class="col-sm-4"><dt>Final operation</dt><dd>{{ $part->finalOperation?->name ?? 'Highest active operation' }}</dd></div>
                    @else
                        <div class="col-sm-4"><dt>Brand</dt><dd>{{ $part->brand ?: '—' }}</dd></div>
                        <div class="col-sm-4"><dt>Model / part no.</dt><dd>{{ $part->part_number ?: '—' }}</dd></div>
                        <div class="col-sm-4"><dt>Supplier</dt><dd>{{ $part->supplier?->name ?? '—' }}</dd></div>
                        <div class="col-sm-4"><dt>Unit cost</dt><dd>{{ $part->unit_cost !== null ? $Q::money($part->unit_cost, $part->currency) : '—' }}</dd></div>
                    @endif
                    <div class="col-sm-8"><dt>Specification</dt><dd>{{ $part->specification ?: '—' }}</dd></div>
                    <div class="col-12"><dt>Compatible machinery</dt><dd>
                        @forelse($part->machineryModels as $m)<span class="badge badge-soft-primary me-1">{{ $m->name }}</span>@empty — @endforelse</dd></div>
                    <div class="col-12"><dt>Description</dt><dd style="white-space:pre-line">{{ $part->description ?: '—' }}</dd></div>
                    <div class="col-sm-6"><dt>Created</dt><dd>{{ $part->created_at?->format('d M Y H:i') }} by {{ $part->creator?->name ?? 'system' }}</dd></div>
                </dl>
            </div>
        </div>

        @if($isCnc)
            <div class="card mb-3">
                <div class="card-header"><span class="card-title">Operation progress (work in progress, not stock)</span>
                    <a href="{{ route('cnc.progress', ['q' => $part->sku]) }}" class="small">All parts</a></div>
                <div class="card-body">
                    @forelse($progress as $op)
                        <div class="d-flex justify-content-between"><span>{{ $op['name'] }} @if($op['operation_id'] == $part->final_operation_id)<span class="badge badge-soft-primary">final</span>@endif</span><strong>{{ $Q::fmt($op['quantity']) }}</strong></div>
                        <div class="progress mb-2"><div class="progress-bar" style="width: {{ $progress[0]['quantity'] > 0 ? min(100, $op['quantity'] / max(array_column($progress, 'quantity')) * 100) : 0 }}%"></div></div>
                    @empty
                        <p class="text-muted mb-0">No production recorded yet.</p>
                    @endforelse
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><span class="card-title">Recent stock movements</span></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Date</th><th>Reference</th><th>Type</th><th class="num">In</th><th class="num">Out</th><th class="num">Balance</th><th>By</th></tr></thead>
                    <tbody>
                    @forelse($recent as $t)
                        <tr class="{{ $t->is_reversed ? 'text-decoration-line-through text-muted' : '' }}">
                            <td>{{ $t->transaction_date->format('d M Y') }}</td>
                            <td class="ref">@if(! $isCnc)<a href="{{ route('imported.transactions.show', $t) }}">{{ $t->reference_no }}</a>@else{{ $t->reference_no }}@endif</td>
                            <td>{{ $t->typeLabel() }}</td>
                            <td class="num text-success">{{ (float) $t->quantity_in ? $Q::fmt($t->quantity_in) : '' }}</td>
                            <td class="num text-danger">{{ (float) $t->quantity_out ? $Q::fmt($t->quantity_out) : '' }}</td>
                            <td class="num fw-semibold">{{ $Q::fmt($t->balance_after) }}</td>
                            <td class="small">{{ $t->creator?->name }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="7" message="No stock movements yet." />
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
