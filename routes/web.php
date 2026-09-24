<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceEventController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PublicAttendanceController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::resource('tickets', TicketController::class)->middleware('auth');

Route::patch('tickets/{ticket}/assignment', [TicketController::class, 'assign'])
    ->middleware('auth');

Route::post('tickets/{ticket}/comments', [CommentController::class, 'store']);

Route::get('/whoami', function () {
    return auth()->user();
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
