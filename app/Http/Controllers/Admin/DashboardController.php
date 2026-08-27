<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Department;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function index()
    {
        // QW-04 — instance baru (hanya admin, belum ada data inti) butuh panduan
        // "mulai di sini" alih-alih deretan angka nol tanpa arah.
        $needsOnboarding = \App\Models\User::count() <= 1
            || ! \App\Models\Salary::exists();

        $onboardingSteps = [
            ['label' => 'Lengkapi Profil Perusahaan', 'done' => \App\Models\CompanyProfile::exists(), 'url' => backpack_url('company-profile')],
            ['label' => 'Tambah Departemen & Cabang', 'done' => \App\Models\Department::exists() && \App\Models\Branch::exists(), 'url' => backpack_url('department')],
            ['label' => 'Tambah / Import Karyawan', 'done' => \App\Models\User::count() > 1, 'url' => backpack_url('user')],
            ['label' => 'Atur Struktur Gaji', 'done' => \App\Models\Salary::exists(), 'url' => backpack_url('salary')],
        ];

        return view('admin.dashboard', [
            'today'        => $this->dashboard->todaySnapshot(),
            'month'        => $this->dashboard->monthSnapshot(),
            'trend'        => $this->dashboard->attendanceTrend(12),
            'latecomers'   => $this->dashboard->topLatecomers(),
            'leaveThisWeek' => $this->dashboard->leaveThisWeek(),
            'headcount'    => $this->dashboard->headcount(),
            'recapMonth'   => now()->format('m-Y'),
            'needsOnboarding' => $needsOnboarding,
            'onboardingSteps' => $onboardingSteps,
        ]);
    }

    public function attendanceReport(Request $request)
    {
        $month = $request->input('month')
            ? Carbon::parse($request->input('month') . '-01')
            : now()->startOfMonth();

        return view('admin.report.attendance', [
            'month'        => $month,
            'rows'         => $this->dashboard->attendanceReport(
                $month,
                $request->input('department_id') ? (int) $request->input('department_id') : null,
                $request->input('branch_id') ? (int) $request->input('branch_id') : null,
            ),
            'departments'  => Department::orderBy('name')->get(),
            'branches'     => Branch::orderBy('name')->get(),
            'departmentId' => $request->input('department_id'),
            'branchId'     => $request->input('branch_id'),
        ]);
    }

    public function salaryReport(Request $request)
    {
        $recapMonth = $request->input('recap_month') ?: now()->format('m-Y');

        return view('admin.report.salary', [
            'recapMonth'   => $recapMonth,
            'rows'         => $this->dashboard->salaryReport(
                $recapMonth,
                $request->input('department_id') ? (int) $request->input('department_id') : null
            ),
            'departments'  => Department::orderBy('name')->get(),
            'departmentId' => $request->input('department_id'),
        ]);
    }

    public function loanReport()
    {
        return view('admin.report.loan', [
            'rows' => $this->dashboard->loanReport(),
        ]);
    }

    public function headcountReport()
    {
        return view('admin.report.headcount', [
            'headcount' => $this->dashboard->headcount(),
        ]);
    }
}
