<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

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
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'session_id' => $session->id,
            'full_name' => 'Online Attendee',
            'email' => 'online@example.test',
            'phone' => '60125902441',
        ])
        ->assertOk()
        ->assertViewIs('attendance.success');

    expect(Attendance::count())->toBe(1)
        ->and(Attendance::sole()->session_id)->toBe($session->id)
        ->and(Attendance::sole()->email)->toBe('online@example.test');
});

it('keeps attendance separate from a rejected event pre-registration', function () {
    [$event, $session] = makeOnlineAttendanceFixture();
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Rejected Participant',
        'guest_email' => 'rejected-participant@example.test',
        'guest_phone' => '60125902441',
        'status' => 'rejected',
        'registered_at' => now(),
    ]);
    $access = makeAttendanceAccessGrant($event);
    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'session_id' => $session->id,
            'full_name' => 'Rejected Participant',
            'email' => 'rejected-participant@example.test',
            'phone' => '60125902441',
        ])
        ->assertOk()
        ->assertViewIs('attendance.success');

    expect(Attendance::where('session_id', $session->id)->where('email', 'rejected-participant@example.test')->exists())->toBeTrue();
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

it('renders the online attendance form with connection feedback and legacy worker cleanup', function () {
    [$event] = makeOnlineAttendanceFixture();
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('id="attendance-form"', false)
        ->assertSee('A server connection is required to submit attendance.')
        ->assertSee('attendance-service-worker-cleanup.js')
        ->assertDontSee('attendance-offline-sync.js')
        ->assertDontSee('indexedDB')
        ->assertDontSee('localStorage')
        ->assertHeaderMissing('X-Attendance-Offline-Cache');
});

it('does not disclose registration details to an unverified guest lookup', function () {
    [$event] = makeOnlineAttendanceFixture();
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Private Attendee',
        'guest_email' => 'private@example.test',
        'guest_phone' => '0125902441',
        'position' => 'Private Position',
        'organisation' => 'Private Organisation',
        'status' => 'approved',
    ]);
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'private@example.test',
            'lookup_phone' => '+60 12-590-2441',
        ])
        ->assertRedirect(route('attendance.form', ['access' => $access['access']]));

    $this->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('A matching registration was found. Saved details are available to the attendee or event organizer.')
        ->assertDontSee('Private Attendee')
        ->assertDontSee('Private Position')
        ->assertDontSee('Private Organisation');
});

it('autofills all five saved fields for an authorized staff lookup without account linkage', function () {
    $event = Event::forceCreate([
        'id' => 87,
        'name' => 'Testing Please 2',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'TP82',
    ]);
    EventSession::create([
        'event_id' => $event->id,
        'name' => 'Open session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Aaron Hafidz',
        'guest_email' => 'aaron@example.test',
        'guest_phone' => '0125902441',
        'position' => 'Intern',
        'organisation' => 'C-COM TECHNOLOGY SDN BHD',
        'status' => 'approved',
        'user_id' => null,
    ]);
    $access = makeAttendanceAccessGrant($event);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'aaron@example.test',
            'lookup_phone' => '+60 12-590-2441',
        ])
        ->assertRedirect(route('attendance.form', ['access' => $access['access']]));

    $this->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('Your approved registration details are ready.')
        ->assertSee('Testing Please 2')
        ->assertSee('value="Aaron Hafidz"', false)
        ->assertSee('value="aaron@example.test"', false)
        ->assertSee('value="0125902441"', false)
        ->assertSee('value="Intern"', false)
        ->assertSee('value="C-COM TECHNOLOGY SDN BHD"', false)
        ->assertSee('name="signature"', false);
});

