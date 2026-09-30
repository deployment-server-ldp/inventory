@props(['title', 'subtitle' => null, 'crumbs' => []])
<div class="page-header">
    <div>
        @if($crumbs)
            <nav aria-label="breadcrumb"><ol class="breadcrumb">
                @foreach($crumbs as $label => $url)
                    <li class="breadcrumb-item">@if($url)<a href="{{ $url }}">{{ $label }}</a>@else{{ $label }}@endif</li>
                @endforeach
            </ol></nav>
        @endif
        <h1>{{ $title }}</h1>
        @if($subtitle)<p class="subtitle">{{ $subtitle }}</p>@endif
    </div>
    @if(trim($slot))<div class="d-flex flex-wrap gap-2 no-print">{{ $slot }}</div>@endif
</div>
