<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeOnlineAttendanceFixture(array $eventAttributes = []): array
{
    $event = Event::create(array_merge([
        'name' => 'Online attendance event',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ], $eventAttributes));

    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Open session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);

    return [$event, $session];
}

it('records an ordinary online attendance submission against its selected session', function () {
    [$event, $session] = makeOnlineAttendanceFixture();

    $access = makeAttendanceAccessGrant($event);
    $this->withSession($access['session'])
        ->withHeader('X-Attendance-Sync', '1')
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'session_id' => $session->id,
            'full_name' => 'Online Attendee',
            'email' => 'online@example.test',
            'phone' => '60125902441',
            'offline_submission_id' => '86de5837-94c7-488c-b6aa-6361952b2cab',
            'client_submitted_at' => now()->subYear()->toDateTimeString(),
        ])
        ->assertOk()
        ->assertViewIs('attendance.success');

    expect(Attendance::count())->toBe(1)
        ->and(Attendance::sole()->session_id)->toBe($session->id)
        ->and(Attendance::sole()->email)->toBe('online@example.test')
        ->and(Attendance::sole()->offline_submission_id)->toBeNull()
        ->and(Attendance::sole()->client_submitted_at)->toBeNull();
});

it('continues to reject a duplicate check-in for the same session', function () {
    [$event, $session] = makeOnlineAttendanceFixture();
    $payload = [
        'session_id' => $session->id,
        'full_name' => 'Duplicate Attendee',
        'email' => 'duplicate@example.test',
        'phone' => '60125902441',
    ];
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), $payload)
        ->assertOk();

    $this->withSession($access['session'])
        ->from(route('attendance.form', ['access' => $access['access']]))
        ->post(route('attendance.store', ['access' => $access['access']]), $payload)
        ->assertRedirect(route('attendance.form', ['access' => $access['access']]))
        ->assertSessionHasErrors('email');

    expect(Attendance::count())->toBe(1);
});

it('rejects online attendance outside the session attendance window', function () {
    [$event, $session] = makeOnlineAttendanceFixture();
    $session->update([
        'attendance_opens_at' => now()->subHours(2),
        'attendance_closes_at' => now()->subHour(),
    ]);
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->from(route('attendance.form', ['access' => $access['access']]))
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'session_id' => $session->id,
            'full_name' => 'Late Attendee',
            'email' => 'late@example.test',
            'phone' => '60125902441',
        ])
        ->assertRedirect(route('attendance.form', ['access' => $access['access']]))
        ->assertSessionHasErrors('session_id');

    expect(Attendance::count())->toBe(0);
});

it('renders the public form without offline queue assets or cache markers', function () {
    [$event] = makeOnlineAttendanceFixture();
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('id="attendance-form"', false)
        ->assertSee('attendance-service-worker-cleanup.js')
        ->assertDontSee('attendance-offline-sync.js')
        ->assertHeaderMissing('X-Attendance-Offline-Cache');
});

it('keeps the online pre-registration lookup and fills attendance details on a match', function () {
    [$event] = makeOnlineAttendanceFixture();
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Lookup Attendee',
        'guest_email' => 'lookup@example.test',
        'guest_phone' => '0125902441',
        'position' => 'Intern',
        'organisation' => 'Example Organisation',
        'status' => 'approved',
    ]);
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'LOOKUP@example.test',
            'lookup_phone' => '+60 12-590-2441',
        ])
        ->assertRedirect(route('attendance.form', ['access' => $access['access']]))
        ->assertSessionHas('pre_registration_found', true)
        ->assertSessionHas('pre_registration_status', 'approved');

    $this->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('Lookup Attendee')
        ->assertSee('lookup@example.test')
        ->assertSee('60125902441')
        ->assertSee('Intern')
        ->assertSee('Example Organisation');
});

it('keeps manual attendance entry available when online pre-registration lookup has no match', function () {
    [$event] = makeOnlineAttendanceFixture();
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'missing@example.test',
            'lookup_phone' => '11111111',
        ])
        ->assertRedirect(route('attendance.form', ['access' => $access['access']]))
        ->assertSessionHas('pre_registration_not_found', true);

    $this->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('No matching pre-registration was found for this event.')
        ->assertSee('name="full_name"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="phone"', false);
});
