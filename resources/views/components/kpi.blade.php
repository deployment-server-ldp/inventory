@props(['label', 'value', 'icon' => 'bi-graph-up', 'href' => null, 'variant' => 'primary', 'sub' => null, 'tip' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="kpi kpi-{{ $variant }}" @if($tip) data-bs-toggle="tooltip" title="{{ $tip }}" @endif>
    <div class="kpi-icon"><i class="bi {{ $icon }}"></i></div>
    <div class="kpi-label pe-5">{{ $label }}</div>
    <div class="kpi-value">{{ $value }}</div>
    @if($sub)<div class="kpi-sub">{{ $sub }}</div>@endif
</{{ $tag }}>
