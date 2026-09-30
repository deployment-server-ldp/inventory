@extends('layouts.app')
@section('title', ($row->exists ? 'Edit ' : 'Add ').$def['singular'])
@section('content')
<x-page-header :title="($row->exists ? 'Edit ' : 'Add ').$def['singular']" :crumbs="['Settings' => null, $def['title'] => route('settings.index', $def['key']), ($row->exists ? 'Edit' : 'Add') => null]" />
<div class="card" style="max-width: 760px">
    <div class="card-body">
        <form method="post" action="{{ $row->exists ? route('settings.update', [$def['key'], $row->id]) : route('settings.store', $def['key']) }}">
            @csrf
            @if($row->exists) @method('PUT') @endif
            <div class="row g-3">
            @foreach($def['fields'] as $name => $f)
                @php($value = old($name, $row->exists ? $row->{$name} : ($f['default'] ?? null)))
                @php($required = in_array('required', is_callable($f['rules']) ? ($f['rules'])(null) : $f['rules'], true))
                <div class="{{ $f['type'] === 'textarea' ? 'col-12' : 'col-md-6' }}">
                    @if($f['type'] === 'checkbox')
                        <div class="form-check mt-4">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input class="form-check-input" type="checkbox" name="{{ $name }}" id="f_{{ $name }}" value="1" @checked($value)>
                            <label for="f_{{ $name }}" class="form-check-label">{{ $f['label'] }}</label>
                        </div>
                    @else
                        <label class="form-label" for="f_{{ $name }}">{{ $f['label'] }} @if($required)<span class="req">*</span>@endif</label>
                        @if($f['type'] === 'select')
                            <select name="{{ $name }}" id="f_{{ $name }}" class="form-select @error($name) is-invalid @enderror">
                                @foreach($f['options'] as $k => $label)<option value="{{ $k }}" @selected((string) $value === (string) $k)>{{ $label }}</option>@endforeach
                            </select>
                        @elseif($f['type'] === 'textarea')
                            <textarea name="{{ $name }}" id="f_{{ $name }}" rows="3" class="form-control @error($name) is-invalid @enderror">{{ $value }}</textarea>
                        @else
                            <input type="{{ $f['type'] }}" name="{{ $name }}" id="f_{{ $name }}" value="{{ $value }}" class="form-control @error($name) is-invalid @enderror" @if($required) required @endif>
                        @endif
                        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>
            @endforeach
            @if($def['toggle'] === 'is_active')
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="f_active" @checked(old('is_active', $row->exists ? $row->is_active : true))>
                        <label class="form-check-label" for="f_active">Active</label>
                    </div>
                </div>
            @endif
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Save</button>
                <a href="{{ route('settings.index', $def['key']) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
