<?php

use App\Http\Controllers\Admin\UserCrudController;
use App\Http\Controllers\Career\CandidateAuthController;
use App\Http\Controllers\Career\CareerController;
use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect(\route('presence.scan.public'));
});
Route::get('/admin', function () {
    return redirect('/admin/dashboard');
});

// M13 — switch UI language (persists to user + session).
Route::get('/locale/{locale}', [\App\Http\Controllers\LocaleController::class, 'switch'])->name('locale.switch');

/*
| M17 — Public careers portal + candidate auth ("candidate" guard).
| Fully separate from the admin panel and employee portal.
*/
Route::group(['prefix' => 'karir', 'as' => 'career.', 'middleware' => 'web'], function () {
    // Public
    Route::get('/', [CareerController::class, 'index'])->name('index');
    Route::get('/lowongan/{slug}', [CareerController::class, 'show'])->name('show');

    // Candidate auth
    Route::get('/daftar', [CandidateAuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [CandidateAuthController::class, 'register'])->name('register.submit');
    Route::get('/masuk', [CandidateAuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [CandidateAuthController::class, 'login'])->name('login.submit');
    Route::post('/keluar', [CandidateAuthController::class, 'logout'])->name('logout');

    // Candidate-only
    Route::group(['middleware' => \App\Http\Middleware\EnsureCandidate::class], function () {
        Route::get('/akun', [CareerController::class, 'dashboard'])->name('dashboard');
        Route::post('/lowongan/{slug}/lamar', [CareerController::class, 'apply'])->name('apply');
    });
});

/*
| Employee self-service portal (M4).
|
| Shares Backpack's login and `backpack` guard — there is no second auth
| system. Admins can reach it too; everyone is an employee.
*/
Route::group([
    'prefix'     => 'my',
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        [\App\Http\Middleware\EnsurePortalAccess::class]
    ),
    'as'         => 'portal.',
], function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');

    Route::get('/attendance', [PortalController::class, 'attendance'])->name('attendance');

    Route::get('/salary', [PortalController::class, 'salaryIndex'])->name('salary.index');
    Route::get('/salary/{id}', [PortalController::class, 'salaryShow'])
         ->whereNumber('id')->name('salary.show');
    Route::get('/salary/{id}/print', [PortalController::class, 'salaryPrint'])
         ->whereNumber('id')->name('salary.print');

    Route::get('/leave', [PortalController::class, 'leaveIndex'])->name('leave.index');
    Route::get('/leave/create', [PortalController::class, 'leaveCreate'])->name('leave.create');
    Route::post('/leave', [PortalController::class, 'leaveStore'])->name('leave.store');
    Route::post('/leave/{id}/cancel', [PortalController::class, 'leaveCancel'])
         ->whereNumber('id')->name('leave.cancel');

    Route::get('/loan', [PortalController::class, 'loanIndex'])->name('loan.index');

    Route::get('/profile', [PortalController::class, 'profile'])->name('profile');
    Route::post('/profile', [PortalController::class, 'profileUpdate'])->name('profile.update');
    Route::post('/password', [PortalController::class, 'changePassword'])->name('password.change');

    Route::get('/notifications', [PortalController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/{id}/read', [PortalController::class, 'notificationRead'])
         ->whereNumber('id')->name('notifications.read');
    Route::post('/notifications/mark-all-read', [PortalController::class, 'notificationsMarkAllRead'])
         ->name('notifications.mark_all_read');

    // M11 — Training (participant side). All scoped to the current user.
    Route::get('/training', [\App\Http\Controllers\Portal\TrainingPortalController::class, 'index'])->name('training.index');
    Route::get('/training/{id}', [\App\Http\Controllers\Portal\TrainingPortalController::class, 'show'])
         ->whereNumber('id')->name('training.show');
    Route::get('/training/{id}/quiz', [\App\Http\Controllers\Portal\TrainingPortalController::class, 'quiz'])
         ->whereNumber('id')->name('training.quiz');
    Route::post('/training/{id}/quiz', [\App\Http\Controllers\Portal\TrainingPortalController::class, 'submit'])
         ->whereNumber('id')->name('training.submit');
    Route::get('/training/{id}/result', [\App\Http\Controllers\Portal\TrainingPortalController::class, 'result'])
         ->whereNumber('id')->name('training.result');
    Route::get('/training/{id}/certificate', [\App\Http\Controllers\Portal\TrainingPortalController::class, 'certificate'])
         ->whereNumber('id')->name('training.certificate');
});
