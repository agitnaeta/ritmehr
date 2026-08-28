{{-- This file is used for menu items by any Backpack v6 theme --}}
{{-- Spatie's @can reads the default (web) guard, but admins authenticate on the
     backpack guard — so every check here goes through backpack_user(). --}}
{{-- Menu top-level diurutkan A-Z (Dashboard tetap dipatok paling atas sebagai home). --}}
@php($me = backpack_auth()->check() ? backpack_user() : null)

<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('dashboard') }}">
        <i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}
    </a>
</li>

{{-- Absen --}}
@if($me?->canAny(['presence.view', 'presence.scan', 'schedule.view', 'national_holiday.view']))
<x-backpack::menu-dropdown :title="__('menu.attendance')" icon="la la-calendar">
    @if($me->can('presence.scan'))
        <x-backpack::menu-dropdown-item title="Scan" icon="la la-calendar" :link="route('presence.scan')" />
    @endif
    @if($me->can('schedule.view'))
        <x-backpack::menu-dropdown-item title="Jadwal" icon="la la-calendar" :link="backpack_url('schedule')" />
    @endif
    @if($me->can('schedule.mass_update'))
        <x-backpack::menu-dropdown-item title="Setting Jadwal" icon="la la-calendar" :link="route('schedule.view.update')" />
    @endif
    @if($me->can('presence.view'))
        <x-backpack::menu-dropdown-item title="Kehadiran" icon="la la-calendar-check" :link="backpack_url('presence')" />
    @endif
    @if($me->can('presence.edit'))
        <x-backpack::menu-dropdown-item title="Persetujuan Absensi" icon="la la-user-check" :link="route('presence.approvals')" />
    @endif
    @if($me->can('national_holiday.view'))
        <x-backpack::menu-dropdown-item title="Libur Nasional" icon="la la-sun" :link="backpack_url('national-holiday')" />
    @endif
</x-backpack::menu-dropdown>
@endif

{{-- Akuntansi --}}
@if($me?->can('accounting.view'))
<x-backpack::menu-dropdown :title="__('menu.accounting')" icon="la la-book" >
    <x-backpack::menu-dropdown-item title="Buku Besar" icon="la la-book-open" :link="backpack_url('accounting/ledger')" />
    @if($me->can('accounting.edit'))
        <x-backpack::menu-dropdown-item title="Catat Transaksi" icon="la la-plus-circle" :link="backpack_url('accounting/transaksi')" />
    @endif
    <x-backpack::menu-dropdown-item title="Daftar Akun" icon="la la-list" :link="backpack_url('account')" />
    <x-backpack::menu-dropdown-item title="Jurnal" icon="la la-exchange-alt" :link="backpack_url('accounting/journal')" />
    <x-backpack::menu-dropdown-item title="Laba Rugi" icon="la la-chart-line" :link="backpack_url('accounting/income-statement')" />
    <x-backpack::menu-dropdown-item title="Neraca" icon="la la-file-invoice" :link="backpack_url('accounting/balance-sheet')" />
    <x-backpack::menu-dropdown-item title="Neraca Saldo" icon="la la-balance-scale" :link="backpack_url('accounting/trial-balance')" />
</x-backpack::menu-dropdown>
@endif

{{-- Audit Log --}}
@if($me?->can('audit.view'))
    <x-backpack::menu-item :title="__('menu.audit_log')" icon="la la-history" :link="backpack_url('audit-log')" />
@endif

{{-- Cuti & Izin --}}
@if($me?->canAny(['leave.view_all', 'leave.view_own', 'leave.request']))
<x-backpack::menu-dropdown :title="__('menu.leave')" icon="la la-umbrella-beach">
    <x-backpack::menu-dropdown-item title="Pengajuan Cuti" icon="la la-file-alt" :link="backpack_url('leave-request')" />
    @if($me->can('leave.request'))
        <x-backpack::menu-dropdown-item title="Ajukan Cuti" icon="la la-plus" :link="backpack_url('leave-request/create-form')" />
    @endif
    <x-backpack::menu-dropdown-item title="Kalender Cuti" icon="la la-calendar" :link="backpack_url('leave-calendar')" />
    @if($me->can('leave.manage_balance'))
        <x-backpack::menu-dropdown-item title="Saldo Cuti" icon="la la-wallet" :link="backpack_url('leave-balance')" />
    @endif
    @if($me->can('leave.configure'))
        <x-backpack::menu-dropdown-item title="Jenis Cuti" icon="la la-tags" :link="backpack_url('leave-type')" />
    @endif
</x-backpack::menu-dropdown>
@endif

{{-- Dokumen --}}
@if($me?->can('document.view'))
<x-backpack::menu-dropdown :title="__('menu.documents')" icon="la la-folder-open">
    <x-backpack::menu-dropdown-item title="Dokumen Karyawan" icon="la la-file-alt" :link="backpack_url('employee-document')" />
    <x-backpack::menu-dropdown-item title="Kelengkapan Dokumen" icon="la la-clipboard-check" :link="backpack_url('employee-document/completeness')" />
    <x-backpack::menu-dropdown-item title="Jenis Dokumen" icon="la la-tags" :link="backpack_url('document-type')" />
