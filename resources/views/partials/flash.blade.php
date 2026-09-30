@foreach(['success' => 'check-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'] as $type => $icon)
    @if(session($type))
        <div class="alert alert-{{ $type }} alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-{{ $icon }} mt-1"></i><div>{{ session($type) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="d-flex gap-2"><i class="bi bi-x-octagon mt-1"></i>
            <div>
                @if($errors->count() === 1)
                    {{ $errors->first() }}
                @else
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                @endif
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
