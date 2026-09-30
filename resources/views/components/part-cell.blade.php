@props(['part' => null, 'name' => null, 'sku' => null, 'meta' => null, 'href' => null, 'size' => ''])
@php
    $thumb = $part?->thumbUrl();
    $name ??= $part?->name;
    $sku ??= $part?->sku;
    $href ??= ($part && auth()->user()->can($part->isCnc() ? 'cnc.inventory.view' : 'imported.inventory.view')) ? route($part->routeBase().'.show', $part) : null;
@endphp
<div class="part-cell">
    @if($thumb)
        <img src="{{ $thumb }}" alt="" class="thumb {{ $size }}" loading="lazy">
    @else
        <span class="thumb {{ $size }} thumb-empty"><i class="bi bi-image"></i></span>
    @endif
    <div>
        <div class="name">@if($href)<a href="{{ $href }}">{{ $name }}</a>@else{{ $name }}@endif</div>
        <div class="meta">{{ $sku }}@if($meta) · {{ $meta }}@endif</div>
    </div>
</div>
