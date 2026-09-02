<?php

use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\AttendanceController;

Route::resource('tickets', TicketController::class)->middleware('auth');

Route::patch('tickets/{ticket}/assignment', [TicketController::class, 'assign'])
    ->middleware('auth');

Route::post('tickets/{ticket}/comments', [CommentController::class, 'store']);

Route::get('/whoami', function () {
    return auth()->user();
});

Route::get('/attendance', [AttendanceController::class, 'index'])
    ->middleware('auth')
    ->name('attendance.index');

Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])
    ->middleware('auth')
    ->name('attendance.clock-in');

Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])
    ->middleware('auth')
    ->name('attendance.clock-out');

//the route for attendance events, to create etc..

use App\Http\Controllers\AttendanceEventController;

Route::get('/attendance-events/create', [AttendanceEventController::class, 'create'])
    ->name('attendance-events.create');

Route::post('/attendance-events', [AttendanceEventController::class, 'store'])
    ->name('attendance-events.store');

//the route for attendace pin prompt

use App\Http\Controllers\PublicAttendanceController;

Route::get('/attendance', [PublicAttendanceController::class, 'enterPin'])
    ->name('attendance.pin');

Route::post('/attendance/verify', [PublicAttendanceController::class, 'verifyPin'])
    ->name('attendance.verify');

Route::get('/attendance/{event}', [PublicAttendanceController::class, 'showForm'])
    ->name('attendance.form');
