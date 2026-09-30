@extends('layouts.app')
@section('title', 'Company settings')
@section('content')
<x-page-header title="Company settings" />
<div class="card" style="max-width:640px"><div class="card-body">
<form method="post" action="{{ route('admin.company.update') }}">@csrf @method('PUT')
    <div class="mb-3"><label class="form-label">Company name <span class="req">*</span></label><input name="company_name" value="{{ old('company_name', $values['company_name']) }}" class="form-control" required maxlength="120"></div>
    <div class="mb-3"><label class="form-label">Address (shown on PDF reports)</label><input name="company_address" value="{{ old('company_address', $values['company_address']) }}" class="form-control" maxlength="255"></div>
    <div class="mb-4"><label class="form-label">Default purchase currency</label><select name="default_currency" class="form-select">
        @foreach(config('spims.currencies') as $c)<option @selected(old('default_currency', $values['default_currency']) === $c)>{{ $c }}</option>@endforeach</select></div>
    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save</button>
</form></div></div>
@endsection
