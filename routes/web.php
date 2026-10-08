<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventSessionController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\PublicAttendanceController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Home / Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])
    ->name('home');


/*
|--------------------------------------------------------------------------
| Public Registration
|--------------------------------------------------------------------------
*/

Route::get('/events-public', [RegistrationController::class, 'publicIndex'])
    ->name('registrations.public-index');

Route::get('/events-public/{event}/register', [RegistrationController::class, 'create'])
    ->name('registrations.create');

Route::post('/events-public/{event}/register', [RegistrationController::class, 'store'])
    ->name('registrations.store');

Route::get('/registration/{registration}/success', [RegistrationController::class, 'success'])
    ->name('registrations.success');


/*
|--------------------------------------------------------------------------
| Public Attendance
|--------------------------------------------------------------------------
*/

Route::get('/attendance', [PublicAttendanceController::class, 'enterPin'])
    ->name('attendance.pin');

Route::post('/attendance/verify', [PublicAttendanceController::class, 'verifyPin'])
    ->name('attendance.verify');

Route::get('/attendance/{event}', [PublicAttendanceController::class, 'showForm'])
    ->name('attendance.form');

Route::post('/attendance/{event}', [PublicAttendanceController::class, 'store'])
    ->name('attendance.store');


/*
|--------------------------------------------------------------------------
| Authenticated Event Management
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'organizer'])->group(function () {

    Route::get('/events/{event}/attendance/export', [EventController::class, 'exportAttendance'])
        ->name('events.attendance.export');

    Route::get('/events/{event}/attendance-overview', [EventController::class, 'attendanceOverview'])
        ->name('events.attendance-overview');

    Route::get(
        '/events/{event}/event-sessions/{event_session}/attendance/export',
        [EventSessionController::class, 'exportAttendance']
    )->name('events.event-sessions.attendance.export');

    Route::get(
        '/events/{event}/event-sessions/{event_session}/live-attendance',
        [EventSessionController::class, 'liveAttendance']
    )->name('events.event-sessions.live-attendance');

    Route::get(
        '/events/{event}/event-sessions/{event_session}/live-attendance/data',
        [EventSessionController::class, 'liveAttendanceData']
    )->name('events.event-sessions.live-attendance.data');

    Route::resource('events', EventController::class);

    Route::resource('events.event-sessions', EventSessionController::class);

    Route::get(
        '/events/{event}/registrations',
        [RegistrationController::class, 'eventRegistrations']
    )->name('events.registrations.index');

    Route::patch(
        '/events/{event}/registrations/{registration}/status',
        [RegistrationController::class, 'updateStatus']
    )->name('events.registrations.status');
});


/*
|--------------------------------------------------------------------------
| Admin Configuration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->group(function () {

    Route::resource('event-types', EventTypeController::class);

    Route::resource('locations', LocationController::class);

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-logs.index');

    Route::prefix('accounts')
        ->name('accounts.')
        ->group(function () {

            Route::get('/', [AccountController::class, 'index'])
                ->name('index');

            Route::get('/create', [AccountController::class, 'create'])
                ->name('create');

            Route::post('/', [AccountController::class, 'store'])
                ->name('store');

            Route::get('/{user}/edit', [AccountController::class, 'edit'])
                ->name('edit');

            Route::put('/{user}', [AccountController::class, 'update'])
                ->name('update');

            Route::delete('/{user}', [AccountController::class, 'destroy'])
                ->name('destroy');
        });
});


/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/

Route::view('/profile', 'profile')
    ->middleware('auth')
    ->name('profile');
