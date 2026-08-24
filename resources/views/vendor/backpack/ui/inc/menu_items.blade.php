{{-- This file is used for menu items by any Backpack v6 theme --}}
{{-- Spatie's @can reads the default (web) guard, but admins authenticate on the
     backpack guard — so every check here goes through backpack_user(). --}}
@php($me = backpack_auth()->check() ? backpack_user() : null)

<li class="nav-item">
    <a class="nav-link" href="{{ backpack_url('dashboard') }}">
        <i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}
    </a>
</li>

@if($me?->can('user.view'))
    <x-backpack::menu-item title="Users" icon="la la-user" :link="backpack_url('user')" />
@endif

@if($me?->canAny(['presence.view', 'presence.scan', 'schedule.view', 'national_holiday.view']))
<x-backpack::menu-dropdown title="Absen" icon="la la-calendar">
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
    @if($me->can('national_holiday.view'))
        <x-backpack::menu-dropdown-item title="Libur Nasional" icon="la la-sun" :link="backpack_url('national-holiday')" />
    @endif
</x-backpack::menu-dropdown>
@endif

@if($me?->canAny(['loan.view', 'loan_payment.view']))
<x-backpack::menu-dropdown title="Kasbon" icon="la la-money-bill">
    @if($me->can('loan.view'))
        <x-backpack::menu-dropdown-item title="Rekap" icon="la la-money-bill" :link="route('loan.recap')" />
        <x-backpack::menu-dropdown-item title="Kasbon" icon="la la-money-bill" :link="backpack_url('loan')" />
    @endif
    @if($me->can('loan_payment.view'))
        <x-backpack::menu-dropdown-item title="Pembayaran Kasbon" icon="la la-money-bill-alt" :link="backpack_url('loan-payment')" />
    @endif
</x-backpack::menu-dropdown>
@endif

@if($me?->canAny(['salary.view', 'salary_recap.view']))
<x-backpack::menu-dropdown title="Gajian" icon="la la-money-check-alt">
    @if($me->can('salary.view'))
        <x-backpack::menu-dropdown-item title="Gaji" icon="la la-money-check" :link="backpack_url('salary')" />
    @endif
    @if($me->can('salary_recap.view'))
        <x-backpack::menu-dropdown-item title="Rekap Gaji" icon="la la-money-check" :link="backpack_url('salary-recap')" />
    @endif
</x-backpack::menu-dropdown>
@endif


@if($me?->can('company_profile.view'))
    <x-backpack::menu-item title="Profile Perusahaan" icon="la la-building" :link="backpack_url('company-profile')" />
@endif

@if($me?->can('acc.view'))
    <x-backpack::menu-item title="Konfigurasi Akuntansi" icon="la la-cogs" :link="backpack_url('acc')" />
@endif

@if($me?->canAny(['org.view', 'branch.view']))
<x-backpack::menu-dropdown title="Organisasi" icon="la la-sitemap">
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

@if($me?->canAny(['leave.view_all', 'leave.view_own', 'leave.request']))
<x-backpack::menu-dropdown title="Cuti & Izin" icon="la la-umbrella-beach">
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
    <x-backpack::menu-dropdown-item title="Rekap Cuti" icon="la la-chart-bar" :link="backpack_url('leave-report')" />
</x-backpack::menu-dropdown>
@endif

@if($me?->can('document.view'))
<x-backpack::menu-dropdown title="Dokumen" icon="la la-folder-open">
    <x-backpack::menu-dropdown-item title="Dokumen Karyawan" icon="la la-file-alt" :link="backpack_url('employee-document')" />
    <x-backpack::menu-dropdown-item title="Kelengkapan Dokumen" icon="la la-clipboard-check" :link="backpack_url('employee-document/completeness')" />
    <x-backpack::menu-dropdown-item title="Jenis Dokumen" icon="la la-tags" :link="backpack_url('document-type')" />
</x-backpack::menu-dropdown>
@endif

@if($me?->can('tax.view'))
<x-backpack::menu-dropdown title="Pajak & BPJS" icon="la la-file-invoice-dollar">
    <x-backpack::menu-dropdown-item title="Profil Pajak Karyawan" icon="la la-user-tag" :link="backpack_url('tax-profile')" />
    <x-backpack::menu-dropdown-item title="Rekap Pajak Tahunan" icon="la la-chart-line" :link="backpack_url('tax-report/annual')" />
    <x-backpack::menu-dropdown-item title="Rekap BPJS Bulanan" icon="la la-hospital" :link="backpack_url('tax-report/bpjs')" />
    <x-backpack::menu-dropdown-item title="Tarif PTKP" icon="la la-percent" :link="backpack_url('ptkp-rate')" />
    <x-backpack::menu-dropdown-item title="Lapisan PPh 21" icon="la la-layer-group" :link="backpack_url('pph21-bracket')" />
    <x-backpack::menu-dropdown-item title="Tarif BPJS" icon="la la-sliders-h" :link="backpack_url('bpjs-rate')" />
</x-backpack::menu-dropdown>
@endif

@if($me?->canAny(['approval.view_all', 'approval.act']))
    <x-backpack::menu-item title="Persetujuan" icon="la la-check-double" :link="backpack_url('approval')" />
@endif

@if($me?->can('audit.view'))
    <x-backpack::menu-item title="Audit Log" icon="la la-history" :link="backpack_url('audit-log')" />
@endif

@if($me?->hasRole('super_admin'))
<x-backpack::menu-dropdown title="Pengaturan" icon="la la-cogs">
    <x-backpack::menu-dropdown-item title="Role" icon="la la-key" :link="backpack_url('role')" />
    <x-backpack::menu-dropdown-item title="Permission" icon="la la-lock" :link="backpack_url('permission')" />
    <x-backpack::menu-dropdown-item title="Alur Persetujuan" icon="la la-sitemap" :link="backpack_url('approval-flow')" />
    <x-backpack::menu-dropdown-item title="Step Persetujuan" icon="la la-list-ol" :link="backpack_url('approval-flow-step')" />
</x-backpack::menu-dropdown>
@endif
