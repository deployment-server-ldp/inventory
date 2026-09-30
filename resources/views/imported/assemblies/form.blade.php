@extends('layouts.app')
@section('title', $assembly->exists ? 'Edit assembly' : 'New assembly')
@section('content')
<x-page-header :title="$assembly->exists ? 'Edit '.$assembly->reference_no : 'New machine assembly'" :crumbs="['Assemblies' => route('imported.assemblies.index'), ($assembly->exists ? 'Edit' : 'New') => null]" />
<div class="card" style="max-width:860px"><div class="card-body">
<form method="post" action="{{ $assembly->exists ? route('imported.assemblies.update', $assembly) : route('imported.assemblies.store') }}">@csrf @if($assembly->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Reference number @if($assembly->exists)<span class="req">*</span>@endif</label><input name="reference_no" value="{{ old('reference_no', $assembly->reference_no) }}" class="form-control" maxlength="40" placeholder="Auto (ASM-YYYY-######)"></div>
        <div class="col-md-8"><label class="form-label">Machine / project name <span class="req">*</span></label><input name="name" value="{{ old('name', $assembly->name) }}" class="form-control" required maxlength="191"></div>
        <div class="col-md-6"><label class="form-label">Machinery model</label><select name="machinery_model_id" class="form-select tom"><option value="">—</option>
            @foreach($models as $m)<option value="{{ $m->id }}" @selected(old('machinery_model_id', $assembly->machinery_model_id) == $m->id)>{{ $m->name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Customer / owner</label><input name="customer" value="{{ old('customer', $assembly->customer) }}" class="form-control" maxlength="150"></div>
        <div class="col-md-4"><label class="form-label">Status <span class="req">*</span></label><select name="status" class="form-select">
            @foreach(\App\Models\MachineAssembly::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $assembly->status) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Start date</label><input type="date" name="start_date" value="{{ old('start_date', $assembly->start_date?->toDateString()) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Target date</label><input type="date" name="target_date" value="{{ old('target_date', $assembly->target_date?->toDateString()) }}" class="form-control"></div>
        <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" rows="3" class="form-control">{{ old('remarks', $assembly->remarks) }}</textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save</button><a href="{{ $assembly->exists ? route('imported.assemblies.show', $assembly) : route('imported.assemblies.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
</form></div></div>
@endsection