it('keeps missing optional registration fields blank after authorized autofill', function () {
    [$event] = makeOnlineAttendanceFixture();
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Partial Attendee',
        'guest_email' => 'partial@example.test',
        'guest_phone' => '60125902441',
        'position' => null,
        'organisation' => null,
        'status' => 'approved',
    ]);
    $access = makeAttendanceAccessGrant($event);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'partial@example.test',
            'lookup_phone' => '60125902441',
        ])
        ->assertRedirect();

    $this->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('value="Partial Attendee"', false)
        ->assertSee('name="position"', false)
        ->assertSee('name="unit"', false);
});

it('does not disclose pending registration details', function () {
    [$event] = makeOnlineAttendanceFixture();
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Pending Attendee',
        'guest_email' => 'pending@example.test',
        'guest_phone' => '60125902441',
        'position' => 'Intern',
        'organisation' => 'Pending Organisation',
        'status' => 'pending',
    ]);
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'pending@example.test',
            'lookup_phone' => '60125902441',
        ])
        ->assertRedirect(route('attendance.form', ['access' => $access['access']]));

    $this->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('This registration is awaiting approval.')
        ->assertDontSee('Pending Attendee')
        ->assertDontSee('Pending Organisation')
        ->assertDontSee('value="Intern"', false);

});

it('does not match rejected or cancelled registrations for the attendance lookup', function () {
    [$event] = makeOnlineAttendanceFixture();
    foreach (['rejected', 'cancelled'] as $index => $status) {
        Registration::create([
            'event_id' => $event->id,
            'guest_name' => ucfirst($status).' Attendee',
            'guest_email' => $status.'-lookup@example.test',
            'guest_phone' => '6012590244'.($index + 1),
            'position' => 'Private Position',
            'organisation' => 'Private Organisation',
            'status' => $status,
        ]);
    }

    foreach ([
        ['rejected-lookup@example.test', '60125902441'],
        ['cancelled-lookup@example.test', '60125902442'],
    ] as [$email, $phone]) {
        $access = makeAttendanceAccessGrant($event);
        $this->withSession($access['session'])
            ->post(route('attendance.store', ['access' => $access['access']]), [
                'action' => 'lookup_pre_registration',
                'lookup_email' => $email,
                'lookup_phone' => $phone,
            ])
            ->assertRedirect(route('attendance.form', ['access' => $access['access']]));

        $this->get(route('attendance.form', ['access' => $access['access']]))
            ->assertOk()
            ->assertSee('No matching pre-registration was found.')
            ->assertDontSee('Private Position')
            ->assertDontSee('Private Organisation');
    }

});

it('does not autofill from a registration belonging to another event', function () {
    [$event] = makeOnlineAttendanceFixture();
    [$otherEvent] = makeOnlineAttendanceFixture(['name' => 'Different event', 'pin' => 'XY91']);
    Registration::create([
        'event_id' => $otherEvent->id,
        'guest_name' => 'Other Event Attendee',
        'guest_email' => 'other-event@example.test',
        'guest_phone' => '60125902441',
        'position' => 'Intern',
        'organisation' => 'Other Organisation',
        'status' => 'approved',
    ]);
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'other-event@example.test',
            'lookup_phone' => '60125902441',
        ])
        ->assertRedirect(route('attendance.form', ['access' => $access['access']]));

    $this->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertDontSee('Other Event Attendee')
        ->assertDontSee('Other Organisation')
        ->assertSee('No matching pre-registration was found.');

});

it('allows a verified attendee email to autofill and edit details before check-in', function () {
    [$event, $session] = makeOnlineAttendanceFixture();
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Original Name',
        'guest_email' => 'editable@example.test',
        'guest_phone' => '60125902441',
        'position' => 'Original Position',
        'organisation' => 'Original Organisation',
        'status' => 'approved',
        'user_id' => null,
    ]);
    $attendee = User::factory()->create([
        'email' => 'EDITABLE@example.test',
        'email_verified_at' => now(),
        'role' => 'user',
    ]);
    $access = makeAttendanceAccessGrant($event);

    $this->actingAs($attendee)->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'editable@example.test',
            'lookup_phone' => '60125902441',
        ])
        ->assertRedirect();
    $this->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('value="Original Name"', false)
        ->assertSee('value="Original Position"', false)
        ->assertSee('value="Original Organisation"', false);

    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'session_id' => $session->id,
            'full_name' => 'Corrected Name',
            'email' => 'editable@example.test',
            'phone' => '60125902441',
            'position' => 'Corrected Position',
            'unit' => 'Corrected Organisation',
        ])
        ->assertOk()
        ->assertViewIs('attendance.success');

    $attendance = Attendance::sole();
    expect($attendance->full_name)->toBe('Corrected Name')
        ->and($attendance->position)->toBe('Corrected Position')
        ->and($attendance->unit)->toBe('Corrected Organisation');
});

