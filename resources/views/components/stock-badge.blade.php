@props(['part'])
@switch($part->stockStatus())
    @case('out')<span class="badge badge-soft-danger">Out of stock</span>@break
    @case('low')<span class="badge badge-soft-warning">Low stock</span>@break
    @default<span class="badge badge-soft-success">In stock</span>
@endswitch
