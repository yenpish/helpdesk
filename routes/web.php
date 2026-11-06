
<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventSessionController;
use App\Http\Controllers\DeletedItemsController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PublicAttendanceController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RoleController;
use App\Support\EncryptedRouteIdentifier;
use Laravel\Passkeys\Passkey;
use Illuminate\Support\Facades\Route;

Route::bind('passkey', function (string $value): Passkey {
    $id = EncryptedRouteIdentifier::decode($value, Passkey::class);
    abort_if($id === null, 404);

    return Passkey::query()->findOrFail($id);
});

/*
|--------------------------------------------------------------------------
| Encrypted Attendance Link
|--------------------------------------------------------------------------
*/

Route::get('/attendance-link/{token}', [
    PublicAttendanceController::class,
    'openEncryptedLink',
])->middleware('throttle:120,1')->name('attendance.encrypted');

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

Route::get('/events-public', [
    RegistrationController::class,
    'publicIndex',
])->name('registrations.public-index');

Route::get('/events-public/{event}/register', [
    RegistrationController::class,
    'create',
])->name('registrations.create');

Route::post('/events-public/{event}/register', [
    RegistrationController::class,
    'store',
])->name('registrations.store');

Route::get('/registration/{registration}/success', [
    RegistrationController::class,
    'success',
])->name('registrations.success');

/*
|--------------------------------------------------------------------------
| Public Attendance
|--------------------------------------------------------------------------
*/

Route::get('/attendance', [
    PublicAttendanceController::class,
    'enterPin',
])->name('attendance.pin');

Route::post('/attendance/verify', [
    PublicAttendanceController::class,
    'verifyPin',
])->middleware('throttle:5,1')->name('attendance.verify');

Route::get('/attendance/{access}', [
    PublicAttendanceController::class,
    'showForm',
])->name('attendance.form');

Route::post('/attendance/{access}', [
    PublicAttendanceController::class,
    'store',
])->middleware('throttle:attendance-submit')->name('attendance.store');

/*
|--------------------------------------------------------------------------
| Public Feedback Submission
|--------------------------------------------------------------------------
| Participants can submit feedback without logging in.
*/
Route::get('/events/{event}/feedback/invitation/{token}', [
    FeedbackController::class,
    'invitationCreate',
])->name('feedback.invitation.show');

Route::post('/events/{event}/feedback/invitation/{token}', [
    FeedbackController::class,
    'invitationStore',
])->name('feedback.invitation.store');

/*
|--------------------------------------------------------------------------
| Authenticated Event Management
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'organizer'])->group(function () {

    Route::get('/events/{event}/attendance/export', [
        EventController::class,
        'exportAttendance',
    ])->middleware('permission:events.manage')->name('events.attendance.export');

    Route::post('/events/{event}/notifications/{notificationKey}/retry', [
        EventController::class,
        'retryNotifications',
    ])->middleware('permission:events.manage')->name('events.notifications.retry');

    Route::get(
        '/events/{event}/event-sessions/{event_session}/attendance/export',
        [EventSessionController::class, 'exportAttendance']
    )->middleware('permission:events.manage')->name('events.event-sessions.attendance.export');

    Route::post('/events/{event}/feedback/invitations/send', [
        FeedbackController::class,
        'sendInvitations',
    ])->middleware('permission:events.manage')->name('feedback.invitations.send');

    Route::post('/events/{event}/feedback/invitations/retry-failed', [
        FeedbackController::class,
        'retryFailedInvitations',
    ])->middleware('permission:events.manage')->name('feedback.invitations.retry-failed');

    /*
    |--------------------------------------------------------------------------
    | Feedback Results
    |--------------------------------------------------------------------------
    | Login and organizer middleware required.
    | FeedbackController@index must also authorize event ownership.
    */

    Route::get('/events/{event}/feedback/results', [
        FeedbackController::class,
        'index',
    ])->middleware('permission:events.manage')->name('feedback.index');

    /*
    |--------------------------------------------------------------------------
    | Event Resources
    |--------------------------------------------------------------------------
    */

    Route::get('/events/deleted', [DeletedItemsController::class, 'index'])
        ->name('events.deleted');
    Route::post('/events/deleted/{eventId}/restore', [DeletedItemsController::class, 'restoreEvent'])
        ->name('events.deleted.restore');
    Route::delete('/events/deleted/{eventId}/permanent', [DeletedItemsController::class, 'permanentlyDeleteEvent'])
        ->name('events.deleted.permanent');
    Route::post('/events/deleted/sessions/{sessionId}/restore', [DeletedItemsController::class, 'restoreSession'])
        ->name('events.deleted.sessions.restore');
    Route::delete('/events/deleted/sessions/{sessionId}/permanent', [DeletedItemsController::class, 'permanentlyDeleteSession'])
        ->name('events.deleted.sessions.permanent');

    Route::resource('events', EventController::class)
        ->middleware('permission:events.manage');

    Route::resource(
        'events.event-sessions',
        EventSessionController::class
    )->middleware('permission:events.manage');

    Route::patch(
        '/events/{event}/event-sessions/{event_session}/attendance/{attendance}',
        [EventSessionController::class, 'updateAttendanceDetails']
    )->middleware('permission:events.manage')->name('events.event-sessions.attendance.update');
