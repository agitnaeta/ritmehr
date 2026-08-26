{{-- M17 — Public careers portal.
     Memakai design system proyek yang sama dengan Portal Karyawan
     (resources/css/portal.css): bidang biru (hero) + lembar putih (sheet) +
     nav pil mengapung. Token, tombol navy, badge lembut, dan kontras AA
     ikut otomatis dari portal.css — tidak ada style hardcoded lagi. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Karir') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/line-awesome@1.3.0/dist/line-awesome/css/line-awesome.min.css" rel="stylesheet">

    {{-- Dimuat SETELAH Bootstrap: portal.css menimpa variabel Bootstrap dengan
         token proyek, jadi urutannya menentukan. --}}
    @vite('resources/css/portal.css')
</head>
<body class="career-portal">

{{-- Bidang biru: memuat navigasi mengapung dan judul halaman. --}}
<div class="portal-hero">
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-dark portal-nav">
            <div class="container-fluid">
                <a class="navbar-brand" href="{{ route('career.index') }}">
                    <i class="la la-briefcase"></i> Karir {{ config('app.name') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#careerNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="careerNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('career.index') }}">Lowongan</a>
                        </li>
                        @auth('candidate')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('career.dashboard') }}">Lamaran Saya</a>
                            </li>
                        @endauth
                    </ul>

                    <ul class="navbar-nav">
                        @auth('candidate')
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="la la-user-circle"></i> {{ auth('candidate')->user()->name }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('career.dashboard') }}">Lamaran Saya</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('career.logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">Keluar</button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('career.login') }}">Masuk</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('career.register') }}">Daftar</a>
                            </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>

        <h1 class="portal-title">@yield('heading', 'Karir & Lowongan')</h1>
    </div>
</div>

{{-- Lembar putih, naik menimpa bidang biru. --}}
<div class="container portal-sheet">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" id="flash-success">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" id="flash-error">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
