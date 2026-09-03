<?php

use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\AttendanceController;
use function Pest\Laravel\post;

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
    
Route::view('/event-attendance', 'event-attendance.pin')
    ->name('event-attendance.pin');

Route::view('/event-attendance/form', 'event-attendance.form')
    ->name('event-attendance.form');

Route::view('/event-admin/dashboard', 'event-admin.dashboard')
    ->name('event-admin.dashboard');

Route::view('/event-admin/event/edit', 'event-admin.edit-event')
    ->name('event-admin.event.edit');

Route::view('/event-admin/attendance/edit', 'event-admin.edit-attendance')
    ->name('event-admin.attendance.edit');