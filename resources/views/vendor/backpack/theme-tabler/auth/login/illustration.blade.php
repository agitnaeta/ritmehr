{{--
    Halaman login. Menimpa layout `illustration` bawaan tema Tabler, yang
    memakai SVG placeholder dari preview.tabler.io — sebuah gambar contoh
    dari situs demo, bukan aset milik proyek ini.

    Diadaptasi dari referensi desain: kartu membulat besar terbagi dua, panel
    merek bergradien di kiri, formulir di kanan.
--}}
@extends(backpack_view('layouts.auth'))

@section('content')
    <div class="auth-shell">
        <div class="auth-split">

            {{-- Panel merek. Disembunyikan di layar kecil: di sana formulir
                 yang penting, bukan hiasannya.

                 Memakai `project_name` milik Backpack, bukan config('app.name')
                 — yang terakhir masih bernilai "Laravel" karena APP_NAME di
                 .env belum diisi. --}}
            <aside class="auth-brand" aria-hidden="true">
                <span class="auth-brand__eyebrow">{{ backpack_theme_config('project_name') }}</span>
                <h2 class="auth-brand__title">Selamat<br>Datang</h2>
                <p class="auth-brand__lead">HRIS &amp; Manajemen Kehadiran</p>
                <p class="auth-brand__text">
                    Kelola kehadiran, penggajian, cuti, dan dokumen karyawan
                    dari satu tempat.
                </p>
                <p class="auth-brand__meta">
                    Butuh bantuan masuk? Hubungi bagian HR.
                </p>
            </aside>

            <main class="auth-form">
                <h1 class="auth-form__title">{{ trans('backpack::base.login') }}</h1>
                <p class="auth-form__sub">Masuk dengan akun yang diberikan HR.</p>

                @include(backpack_view('auth.login.inc.form'))
            </main>

        </div>
    </div>
@endsection