Route::get(
    '/events/{event}/event-sessions/{event_session}/attendance/{attendance}/edit',
    [EventSessionController::class, 'editAttendanceDetails']
)->middleware('permission:events.manage')->name('events.event-sessions.attendance.edit');

    /*
    |--------------------------------------------------------------------------
    | Registrations
    |--------------------------------------------------------------------------
    */

    Route::get('/events/{event}/registrations', [
        RegistrationController::class,
        'eventRegistrations',
    ])->middleware('permission:events.manage')->name('events.registrations.index');

    Route::patch(
        '/events/{event}/registrations/{registration}/status',
        [RegistrationController::class, 'updateStatus']
    )->middleware('permission:events.manage')->name('events.registrations.status');

    Route::patch(
        '/events/{event}/registrations/{registration}/details',
        [RegistrationController::class, 'updateDetails']
    )->middleware('permission:events.manage')->name('events.registrations.details');
Route::get('/events/{event}/registrations/{registration}/edit', [
    RegistrationController::class,
    'editDetails',
])->middleware('permission:events.manage')->name('events.registrations.edit');
});

/*
|--------------------------------------------------------------------------
| Publish Event
|--------------------------------------------------------------------------
*/

Route::post('/events/{event}/publish', [
    EventController::class,
    'publish',
])
    ->middleware(['auth', 'organizer', 'permission:events.publish'])
    ->name('events.publish');

/*
|--------------------------------------------------------------------------
| Admin Configuration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->group(function () {

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    Route::resource('event-types', EventTypeController::class);

    Route::resource('locations', LocationController::class);

    Route::get('/audit-logs', [
        AuditLogController::class,
        'index',
    ])->name('audit-logs.index');

    Route::prefix('accounts')
        ->name('accounts.')
        ->group(function () {

            Route::get('/', [
                AccountController::class,
                'index',
            ])->name('index');

            Route::get('/create', [
                AccountController::class,
                'create',
            ])->name('create');

            Route::post('/', [
                AccountController::class,
                'store',
            ])->name('store');

            Route::get('/{user}/edit', [
                AccountController::class,
                'edit',
            ])->name('edit');

            Route::put('/{user}', [
                AccountController::class,
                'update',
            ])->name('update');

            Route::delete('/{user}', [
                AccountController::class,
                'destroy',
            ])->name('destroy');
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

Route::middleware('auth')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\AppearanceSettingsController::class, 'edit'])
        ->name('settings.edit');
    Route::put('/settings/appearance', [\App\Http\Controllers\AppearanceSettingsController::class, 'update'])
        ->name('settings.appearance.update');
});
