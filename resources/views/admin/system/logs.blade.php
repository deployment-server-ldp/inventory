@extends('layouts.app')
@section('title', 'Error log')
@section('content')
<x-page-header title="Application error log" subtitle="Last ~200 KB of the selected log file." :crumbs="['System health' => route('admin.system.health'), 'Logs' => null]" />
<form class="filter-bar row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label">Log file</label><select name="file" class="form-select form-select-sm" data-autosubmit>
        @forelse($files as $f)<option @selected($f === $current)>{{ $f }}</option>@empty<option>No log files</option>@endforelse</select></div>
</form>
<div class="card"><div class="card-body p-0">
    @if($content)<pre class="small m-0 p-3" style="max-height:70vh;overflow:auto;white-space:pre-wrap;background:#0f1f3d;color:#e2e8f0;border-radius:10px">{{ $content }}</pre>
    @else<x-empty icon="bi-emoji-smile" message="The log is empty." />@endif
</div></div>
@endsection