</x-backpack::menu-dropdown>
@endif

{{-- Gajian --}}
@if($me?->canAny(['salary.view', 'salary_recap.view']))
<x-backpack::menu-dropdown :title="__('menu.payroll')" icon="la la-money-check-alt">
    @if($me->can('salary.view'))
        <x-backpack::menu-dropdown-item title="Gaji" icon="la la-money-check" :link="backpack_url('salary')" />
        <x-backpack::menu-dropdown-item title="Jenis Tunjangan" icon="la la-tags" :link="backpack_url('salary-allowance-type')" />
        {{-- M20b — "Tunjangan Karyawan" dipindah inline ke form Gaji; menu terpisah disembunyikan (route tetap ada sebagai fallback). --}}
    @endif
    @if($me->can('salary_recap.view'))
        <x-backpack::menu-dropdown-item title="Rekap Gaji" icon="la la-money-check" :link="backpack_url('salary-recap')" />
    @endif
</x-backpack::menu-dropdown>
@endif

{{-- Kasbon --}}
@if($me?->canAny(['loan.view', 'loan_payment.view']))
<x-backpack::menu-dropdown :title="__('menu.loan')" icon="la la-money-bill">
    @if($me->can('loan.view'))
        <x-backpack::menu-dropdown-item title="Rekap" icon="la la-money-bill" :link="route('loan.recap')" />
        <x-backpack::menu-dropdown-item title="Kasbon" icon="la la-money-bill" :link="backpack_url('loan')" />
    @endif
    @if($me->can('loan_payment.view'))
        <x-backpack::menu-dropdown-item title="Pembayaran Kasbon" icon="la la-money-bill-alt" :link="backpack_url('loan-payment')" />
    @endif
</x-backpack::menu-dropdown>
@endif

{{-- Kinerja --}}
@if($me?->canAny(['performance.view', 'performance.edit', 'performance.review_self']))
<x-backpack::menu-dropdown :title="__('menu.performance')" icon="la la-chart-line">
    <x-backpack::menu-dropdown-item title="Penilaian Saya" icon="la la-user-check" :link="backpack_url('performance')" />
    @if($me->can('performance.edit'))
        <x-backpack::menu-dropdown-item title="KPI" icon="la la-bullseye" :link="backpack_url('kpi')" />
        <x-backpack::menu-dropdown-item title="Papan Skor" icon="la la-chart-bar" :link="backpack_url('performance/scoreboard')" />
        <x-backpack::menu-dropdown-item title="Siklus Penilaian" icon="la la-sync" :link="backpack_url('review-cycle')" />
    @endif
</x-backpack::menu-dropdown>
@endif

{{-- Pelatihan (M11) --}}
@if($me?->canAny(['training.view', 'training.enroll_self']))
<x-backpack::menu-dropdown :title="__('menu.training')" icon="la la-graduation-cap">
    <x-backpack::menu-dropdown-item title="Pelatihan Saya" icon="la la-book-reader" :link="url('my/training')" />
    @if($me->can('training.view'))
        <x-backpack::menu-dropdown-item title="Kelola Pelatihan" icon="la la-chalkboard-teacher" :link="backpack_url('training')" />
    @endif
</x-backpack::menu-dropdown>
@endif

{{-- Laporan — hub laporan terpadu (M08). Sub-item A-Z, tiap link tetap ter-gate izinnya. --}}
@if($me?->canAny(['report.view', 'tax.view', 'leave.view_all']))
<x-backpack::menu-dropdown :title="__('menu.reports')" icon="la la-chart-bar">
    @if($me->can('tax.view'))
        <x-backpack::menu-dropdown-item title="BPJS Bulanan" icon="la la-hospital" :link="backpack_url('tax-report/bpjs')" />
    @endif
    @if($me->can('report.view'))
        <x-backpack::menu-dropdown-item title="Gaji" icon="la la-money-check" :link="backpack_url('report/salary')" />
        <x-backpack::menu-dropdown-item title="Headcount" icon="la la-users" :link="backpack_url('report/headcount')" />
        <x-backpack::menu-dropdown-item title="Kasbon" icon="la la-money-bill" :link="backpack_url('report/loan')" />
        <x-backpack::menu-dropdown-item title="Kehadiran" icon="la la-calendar-check" :link="backpack_url('report/attendance')" />
    @endif
    @if($me->can('tax.view'))
        <x-backpack::menu-dropdown-item title="Pajak Tahunan" icon="la la-file-invoice-dollar" :link="backpack_url('tax-report/annual')" />
    @endif
    @if($me->can('leave.view_all'))
        <x-backpack::menu-dropdown-item title="Rekap Cuti" icon="la la-umbrella-beach" :link="backpack_url('leave-report')" />
    @endif
</x-backpack::menu-dropdown>
@endif

