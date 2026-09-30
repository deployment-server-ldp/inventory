@extends('layouts.app')
@section('title', 'System health')
@section('content')
<x-page-header title="System health" subtitle="Environment checks and stock-ledger reconciliation.">
    <a href="{{ route('admin.system.logs') }}" class="btn btn-outline-secondary"><i class="bi bi-file-text me-1"></i>Error log</a>
</x-page-header>
<div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-header"><span class="card-title">Environment</span></div>
        <ul class="list-group list-group-flush">
        @foreach($checks as $c)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="bi {{ $c['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} me-2"></i>{{ $c['label'] }}</span>
                <small class="text-muted">{{ $c['detail'] }}</small></li>
        @endforeach
        </ul></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-header"><span class="card-title">Stock ledger reconciliation</span></div><div class="card-body">
        @if(! $issues)
            <div class="text-success"><i class="bi bi-shield-check me-1"></i>All part balances equal their ledger totals; no inventory mixing detected.</div>
        @else
            <div class="text-danger mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ count($issues) }} discrepancy(ies):</div>
            <table class="table table-sm"><thead><tr><th>SKU</th><th>Inv.</th><th class="num">Stored</th><th class="num">Ledger</th></tr></thead><tbody>
                @foreach($issues as $i)<tr><td>{{ $i['sku'] }}</td><td>{{ $i['type'] }}</td><td class="num">{{ $i['stored'] }}</td><td class="num">{{ $i['ledger'] }}</td></tr>@endforeach
            </tbody></table>
        @endif
        <p class="small text-muted mt-3 mb-0">CLI equivalent: <code>php artisan stock:verify</code></p>
    </div></div></div>
</div>
@endsection
