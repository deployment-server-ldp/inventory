@props(['col', 'label', 'class' => ''])
@php
    $current = request('sort');
    $dir = request('dir', 'asc') === 'desc' ? 'desc' : 'asc';
    $next = ($current === $col && $dir === 'asc') ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort' => $col, 'dir' => $next, 'page' => null]);
@endphp
<th class="{{ $class }}"><a class="sort" href="{{ $url }}">{{ $label }}
    @if($current === $col)<i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill"></i>@else<i class="bi bi-arrow-down-up opacity-25"></i>@endif
</a></th>
