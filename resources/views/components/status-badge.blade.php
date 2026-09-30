@props(['active' => true, 'on' => 'Active', 'off' => 'Inactive'])
<span class="badge {{ $active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $active ? $on : $off }}</span>