it('returns only a match result from the JSON pre-registration lookup', function () {
    [$event] = makeOnlineAttendanceFixture();
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Private Lookup Attendee',
        'guest_email' => 'private-lookup@example.test',
        'guest_phone' => '0125902441',
        'position' => 'Private Position',
        'organisation' => 'Private Organisation',
        'status' => 'approved',
    ]);
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->postJson(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'private-lookup@example.test',
            'lookup_phone' => '+60 12-590-2441',
        ])
        ->assertOk()
        ->assertExactJson(['status' => 'found']);
});

it('keeps manual attendance entry available when both lookup identifiers do not match', function () {
    [$event] = makeOnlineAttendanceFixture();
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Different Attendee',
        'guest_email' => 'lookup-match@example.test',
        'guest_phone' => '0125902441',
        'position' => 'Intern',
        'organisation' => 'Example Organisation',
        'status' => 'approved',
    ]);
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'action' => 'lookup_pre_registration',
            'lookup_email' => 'lookup-match@example.test',
            'lookup_phone' => '0199999999',
        ])
        ->assertRedirect(route('attendance.form', ['access' => $access['access']]))
        ->assertSessionHas('pre_registration_not_found', true);

    $this->get(route('attendance.form', ['access' => $access['access']]))
        ->assertOk()
        ->assertSee('No matching pre-registration was found. You can still enter your details manually.')
        ->assertSee('name="full_name"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="phone"', false);

});

it('temporarily locks distributed attempts to guess attendance PINs while allowing a valid PIN', function () {
    RateLimiter::clear('attendance-pin:invalid-attempts');
    RateLimiter::clear('attendance-pin:invalid-lockout');
    [$event] = makeOnlineAttendanceFixture(['pin' => 'ABCD']);

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $symbols = '23456789';
        $guess = 'ZZ'.$symbols[intdiv($attempt - 1, 8)].$symbols[($attempt - 1) % 8];
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.'.($attempt % 250 + 1),
        ])->post(route('attendance.verify'), ['pin' => $guess]);

        if ($attempt < 30) {
            $response->assertSessionHasErrors('pin');
        } else {
            $response->assertStatus(429);
        }
    }

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.200'])
        ->post(route('attendance.verify'), ['pin' => $event->pin])
        ->assertRedirect();

    expect(parse_url($response->headers->get('Location'), PHP_URL_PATH))
        ->toMatch('/^\/attendance\/[a-f0-9]{64}$/');
});

it('throttles repeated public attendance submissions for one access grant without recording attendance', function () {
    [$event] = makeOnlineAttendanceFixture();
    $access = makeAttendanceAccessGrant($event);
    $ip = '192.0.2.88';
    RateLimiter::clear('attendance-access:'.hash('sha256', $access['access'].'|'.$ip));
    RateLimiter::clear('attendance-ip:'.$ip);

    for ($attempt = 1; $attempt <= 31; $attempt++) {
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withSession($access['session'])
            ->post(route('attendance.store', ['access' => $access['access']]), [
                'action' => 'lookup_pre_registration',
                'lookup_email' => 'missing-'.$attempt.'@example.test',
                'lookup_phone' => '11111111',
            ]);
    }

    $response->assertStatus(429);
    expect(Attendance::count())->toBe(0);
});
