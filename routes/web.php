<?php

use App\Http\Controllers\Admin\UserCrudController;
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
});
