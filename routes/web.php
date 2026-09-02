<?php

use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceEventController;
use App\Http\Controllers\PublicAttendanceController;

Route::get('/', function () {return view('home');})->name('home');

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

//the route for attendance events, to create, display, etc

Route::middleware('auth')->group(function () {
    Route::get('/attendance-events/create', [AttendanceEventController::class, 'create'])
        ->name('attendance-events.create');

    Route::post('/attendance-events', [AttendanceEventController::class, 'store'])
        ->name('attendance-events.store');

    Route::get('/attendance-events/{event}', [AttendanceEventController::class, 'show'])
        ->name('attendance-events.show');

    Route::get('/attendance-events', [AttendanceEventController::class, 'index'])
        ->name('attendance-events.index');
});

//the route for attendace pin prompt & form, etc

Route::post('/attendance/verify', [PublicAttendanceController::class, 'verifyPin'])
    ->name('attendance.verify');

Route::get('/attendance/{event}', [PublicAttendanceController::class, 'showForm'])
    ->name('attendance.form');

Route::post('/attendance/{event}', [PublicAttendanceController::class, 'store'])
    ->name('attendance.store');
