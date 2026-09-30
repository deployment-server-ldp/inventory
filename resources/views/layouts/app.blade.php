<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ \App\Models\AppSetting::get('company_name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tom-select/tom-select.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body>
<div class="app-shell" id="appShell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
                <span class="brand-mark"><i class="bi bi-gear-fill"></i></span>
                <span class="brand-text">SPIMS<small>{{ \Illuminate\Support\Str::limit(\App\Models\AppSetting::get('company_name'), 26) }}</small></span>
            </a>
        </div>
        <nav class="sidebar-nav">
            @foreach(\App\Support\Navigation::items(auth()->user()) as $section)
                @if($section['label'])<div class="nav-section">{{ $section['label'] }}</div>@endif
                @foreach($section['items'] as $item)
                    <a href="{{ $item['url'] }}" class="nav-item {{ $item['active'] ? 'active' : '' }}" title="{{ $item['label'] }}">
                        <i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-icon" id="sidebarToggle" type="button" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
            <form class="global-search d-none d-md-flex" action="{{ route('search') }}" method="get" role="search">
                <i class="bi bi-search"></i>
                <input type="search" name="q" class="form-control" placeholder="Search parts, SKU, machines, references…" value="{{ request()->routeIs('search') ? request('q') : '' }}" aria-label="Global search">
            </form>
            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="{{ route('search') }}" class="btn btn-icon d-md-none" aria-label="Search"><i class="bi bi-search"></i></a>
                <div class="dropdown">
                    <button class="btn btn-user dropdown-toggle" data-bs-toggle="dropdown" type="button">
                        <span class="avatar">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="d-none d-sm-inline text-start lh-sm">
                            <span class="d-block fw-semibold">{{ auth()->user()->name }}</span>
                            <small class="text-muted">{{ auth()->user()->role?->display_name }}</small>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="{{ route('password.change') }}"><i class="bi bi-key me-2"></i>Change password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="{{ route('logout') }}">@csrf
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content">
            @include('partials.flash')
            @yield('content')
        </main>
        <footer class="app-footer">© {{ date('Y') }} {{ \App\Models\AppSetting::get('company_name') }} · Spare Parts Manufacturing &amp; Inventory Management</footer>
    </div>
</div>

@stack('modals')
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/tom-select/tom-select.complete.min.js') }}"></script>
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
