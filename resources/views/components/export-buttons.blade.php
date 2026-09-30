@props(['route', 'params' => []])
<div class="btn-group">
    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach(['xlsx' => ['Excel (.xlsx)', 'bi-file-earmark-excel'], 'csv' => ['CSV', 'bi-filetype-csv'], 'pdf' => ['PDF', 'bi-file-earmark-pdf']] as $fmt => [$label, $icon])
            <li><a class="dropdown-item" href="{{ route($route, array_merge($params, request()->except(['page', 'format']), ['format' => $fmt])) }}"><i class="bi {{ $icon }} me-2"></i>{{ $label }}</a></li>
        @endforeach
    </ul>
</div>
