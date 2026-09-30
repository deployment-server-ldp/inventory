@extends('layouts.app')
@section('title', 'Reports Center')
@section('content')
<x-page-header title="Reports Center" subtitle="All reports support date filters, search, sorting, pagination and Excel / CSV / PDF export of exactly what you filtered." />
@php($labels = ['cnc' => ['CNC Manufacturing', 'bi-gear-wide-connected'], 'imported' => ['Imported Inventory', 'bi-globe2'], 'both' => ['Cross-inventory', 'bi-intersect']])
@foreach(['cnc', 'imported', 'both'] as $g)
    @continue(! isset($groups[$g]))
    <h2 class="h6 text-uppercase text-muted mt-4 mb-2"><i class="bi {{ $labels[$g][1] }} me-1"></i>{{ $labels[$g][0] }}</h2>
    <div class="row g-3">
        @foreach($groups[$g] as $r)
            <div class="col-md-6 col-xl-4"><a href="{{ route('reports.show', $r->key) }}" class="kpi"><div class="kpi-icon"><i class="bi bi-file-earmark-bar-graph"></i></div>
                <div class="fw-semibold text-dark pe-5">{{ $r->title }}</div><div class="small text-muted mt-1">{{ $r->description }}</div></a></div>
        @endforeach
    </div>
@endforeach
@endsection
