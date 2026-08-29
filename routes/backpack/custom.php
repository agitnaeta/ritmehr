<?php

use App\Http\Controllers\Admin\ApprovalCrudController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentCrudController;
use App\Http\Controllers\Admin\EmployeeDocumentController;
use App\Http\Controllers\Admin\LeaveBalanceCrudController;
use App\Http\Controllers\Admin\LeaveRequestCrudController;
use App\Http\Controllers\Admin\LoanCrudController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PerformanceController;
use App\Http\Controllers\Admin\PresenceCrudController;
use App\Http\Controllers\Admin\RecruitmentController;
use App\Http\Controllers\Admin\SalaryRecapCrudController;
use App\Http\Controllers\Admin\TrainingController;
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

    // WIZ-01 — Setup Wizard onboarding
    Route::get ('setup',        [\App\Http\Controllers\Admin\SetupWizardController::class, 'index'])->name('setup.index');
    Route::match(['get','post'], 'setup/finish', [\App\Http\Controllers\Admin\SetupWizardController::class, 'finish'])->name('setup.finish');
    Route::get ('setup/{step}', [\App\Http\Controllers\Admin\SetupWizardController::class, 'step'])->name('setup.step');
    Route::post('setup/{step}', [\App\Http\Controllers\Admin\SetupWizardController::class, 'save'])->name('setup.save');

    Route::crud('schedule', 'ScheduleCrudController');
    Route::crud('salary', 'SalaryCrudController');
    Route::group(['prefix'=>'salary'],function (){
        // IMP-04 — import struktur gaji dari Excel
        Route::get("/import",[\App\Http\Controllers\Admin\SalaryCrudController::class,'importForm'])->name('salary.import.form');
        Route::get("/import/template",[\App\Http\Controllers\Admin\SalaryCrudController::class,'importTemplate'])->name('salary.import.template');
        Route::post("/import/preview",[\App\Http\Controllers\Admin\SalaryCrudController::class,'importPreview'])->name('salary.import.preview');
        Route::post("/import",[\App\Http\Controllers\Admin\SalaryCrudController::class,'importStore'])->name('salary.import.store');
    });
    Route::crud('salary-allowance-type', 'SalaryAllowanceTypeCrudController');
    Route::crud('employee-salary-allowance', 'EmployeeSalaryAllowanceCrudController');
    Route::crud('loan', 'LoanCrudController');
    Route::crud('loan-payment', 'LoanPaymentCrudController');
    Route::crud('presence', 'PresenceCrudController');
    Route::crud('salary-recap', 'SalaryRecapCrudController');

    Route::group(['prefix'=>'user'],function (){
        Route::get("/{id}/print",[UserCrudController::class,'print'])->name('user.print');
        Route::get("/print-all",[UserCrudController::class,'printAll'])->name('user.print.all');
        // UM-11 — cetak terpilih + status/unduh PDF background
        Route::match(['get','post'],"/print-selected",[UserCrudController::class,'printSelected'])->name('user.print.selected');
        Route::get("/print/{printJob}/status",[UserCrudController::class,'printStatus'])->name('user.print.status');
        Route::get("/print/{printJob}/status.json",[UserCrudController::class,'printStatusJson'])->name('user.print.status.json');
        Route::get("/print/{printJob}/download",[UserCrudController::class,'printDownload'])->name('user.print.download');
        Route::get("/export",[UserCrudController::class,'export'])->name('user.export.all');
        // IMP-03 — import karyawan dari Excel
        Route::get("/import",[UserCrudController::class,'importForm'])->name('user.import.form');
        Route::get("/import/template",[UserCrudController::class,'importTemplate'])->name('user.import.template');
        Route::post("/import/preview",[UserCrudController::class,'importPreview'])->name('user.import.preview');
        Route::post("/import",[UserCrudController::class,'importStore'])->name('user.import.store');
        // UM-09 — status import background
        Route::get("/import/{importJob}/status",[UserCrudController::class,'importStatus'])->name('user.import.status');
        Route::get("/import/{importJob}/status.json",[UserCrudController::class,'importStatusJson'])->name('user.import.status.json');
        Route::get("/import/{importJob}/errors.csv",[UserCrudController::class,'importErrorsCsv'])->name('user.import.errors.csv');
    });
    Route::group(['prefix'=>'presence'],function (){
        Route::get("/scan",[PresenceCrudController::class,'scan'])->name('presence.scan');
        Route::post("/record",[PresenceCrudController::class,'record'])->name('presence.record');
        Route::get("/record",[PresenceCrudController::class,'record'])->name('presence.record.get');
        // M22-5 — approval absen luar-radius (Camera Mode)
        Route::get("/approvals",[PresenceCrudController::class,'approvals'])->name('presence.approvals');
        Route::post("/{id}/approve",[PresenceCrudController::class,'approveAction'])->name('presence.approve');
        Route::post("/{id}/reject",[PresenceCrudController::class,'rejectAction'])->name('presence.reject');
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
    // M12 — Internal accounting ledger. Chart of accounts (with posting roles)
    // fully replaces the old `acc` mapping screen; everything is under /account.
    Route::group(['middleware' => 'permission:accounting.view'], function () {
        Route::crud('account', 'AccountCrudController');
        Route::get('accounting/journal', [\App\Http\Controllers\Admin\LedgerController::class, 'journal'])->name('accounting.journal');
        Route::get('accounting/ledger', [\App\Http\Controllers\Admin\LedgerController::class, 'ledger'])->name('accounting.ledger');
        Route::get('accounting/trial-balance', [\App\Http\Controllers\Admin\LedgerController::class, 'trialBalance'])->name('accounting.trial_balance');
        Route::get('accounting/income-statement', [\App\Http\Controllers\Admin\LedgerController::class, 'incomeStatement'])->name('accounting.income_statement');
        Route::get('accounting/balance-sheet', [\App\Http\Controllers\Admin\LedgerController::class, 'balanceSheet'])->name('accounting.balance_sheet');

        // Manual journal management: friendly "Catat Transaksi" + advanced + reversal.
        Route::get('accounting/transaksi', [\App\Http\Controllers\Admin\JournalController::class, 'chooser'])->name('accounting.journal.chooser');
        Route::get('accounting/journal/create', [\App\Http\Controllers\Admin\JournalController::class, 'create'])->name('accounting.journal.create');
        Route::post('accounting/journal', [\App\Http\Controllers\Admin\JournalController::class, 'store'])->name('accounting.journal.store');
        Route::get('accounting/journal/{id}/edit', [\App\Http\Controllers\Admin\JournalController::class, 'edit'])->whereNumber('id')->name('accounting.journal.edit');
        Route::put('accounting/journal/{id}', [\App\Http\Controllers\Admin\JournalController::class, 'update'])->whereNumber('id')->name('accounting.journal.update');
        Route::delete('accounting/journal/{id}', [\App\Http\Controllers\Admin\JournalController::class, 'destroy'])->whereNumber('id')->name('accounting.journal.destroy');
        Route::post('accounting/journal/{id}/reverse', [\App\Http\Controllers\Admin\JournalController::class, 'reverse'])->whereNumber('id')->name('accounting.journal.reverse');
        Route::get('accounting/journal/{id}/attachment', [\App\Http\Controllers\Admin\JournalController::class, 'attachment'])->whereNumber('id')->name('accounting.journal.attachment');
    });
    Route::crud('role', 'RoleCrudController');
    Route::crud('permission', 'PermissionCrudController');
    Route::group(['middleware' => 'permission:audit.view'], function () {
        Route::crud('audit-log', 'AuditLogCrudController');
    });

    // M15 — Platform Settings (super admin only; guard also enforced in controller).
    Route::group(['prefix' => 'settings'], function () {
        Route::get('/', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('settings.index');
        Route::post('/', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');
        Route::post('/test-whatsapp', [\App\Http\Controllers\Admin\SettingController::class, 'testWhatsApp'])->name('settings.test_whatsapp');
        Route::post('/test-storage', [\App\Http\Controllers\Admin\SettingController::class, 'testStorage'])->name('settings.test_storage');
        // M17 — Rekrutmen AI probes.
        Route::post('/test-qdrant', [\App\Http\Controllers\Admin\SettingController::class, 'testQdrant'])->name('settings.test_qdrant');
        Route::post('/test-embedding', [\App\Http\Controllers\Admin\SettingController::class, 'testEmbedding'])->name('settings.test_embedding');
        Route::post('/test-llm', [\App\Http\Controllers\Admin\SettingController::class, 'testLlm'])->name('settings.test_llm');
    });

    // M03b — In-app WhatsApp (WAHA) connection: scan QR, status, logout.
    Route::group(['prefix' => 'whatsapp'], function () {
        Route::get('/', [\App\Http\Controllers\Admin\WahaController::class, 'index'])->name('whatsapp.index');
        Route::get('/status', [\App\Http\Controllers\Admin\WahaController::class, 'status'])->name('whatsapp.status');
        Route::post('/start', [\App\Http\Controllers\Admin\WahaController::class, 'start'])->name('whatsapp.start');
        Route::get('/qr', [\App\Http\Controllers\Admin\WahaController::class, 'qr'])->name('whatsapp.qr');
        Route::post('/logout', [\App\Http\Controllers\Admin\WahaController::class, 'logout'])->name('whatsapp.logout');
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

    Route::group(['prefix' => 'report', 'middleware' => 'permission:report.view'], function () {
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
        Route::crud('ter-rate', 'TerRateCrudController');
        Route::crud('bpjs-rate', 'BpjsRateCrudController');
        Route::group(['prefix' => 'tax-report'], function () {
            Route::get('/annual', [TaxReportController::class, 'annual'])->name('tax-report.annual');
            Route::get('/bpjs', [TaxReportController::class, 'bpjs'])->name('tax-report.bpjs');
            Route::post('/recalculate', [TaxReportController::class, 'recalculate'])->name('tax-report.recalculate');
        });
    });

    // M09 — Recruitment. Pipeline board + interview calendar + hire action.
    Route::group(['middleware' => 'permission:recruitment.view'], function () {
        Route::crud('job-opening', 'JobOpeningCrudController');
        Route::crud('applicant', 'ApplicantCrudController');
        Route::crud('interview', 'InterviewCrudController');
        Route::get('recruitment/pipeline', [RecruitmentController::class, 'pipeline'])->name('recruitment.pipeline');
        Route::get('recruitment/ranking', [RecruitmentController::class, 'ranking'])->name('recruitment.ranking');
        Route::get('recruitment/calendar', [RecruitmentController::class, 'calendar'])->name('recruitment.calendar');
        Route::post('recruitment/applicant/{id}/stage', [RecruitmentController::class, 'moveStage'])
             ->whereNumber('id')->name('recruitment.move_stage');
        Route::post('recruitment/applicant/{id}/hire', [RecruitmentController::class, 'hire'])
             ->whereNumber('id')->name('recruitment.hire');
        // M17 — reject (hapus CV) + AI ranking.
        Route::post('recruitment/applicant/{id}/reject', [RecruitmentController::class, 'reject'])
             ->whereNumber('id')->name('recruitment.reject');
        Route::post('recruitment/opening/{id}/rank', [RecruitmentController::class, 'rankWithAi'])
             ->whereNumber('id')->name('recruitment.rank_ai');
        // M18 — inline CV stream (read without download).
        Route::get('recruitment/applicant/{id}/cv', [RecruitmentController::class, 'streamCv'])
             ->whereNumber('id')->name('recruitment.cv');
        // M18-3 — applicant detail JSON for the pipeline drawer.
        Route::get('recruitment/applicant/{id}/detail', [RecruitmentController::class, 'applicantDetail'])
             ->whereNumber('id')->name('recruitment.detail');
        // M18-4 — schedule interview inline from the drawer.
        Route::post('recruitment/applicant/{id}/interview', [RecruitmentController::class, 'scheduleInterview'])
             ->whereNumber('id')->name('recruitment.schedule_interview');
        // M18-5 — bulk action (reject/move) on selected applicants.
        Route::post('recruitment/bulk', [RecruitmentController::class, 'bulkAction'])
             ->name('recruitment.bulk');
    });

    // M10 — Performance Management. Cycles/KPI (edit-gated) + review flow (self-service).
    Route::group(['middleware' => 'permission:performance.edit'], function () {
        Route::crud('review-cycle', 'ReviewCycleCrudController');
        Route::crud('kpi', 'KpiCrudController');
        Route::post('performance/cycle/{cycleId}/generate', [PerformanceController::class, 'generate'])
             ->whereNumber('cycleId')->name('performance.generate');
        Route::get('performance/scoreboard', [PerformanceController::class, 'scoreboard'])->name('performance.scoreboard');
        Route::post('performance/review/{id}/manager', [PerformanceController::class, 'submitManager'])
             ->whereNumber('id')->name('performance.submit_manager');
        Route::post('performance/review/{id}/finalize', [PerformanceController::class, 'finalize'])
             ->whereNumber('id')->name('performance.finalize');
    });
    // Self-service: any admin-panel user may reach these; the controller's
    // guardView() (canAny performance.view/review_self) + ownership checks do
    // the real gating. Avoids Spatie's `a|b` middleware OR-syntax ambiguity.
    Route::get('performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::get('performance/review/{id}', [PerformanceController::class, 'show'])
         ->whereNumber('id')->name('performance.review');
    Route::post('performance/review/{id}/self', [PerformanceController::class, 'submitSelf'])
         ->whereNumber('id')->name('performance.submit_self');

    // M11 — Training & Development (mini-LMS). Admin authoring is edit-gated;
    // the tabbed manage page carries materials + quiz + participants.
    Route::group(['middleware' => 'permission:training.view'], function () {
        Route::get('training', [TrainingController::class, 'index'])->name('training.index');
        Route::get('training/create', [TrainingController::class, 'create'])->name('training.create');
        Route::post('training', [TrainingController::class, 'store'])->name('training.store');
        Route::get('training/{id}/manage', [TrainingController::class, 'manage'])
             ->whereNumber('id')->name('training.manage');
        Route::post('training/{id}', [TrainingController::class, 'update'])
             ->whereNumber('id')->name('training.update');
        // Materials
        Route::post('training/{id}/material', [TrainingController::class, 'storeMaterial'])
             ->whereNumber('id')->name('training.material.store');
        Route::post('training/{id}/material/{materialId}/delete', [TrainingController::class, 'deleteMaterial'])
             ->whereNumber('id')->whereNumber('materialId')->name('training.material.delete');
        Route::post('training/{id}/material/{materialId}/move', [TrainingController::class, 'moveMaterial'])
             ->whereNumber('id')->whereNumber('materialId')->name('training.material.move');
        // Questions
        Route::post('training/{id}/question', [TrainingController::class, 'storeQuestion'])
             ->whereNumber('id')->name('training.question.store');
        Route::post('training/{id}/question/{questionId}/delete', [TrainingController::class, 'deleteQuestion'])
             ->whereNumber('id')->whereNumber('questionId')->name('training.question.delete');
        // Participants
        Route::post('training/{id}/enroll', [TrainingController::class, 'enroll'])
             ->whereNumber('id')->name('training.enroll');
        Route::post('training/{id}/enrollment/{enrollmentId}/remove', [TrainingController::class, 'unenroll'])
             ->whereNumber('id')->whereNumber('enrollmentId')->name('training.unenroll');
        Route::post('training/{id}/enrollment/{enrollmentId}/reset', [TrainingController::class, 'resetAttempt'])
             ->whereNumber('id')->whereNumber('enrollmentId')->name('training.reset_attempt');
        // Lifecycle
        Route::post('training/{id}/publish', [TrainingController::class, 'publish'])
             ->whereNumber('id')->name('training.publish');
        Route::post('training/{id}/archive', [TrainingController::class, 'archive'])
             ->whereNumber('id')->name('training.archive');
        Route::post('training/{id}/restore', [TrainingController::class, 'restore'])
             ->whereNumber('id')->name('training.restore');
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