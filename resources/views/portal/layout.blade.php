<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Portal Karyawan') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/line-awesome@1.3.0/dist/line-awesome/css/line-awesome.min.css" rel="stylesheet">
    <style>
        body { background: #f6f7fb; }
        .stat-card .value { font-size: 1.6rem; font-weight: 600; }
        .navbar-brand { font-weight: 600; }
        @media (max-width: 576px) { .stat-card .value { font-size: 1.3rem; } }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="{{ route('portal.dashboard') }}">
            <i class="la la-user-clock"></i> Portal Karyawan
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('portal.dashboard') }}">Beranda</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('portal.attendance') }}">Kehadiran</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('portal.salary.index') }}">Slip Gaji</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('portal.leave.index') }}">Cuti</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('portal.loan.index') }}">Kasbon</a></li>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link position-relative" href="{{ route('portal.notifications') }}">
                        <i class="la la-bell"></i>
                        @php $portalUnread = app(\App\Services\NotificationService::class)->unreadCount(backpack_user()); @endphp
                        @if($portalUnread > 0)
                            <span class="badge bg-danger">{{ $portalUnread > 99 ? '99+' : $portalUnread }}</span>
                        @endif
                    </a>
                </li>
                @if(backpack_user()->hasAnyRole(['super_admin', 'hr_admin', 'manager']))
                    <li class="nav-item">
                        <a class="nav-link" href="{{ backpack_url('dashboard') }}">
                            <i class="la la-cogs"></i> Admin
                        </a>
                    </li>
                @endif
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        {{ backpack_user()->name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('portal.profile') }}">Profil Saya</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="{{ backpack_url('logout') }}">Keluar</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <h4 class="mb-3">@yield('heading', 'Portal Karyawan')</h4>

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
