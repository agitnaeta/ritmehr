<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Portal Karyawan') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/line-awesome@1.3.0/dist/line-awesome/css/line-awesome.min.css" rel="stylesheet">

    {{-- Dimuat SETELAH Bootstrap: portal.css menimpa variabel Bootstrap
         dengan token proyek, sehingga urutannya menentukan. --}}
    @vite('resources/css/portal.css')
</head>
<body>

{{-- Bidang biru: memuat navigasi mengapung dan judul halaman.
     Markup di dalam <nav> sengaja tidak diubah agar collapse dan dropdown
     Bootstrap tetap berjalan tanpa JavaScript tambahan. --}}
<div class="portal-hero">
    <div class="container">
        {{-- App bar ringkas untuk mobile (disembunyikan di desktop via CSS).
             Menyediakan brand + lonceng notifikasi + akses profil tanpa hamburger,
             karena navigasi utama pindah ke bottom tab bar. --}}
        <div class="portal-appbar">
            <div class="portal-appbar__row">
                <a class="portal-appbar__brand" href="{{ route('portal.dashboard') }}">
                    <i class="la la-user-clock"></i> Portal Karyawan
                </a>
                <div class="portal-appbar__actions">
                    <a class="portal-appbar__btn" href="{{ route('portal.notifications') }}" aria-label="Notifikasi">
                        <i class="la la-bell"></i>
                        @php $portalUnreadTop = app(\App\Services\NotificationService::class)->unreadCount(backpack_user()); @endphp
                        @if($portalUnreadTop > 0)
                            <span class="badge">{{ $portalUnreadTop > 99 ? '99+' : $portalUnreadTop }}</span>
                        @endif
                    </a>
                    @if(backpack_user()->hasAnyRole(['super_admin', 'hr_admin', 'manager']))
                        <a class="portal-appbar__btn" href="{{ backpack_url('dashboard') }}" aria-label="Admin">
                            <i class="la la-cogs"></i>
                        </a>
                    @endif
                    <a class="portal-appbar__btn" href="{{ route('portal.profile') }}" aria-label="Profil">
                        <i class="la la-user"></i>
                    </a>
                </div>
            </div>
        </div>

        <nav class="navbar navbar-expand-lg navbar-dark portal-nav">
    <div class="container-fluid">
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
                <li class="nav-item"><a class="nav-link" href="{{ route('portal.training.index') }}">Pelatihan</a></li>
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

        <h1 class="portal-title">@yield('heading', 'Portal Karyawan')</h1>
    </div>
</div>

{{-- Lembar putih, naik menimpa bidang biru. --}}
<div class="container portal-sheet">
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

    @yield('content')
</div>

{{-- Bottom tab bar (mobile only, disembunyikan di desktop via CSS).
     Lima menu utama. "Lainnya" mengarah ke profil sebagai pusat menu sekunder
     (kasbon, pelatihan, notifikasi bisa dijangkau dari sana / top-nav desktop). --}}
@php
    $rn = \Illuminate\Support\Facades\Route::currentRouteName();
    $isTab = fn($prefixes) => collect((array) $prefixes)->contains(fn($p) => str_starts_with($rn ?? '', $p));
@endphp
<nav class="portal-tabbar">
    <a class="tab {{ $rn === 'portal.dashboard' ? 'active' : '' }}" href="{{ route('portal.dashboard') }}">
        <i class="la la-home"></i>Beranda
    </a>
    <a class="tab {{ $isTab('portal.attendance') ? 'active' : '' }}" href="{{ route('portal.attendance') }}">
        <i class="la la-calendar-check"></i>Hadir
    </a>
    <a class="tab {{ $isTab('portal.salary') ? 'active' : '' }}" href="{{ route('portal.salary.index') }}">
        <i class="la la-money-check"></i>Gaji
    </a>
    <a class="tab {{ $isTab('portal.leave') ? 'active' : '' }}" href="{{ route('portal.leave.index') }}">
        <i class="la la-plane-departure"></i>Cuti
    </a>
    <a class="tab {{ $isTab(['portal.loan','portal.training','portal.profile','portal.notifications']) ? 'active' : '' }}" href="{{ route('portal.profile') }}">
        <i class="la la-ellipsis-h"></i>Lainnya
    </a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
