@extends('layouts.app')
@php($Q = \App\Support\Qty::class)
@section('title', 'Search')
@section('content')
<x-page-header title="Search" :subtitle="$q !== '' ? $count.' result(s) for “'.$q.'”' : 'Search parts, SKUs, part numbers, machines and transaction references.'" />
<form method="get" class="mb-4" role="search"><div class="input-group input-group-lg"><span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="e.g. BRG-6204, M-7, IMP-OUT-2026-000012, solenoid" autofocus minlength="2"><button class="btn btn-primary">Search</button></div></form>
@if($q !== '' && mb_strlen($q) < 2)<div class="alert alert-info">Enter at least 2 characters.</div>@endif
@if($q !== '' && $count === 0 && mb_strlen($q) >= 2)<x-empty icon="bi-search" message="Nothing found. Try a SKU, part number or reference number." />@endif

@if(! empty($results['parts']) && $results['parts']->count())
<div class="card mb-3"><div class="card-header"><span class="card-title">Parts &amp; products</span><span class="badge badge-soft-primary">{{ $results['parts']->count() }}</span></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Part</th><th>Inventory</th><th>Category</th><th>Part no.</th><th class="num">Stock</th><th>Status</th></tr></thead><tbody>
@foreach($results['parts'] as $p)<tr><td><x-part-cell :part="$p" /></td><td><span class="badge {{ $p->isCnc() ? 'badge-soft-info' : 'badge-soft-primary' }}">{{ $p->isCnc() ? 'CNC' : 'Imported' }}</span></td>
    <td>{{ $p->category?->name }}</td><td class="small">{{ $p->part_number ?: '—' }}</td><td class="num fw-semibold">{{ $Q::fmt($p->current_stock) }} <small class="text-muted">{{ $p->unit?->symbol }}</small></td><td><x-stock-badge :part="$p" /></td></tr>@endforeach
</tbody></table></div></div>
@endif
<div class="row g-3">
@if(! empty($results['machines']) && $results['machines']->count())
<div class="col-md-6"><div class="card h-100"><div class="card-header"><span class="card-title">CNC machines</span></div><ul class="list-group list-group-flush">
@foreach($results['machines'] as $m)<a href="{{ route('cnc.machines.show', $m) }}" class="list-group-item list-group-item-action"><strong>{{ $m->code }}</strong> {{ $m->name !== $m->code ? '— '.$m->name : '' }} <span class="badge badge-soft-secondary">{{ $m->status }}</span></a>@endforeach
</ul></div></div>@endif
@if(! empty($results['production']) && $results['production']->count())
<div class="col-md-6"><div class="card h-100"><div class="card-header"><span class="card-title">Production entries</span></div><ul class="list-group list-group-flush">
@foreach($results['production'] as $r)<a href="{{ route('cnc.production.show', $r) }}" class="list-group-item list-group-item-action"><span class="ref">{{ $r->reference_no }}</span> · {{ $r->production_date->format('d M Y') }} · {{ $r->machine->code }} · {{ $r->part_sku }}</a>@endforeach
</ul></div></div>@endif
@if(! empty($results['completions']) && $results['completions']->count())
<div class="col-md-6"><div class="card h-100"><div class="card-header"><span class="card-title">Completions</span></div><ul class="list-group list-group-flush">
@foreach($results['completions'] as $c)<a href="{{ route('cnc.completions.show', $c) }}" class="list-group-item list-group-item-action"><span class="ref">{{ $c->reference_no }}</span> · {{ $c->part_sku }} · {{ $c->status }}</a>@endforeach
</ul></div></div>@endif
@if(! empty($results['transactions']) && $results['transactions']->count())
<div class="col-md-6"><div class="card h-100"><div class="card-header"><span class="card-title">Imported transactions</span></div><ul class="list-group list-group-flush">
@foreach($results['transactions'] as $t)<a href="{{ route('imported.transactions.show', $t) }}" class="list-group-item list-group-item-action"><span class="ref">{{ $t->reference_no }}</span> · {{ $t->typeLabel() }} · {{ $t->part_sku }} {{ $t->document_reference ? '· '.$t->document_reference : '' }}</a>@endforeach
</ul></div></div>@endif
@if(! empty($results['assemblies']) && $results['assemblies']->count())
<div class="col-md-6"><div class="card h-100"><div class="card-header"><span class="card-title">Assemblies</span></div><ul class="list-group list-group-flush">
@foreach($results['assemblies'] as $a)<a href="{{ route('imported.assemblies.show', $a) }}" class="list-group-item list-group-item-action"><span class="ref">{{ $a->reference_no }}</span> · {{ $a->name }}</a>@endforeach
</ul></div></div>@endif
</div>
@endsection
