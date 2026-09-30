@extends('layouts.app')
@section('title', 'Activity detail')
@section('content')
<x-page-header :title="$log->action" :subtitle="$log->created_at->format('d M Y H:i:s')" :crumbs="['Activity log' => route('admin.activity.index'), '#'.$log->id => null]" />
<div class="card mb-3"><div class="card-body"><dl class="row detail mb-0">
    <div class="col-md-3"><dt>User</dt><dd>{{ $log->user?->name ?? $log->user_name ?? 'system' }}</dd></div>
    <div class="col-md-3"><dt>Module</dt><dd>{{ $log->module ?? '—' }}</dd></div>
    <div class="col-md-3"><dt>Reference</dt><dd class="ref">{{ $log->reference ?? '—' }}</dd></div>
    <div class="col-md-3"><dt>IP address</dt><dd>{{ $log->ip_address ?? '—' }}</dd></div>
    <div class="col-12"><dt>Description</dt><dd>{{ $log->description }}</dd></div>
    <div class="col-12"><dt>User agent</dt><dd class="small">{{ $log->user_agent ?? '—' }}</dd></div>
</dl></div></div>
<div class="row g-3">
    @foreach(['Before' => $log->old_values, 'After' => $log->new_values] as $label => $vals)
        <div class="col-md-6"><div class="card"><div class="card-header"><span class="card-title">{{ $label }}</span></div>
            <div class="card-body"><pre class="small mb-0" style="white-space:pre-wrap">{{ $vals ? json_encode($vals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre></div></div></div>
    @endforeach
</div>
@endsection
