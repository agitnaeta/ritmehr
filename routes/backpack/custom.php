<?php

use App\Http\Controllers\Admin\ApprovalCrudController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentCrudController;
use App\Http\Controllers\Admin\EmployeeDocumentController;
use App\Http\Controllers\Admin\LeaveBalanceCrudController;
use App\Http\Controllers\Admin\LeaveRequestCrudController;
use App\Http\Controllers\Admin\LoanCrudController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PresenceCrudController;
use App\Http\Controllers\Admin\SalaryRecapCrudController;
use App\Http\Controllers\Admin\TaxReportController;
use App\Http\Controllers\Admin\ScheduleCrudController;
use App\Http\Controllers\Admin\UserCrudController;
use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\Base.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('user', 'UserCrudController');
    Route::crud('schedule', 'ScheduleCrudController');
    Route::crud('salary', 'SalaryCrudController');
    Route::crud('loan', 'LoanCrudController');
    Route::crud('loan-payment', 'LoanPaymentCrudController');
    Route::crud('presence', 'PresenceCrudController');
    Route::crud('salary-recap', 'SalaryRecapCrudController');

    Route::group(['prefix'=>'user'],function (){
        Route::get("/{id}/print",[UserCrudController::class,'print'])->name('user.print');
        Route::get("/print-all",[UserCrudController::class,'printAll'])->name('user.print.all');
        Route::get("/export",[UserCrudController::class,'export'])->name('user.export.all');
    });
    Route::group(['prefix'=>'presence'],function (){
        Route::get("/scan",[PresenceCrudController::class,'scan'])->name('presence.scan');
        Route::post("/record",[PresenceCrudController::class,'record'])->name('presence.record');
        Route::get("/record",[PresenceCrudController::class,'record'])->name('presence.record.get');
    });

    Route::group(['prefix'=>'schedule'],function (){
        Route::get("/view-update",[ScheduleCrudController::class,'viewSchedule'])->name('schedule.view.update');
        Route::post("/mass-update",[ScheduleCrudController::class,'massUpdateSchedule'])->name('schedule.mass_update');
    });
    Route::group(['prefix'=>'loan'],function (){
        Route::get("/recap",[LoanCrudController::class,'loanRecap'])->name('loan.recap');
        Route::get("/download",[LoanCrudController::class,'download'])->name('loan.download');
        Route::get("/{id}/download-detail",[LoanCrudController::class,'downloadDetail'])->name('loan.download.detail');
        Route::get("/{id}/print-detail",[LoanCrudController::class,'print'])->name('loan.download.print');
        Route::get("/{id}/detail",[LoanCrudController::class,'detail'])->name('loan.detail');
    });
    Route::group(['prefix'=>'salary-recap'],function (){
        Route::get('export',[SalaryRecapCrudController::class,'export'])
            ->name('salary-recap.export');
        Route::get('print',[SalaryRecapCrudController::class,'print'])
            ->name('salary-recap.print');
    });


    // Modul di bawah ini tertutup bagi role yang tidak punya izinnya.
    // Untuk modul yang boleh DIBACA tapi tidak boleh DITULIS oleh sebagian role
    // (user, presence, salary, loan, jadwal, organisasi), pembatasannya ada di
    // controller lewat denyAccess — middleware hanya menjaga pintu masuk.
    Route::group(['middleware' => 'permission:schedule.view'], function () {
        Route::crud('day', 'DayCrudController');
        Route::crud('schedule-day-off', 'ScheduleDayOffCrudController');
    });
    Route::group(['middleware' => 'permission:national_holiday.view'], function () {
        Route::crud('national-holiday', 'NationalHolidayCrudController');
    });
    Route::group(['middleware' => 'permission:company_profile.view'], function () {
        Route::crud('company-profile', 'CompanyProfileCrudController');
    });
    Route::group(['middleware' => 'permission:acc.view'], function () {
        Route::crud('acc', 'AccCrudController');
    });
    Route::crud('role', 'RoleCrudController');
    Route::crud('permission', 'PermissionCrudController');
    Route::group(['middleware' => 'permission:audit.view'], function () {
        Route::crud('audit-log', 'AuditLogCrudController');
    });

    Route::group(['prefix' => 'notification'], function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notification.index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('notification.unread_count');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notification.mark_all_read');
        Route::get('/{id}/read', [NotificationController::class, 'read'])->name('notification.read');
    });

    // Overrides Backpack's stock dashboard. Declared before the CRUD routes
    // so it wins over the package's own /dashboard registration.
    Route::get('dashboard', [DashboardController::class, 'index'])->name('backpack.dashboard');

    Route::group(['prefix' => 'report'], function () {
        Route::get('/attendance', [DashboardController::class, 'attendanceReport'])->name('report.attendance');
        Route::get('/salary', [DashboardController::class, 'salaryReport'])->name('report.salary');
        Route::get('/loan', [DashboardController::class, 'loanReport'])->name('report.loan');
        Route::get('/headcount', [DashboardController::class, 'headcountReport'])->name('report.headcount');
    });

    Route::group(['middleware' => 'permission:branch.view'], function () {
        Route::crud('branch', 'BranchCrudController');
    });
    Route::group(['middleware' => 'permission:org.view'], function () {
        Route::crud('department', 'DepartmentCrudController');
        Route::crud('position', 'PositionCrudController');
        Route::get('org-chart', [DepartmentCrudController::class, 'orgChart'])->name('department.org_chart');
    });

    Route::group(['middleware' => 'permission:leave.configure'], function () {
        Route::crud('leave-type', 'LeaveTypeCrudController');
    });
    Route::group(['middleware' => 'permission:leave.manage_balance'], function () {
        Route::crud('leave-balance', 'LeaveBalanceCrudController');
        Route::post('leave-balance/generate', [LeaveBalanceCrudController::class, 'generate'])->name('leave-balance.generate');
        Route::post('leave-balance/carry-over', [LeaveBalanceCrudController::class, 'carryOver'])->name('leave-balance.carry_over');
    });
    Route::crud('leave-request', 'LeaveRequestCrudController');
    Route::group(['prefix' => 'leave-request'], function () {
        Route::get('/create-form', [LeaveRequestCrudController::class, 'createForm'])->name('leave-request.create_form');
        Route::post('/store-form', [LeaveRequestCrudController::class, 'storeForm'])->name('leave-request.store_form');
        Route::post('/{id}/cancel', [LeaveRequestCrudController::class, 'cancel'])->name('leave-request.cancel');
    });
    Route::get('leave-calendar', [LeaveRequestCrudController::class, 'calendar'])->name('leave.calendar');
    Route::get('leave-report', [LeaveRequestCrudController::class, 'report'])->name('leave.report');

    Route::group(['middleware' => 'permission:document.view'], function () {
        Route::crud('document-type', 'DocumentTypeCrudController');
        Route::group(['prefix' => 'employee-document'], function () {
            Route::get('/', [EmployeeDocumentController::class, 'index'])->name('employee-document.index');
            Route::get('/create', [EmployeeDocumentController::class, 'create'])->name('employee-document.create');
            Route::post('/', [EmployeeDocumentController::class, 'store'])->name('employee-document.store');
            Route::get('/completeness', [EmployeeDocumentController::class, 'completeness'])->name('employee-document.completeness');
            Route::get('/{id}/download', [EmployeeDocumentController::class, 'download'])
                 ->whereNumber('id')->name('employee-document.download');
            Route::post('/{id}/delete', [EmployeeDocumentController::class, 'destroy'])
                 ->whereNumber('id')->name('employee-document.destroy');
        });
    });

    Route::group(['middleware' => 'permission:tax.view'], function () {
        Route::crud('tax-profile', 'EmployeeTaxProfileCrudController');
        Route::crud('ptkp-rate', 'PtkpRateCrudController');
        Route::crud('pph21-bracket', 'Pph21BracketCrudController');
        Route::crud('bpjs-rate', 'BpjsRateCrudController');
        Route::group(['prefix' => 'tax-report'], function () {
            Route::get('/annual', [TaxReportController::class, 'annual'])->name('tax-report.annual');
            Route::get('/bpjs', [TaxReportController::class, 'bpjs'])->name('tax-report.bpjs');
            Route::post('/recalculate', [TaxReportController::class, 'recalculate'])->name('tax-report.recalculate');
        });
    });

    Route::crud('approval-flow', 'ApprovalFlowCrudController');
    Route::crud('approval-flow-step', 'ApprovalFlowStepCrudController');
    Route::crud('approval', 'ApprovalCrudController');
    Route::group(['prefix' => 'approval'], function () {
        Route::get('/{id}/detail', [ApprovalCrudController::class, 'detail'])->name('approval.detail');
        Route::post('/{id}/approve', [ApprovalCrudController::class, 'approve'])->name('approval.approve');
        Route::post('/{id}/reject', [ApprovalCrudController::class, 'reject'])->name('approval.reject');
        Route::post('/{id}/cancel', [ApprovalCrudController::class, 'cancel'])->name('approval.cancel');
    });
}); // this should be the absolute last line of this file

Route::get("/scan",[PresenceCrudController::class,'scan'])->name('presence.scan.public');
Route::group(['prefix'=>'presence'],function (){
    Route::post("/record",[PresenceCrudController::class,'record'])->name('presence.record');
});