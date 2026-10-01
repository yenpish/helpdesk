<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceEventController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PublicAttendanceController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\EventSessionController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\AuditLogController;

Route::get('/', [HomeController::class, 'index'])
    ->name('home');

Route::resource('events', EventController::class)->middleware(['auth', 'organizer']);

Route::resource('event-types', EventTypeController::class)
    ->middleware(['auth', 'admin']);

Route::resource('locations', LocationController::class)
    ->middleware(['auth', 'admin']);

Route::resource('events.event-sessions', EventSessionController::class)
    ->middleware(['auth', 'organizer']);

Route::get('/audit-logs', [AuditLogController::class, 'index'])
    ->middleware(['auth', 'admin'])
    ->name('audit-logs.index');


Route::resource('tickets', TicketController::class)->middleware('auth');

Route::patch('tickets/{ticket}/assignment', [TicketController::class, 'assign'])
    ->middleware('auth');

Route::post('tickets/{ticket}/comments', [CommentController::class, 'store']);

Route::get('/whoami', function () {
    return auth()->user();
});

Route::get('/events-public', [RegistrationController::class, 'publicIndex'])
    ->name('registrations.public-index');

Route::get('/events-public/{event}/register', [RegistrationController::class, 'create'])
    ->name('registrations.create');

Route::post('/events-public/{event}/register', [RegistrationController::class, 'store'])
    ->name('registrations.store');

Route::get('/registration/{registration}/success', [RegistrationController::class, 'success'])
    ->name('registrations.success');

Route::middleware(['auth', 'organizer'])->group(function () {
    Route::get(
        '/events/{event}/registrations',
        [RegistrationController::class, 'eventRegistrations']
    )->name('events.registrations.index');

    Route::patch(
        '/events/{event}/registrations/{registration}/status',
        [RegistrationController::class, 'updateStatus']
    )->name('events.registrations.status');
});

Route::get('/attendance', [PublicAttendanceController::class, 'enterPin'])
    ->name('attendance.pin');

Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])
    ->middleware('auth')
    ->name('attendance.clock-in');

Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])
    ->middleware('auth')
    ->name('attendance.clock-out');

Route::middleware(['auth', 'organizer'])->group(function () {
    Route::get('/attendance-events/create', [AttendanceEventController::class, 'create'])
        ->name('attendance-events.create');

    Route::post('/attendance-events', [AttendanceEventController::class, 'store'])
        ->name('attendance-events.store');

    Route::post('/attendance-events/export', [AttendanceEventController::class, 'exportSelected'])
        ->name('attendance-events.export-selected');

    Route::get('/attendance-events/{event}/edit', [AttendanceEventController::class, 'edit'])
        ->name('attendance-events.edit');

    Route::patch('/attendance-events/{event}', [AttendanceEventController::class, 'update'])
        ->name('attendance-events.update');

    Route::get('/attendance-events/{event}', [AttendanceEventController::class, 'show'])
        ->name('attendance-events.show');

    Route::delete('/attendance-events/{event}', [AttendanceEventController::class, 'destroy'])
        ->name('attendance-events.destroy');

    Route::get('/attendance-events', [AttendanceEventController::class, 'index'])
        ->name('attendance-events.index');

    Route::get('/attendance-events/{event}/export', [AttendanceEventController::class, 'export'])
        ->name('attendance-events.export');

    Route::get('/users/create', [UserController::class, 'create'])
        ->name('users.create');

    Route::post('/users', [UserController::class, 'store'])
        ->name('users.store');

    Route::get('/users', [UserController::class, 'index'])
        ->name('users.index');

    Route::get('/users/{user}', [UserController::class, 'show'])
        ->name('users.show');
});

Route::post('/attendance/verify', [PublicAttendanceController::class, 'verifyPin'])
    ->name('attendance.verify');

Route::get('/attendance/{event}', [PublicAttendanceController::class, 'showForm'])
    ->name('attendance.form');

Route::post('/attendance/{event}', [PublicAttendanceController::class, 'store'])
    ->name('attendance.store');

Route::view('/profile', 'profile')
    ->middleware('auth')
    ->name('profile');

Route::middleware(['auth', 'admin'])
    ->prefix('accounts')
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