{{-- Organisasi --}}
@if($me?->canAny(['org.view', 'branch.view']))
<x-backpack::menu-dropdown :title="__('menu.organization')" icon="la la-sitemap">
    @if($me->can('branch.view'))
        <x-backpack::menu-dropdown-item title="Cabang" icon="la la-map-marked" :link="backpack_url('branch')" />
    @endif
    @if($me->can('org.view'))
        <x-backpack::menu-dropdown-item title="Departemen" icon="la la-building" :link="backpack_url('department')" />
        <x-backpack::menu-dropdown-item title="Jabatan" icon="la la-id-badge" :link="backpack_url('position')" />
        <x-backpack::menu-dropdown-item title="Struktur Organisasi" icon="la la-project-diagram" :link="backpack_url('org-chart')" />
    @endif
</x-backpack::menu-dropdown>
@endif

{{-- Pajak & BPJS --}}
@if($me?->can('tax.view'))
<x-backpack::menu-dropdown :title="__('menu.tax_bpjs')" icon="la la-file-invoice-dollar">
    <x-backpack::menu-dropdown-item title="Profil Pajak Karyawan" icon="la la-user-tag" :link="backpack_url('tax-profile')" />
    <x-backpack::menu-dropdown-item title="Tarif PTKP" icon="la la-percent" :link="backpack_url('ptkp-rate')" />
    <x-backpack::menu-dropdown-item title="Lapisan PPh 21" icon="la la-layer-group" :link="backpack_url('pph21-bracket')" />
    <x-backpack::menu-dropdown-item title="Tarif TER (PPh 21)" icon="la la-percentage" :link="backpack_url('ter-rate')" />
    <x-backpack::menu-dropdown-item title="Tarif BPJS" icon="la la-sliders-h" :link="backpack_url('bpjs-rate')" />
</x-backpack::menu-dropdown>
@endif

{{-- Pengaturan --}}
@if($me?->hasRole('super_admin'))
<x-backpack::menu-dropdown :title="__('menu.settings')" icon="la la-cogs">
    <x-backpack::menu-dropdown-item title="Pengaturan Sistem" icon="la la-sliders-h" :link="backpack_url('settings')" />
    <x-backpack::menu-dropdown-item title="Koneksi WhatsApp" icon="la la-whatsapp" :link="backpack_url('whatsapp')" />
    <x-backpack::menu-dropdown-item title="Role" icon="la la-key" :link="backpack_url('role')" />
    <x-backpack::menu-dropdown-item title="Permission" icon="la la-lock" :link="backpack_url('permission')" />
    <x-backpack::menu-dropdown-item title="Alur Persetujuan" icon="la la-sitemap" :link="backpack_url('approval-flow')" />
    <x-backpack::menu-dropdown-item title="Step Persetujuan" icon="la la-list-ol" :link="backpack_url('approval-flow-step')" />
</x-backpack::menu-dropdown>
@endif

{{-- Persetujuan --}}
@if($me?->canAny(['approval.view_all', 'approval.act']))
    <x-backpack::menu-item :title="__('menu.approval')" icon="la la-check-double" :link="backpack_url('approval')" />
@endif

{{-- Rekrutmen --}}
@if($me?->can('recruitment.view'))
<x-backpack::menu-dropdown :title="__('menu.recruitment')" icon="la la-user-plus">
    <x-backpack::menu-dropdown-item :title="__('menu.recruitment_pipeline')" icon="la la-columns" :link="backpack_url('recruitment/pipeline')" />
    <x-backpack::menu-dropdown-item :title="__('menu.recruitment_ranking')" icon="la la-trophy" :link="backpack_url('recruitment/ranking')" />
    <x-backpack::menu-dropdown-item :title="__('menu.recruitment_openings')" icon="la la-briefcase" :link="backpack_url('job-opening')" />
    <x-backpack::menu-dropdown-item :title="__('menu.recruitment_applicants')" icon="la la-user-friends" :link="backpack_url('applicant')" />
    <x-backpack::menu-dropdown-item :title="__('menu.recruitment_interviews')" icon="la la-calendar" :link="backpack_url('recruitment/calendar')" />
</x-backpack::menu-dropdown>
@endif

{{-- Profile Perusahaan --}}
@if($me?->can('company_profile.view'))
    <x-backpack::menu-item :title="__('menu.company_profile')" icon="la la-building" :link="backpack_url('company-profile')" />
@endif

{{-- Users --}}
@if($me?->can('user.view'))
    <x-backpack::menu-item :title="__('menu.users')" icon="la la-user" :link="backpack_url('user')" />
@endif

{{-- M13 — Language switcher. Ditaruh di sidebar karena tema vertikal ini
     menyembunyikan topbar kanan. Tampilkan bahasa aktif + opsi ganti. --}}
<x-backpack::menu-dropdown :title="__('common.language').' ('.strtoupper(app()->getLocale()).')'" icon="la la-globe">
    <x-backpack::menu-dropdown-item title="🇮🇩 Indonesia" icon="la la-check{{ app()->getLocale() === 'id' ? '' : '-empty' }}" :link="url('/locale/id')" />
    <x-backpack::menu-dropdown-item title="🇬🇧 English" icon="la la-check{{ app()->getLocale() === 'en' ? '' : '-empty' }}" :link="url('/locale/en')" />
</x-backpack::menu-dropdown>
