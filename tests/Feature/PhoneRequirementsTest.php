<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\User;
use App\Support\MalaysianPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires a valid phone number for event registration', function () {
    $event = Event::create([
        'name' => 'Phone required event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'ABCD',
    ]);

    $this->from(route('registrations.create', $event))
        ->post(route('registrations.store', $event), [
            'guest_name' => 'Test Attendee',
            'guest_email' => 'attendee@example.test',
            'guest_phone' => '',
        ])
        ->assertRedirect(route('registrations.create', $event))
        ->assertSessionHasErrors('guest_phone');

    expect(Registration::count())->toBe(0);
});

it('rejects a duplicate phone number within the same event after normalizing punctuation', function () {
    $event = Event::create([
        'name' => 'One event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'EFGH',
    ]);

    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Existing Attendee',
        'guest_email' => 'existing@example.test',
        'guest_phone' => '012-345 6789',
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    $this->from(route('registrations.create', $event))
        ->post(route('registrations.store', $event), [
            'guest_name' => 'Another Attendee',
            'guest_email' => 'another@example.test',
            'guest_phone' => '0123456789',
        ])
        ->assertRedirect(route('registrations.create', $event))
        ->assertSessionHasErrors('guest_phone');

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

it('keeps event registration email duplicate protection', function () {
    $event = Event::create([
        'name' => 'Email duplicate event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'ABEF',
    ]);
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Existing Attendee',
        'guest_email' => 'person@example.test',
        'guest_phone' => '60125902441',
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    $this->from(route('registrations.create', $event))
        ->post(route('registrations.store', $event), [
            'guest_name' => 'Duplicate Email',
            'guest_email' => 'PERSON@example.test',
            'guest_phone' => '60129998877',
        ])
        ->assertRedirect(route('registrations.create', $event))
        ->assertSessionHasErrors('guest_email');

    expect(Registration::where('event_id', $event->id)->count())->toBe(1);
});

it('allows the same phone number to register for different events', function () {
    $firstEvent = Event::create([
        'name' => 'First event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'JKLM',
    ]);
    $secondEvent = Event::create([
        'name' => 'Second event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'NPQR',
    ]);

    Registration::create([
        'event_id' => $firstEvent->id,
        'guest_name' => 'Existing Attendee',
        'guest_email' => 'first@example.test',
        'guest_phone' => '012-345 6789',
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    $this->post(route('registrations.store', $secondEvent), [
        'guest_name' => 'Same Attendee',
        'guest_email' => 'second@example.test',
        'guest_phone' => '012 345-6789',
    ])->assertRedirect(route('registrations.success', Registration::latest('id')->first()));

    expect(Registration::where('event_id', $secondEvent->id)->count())->toBe(1);
});

it('canonicalizes common Malaysian phone formats', function () {
    foreach ([
        '0125902441',
        '60125902441',
        '+60125902441',
        '012-590-2441',
        '012 590 2441',
        '+60 12 590 2441',
    ] as $phone) {
        expect(MalaysianPhoneNumber::canonicalize($phone))->toBe('60125902441');
    }
});

it('rejects equivalent phone representations for attendance in the same event', function () {
    $event = Event::create([
        'name' => 'Duplicate attendance event',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'CDEF',
    ]);
    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Open session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);

    $this->withSession(['attendance_event_id' => $event->id])
        ->post(route('attendance.store', $event), [
            'session_id' => $session->id,
            'full_name' => 'First attendee',
            'email' => 'first@example.test',
            'phone' => '0125902441',
        ])->assertViewIs('attendance.success');

    foreach (['+60125902441', '60125902441', '012-590-2441', '+60 12 590 2441'] as $phone) {
        $this->withSession(['attendance_event_id' => $event->id])
            ->from(route('attendance.form', $event))
            ->post(route('attendance.store', $event), [
                'session_id' => $session->id,
                'full_name' => 'Another attendee',
                'email' => 'another@example.test',
                'phone' => $phone,
            ])
            ->assertRedirect(route('attendance.form', $event))
            ->assertSessionHasErrors('phone');
    }

    expect(Attendance::where('session_id', $session->id)->count())->toBe(1);
});

it('keeps email duplicate protection and allows the same attendee at another session', function () {
    $event = Event::create([
        'name' => 'Multi-session event',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'GHIJ',
    ]);
    $sessions = collect(['Session One', 'Session Two'])->map(fn ($name) => EventSession::create([
        'event_id' => $event->id,
        'name' => $name,
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]));
    Attendance::create([
        'session_id' => $sessions[0]->id,
        'full_name' => 'Returning attendee',
        'email' => 'returning@example.test',
        'phone' => '60125902441',
    ]);

    $this->withSession(['attendance_event_id' => $event->id])
        ->from(route('attendance.form', $event))
        ->post(route('attendance.store', $event), [
            'session_id' => $sessions[0]->id,
            'full_name' => 'Returning attendee',
            'email' => 'returning@example.test',
            'phone' => '0125902441',
        ])->assertSessionHasErrors(['email', 'phone']);

    $this->withSession(['attendance_event_id' => $event->id])
        ->post(route('attendance.store', $event), [
            'session_id' => $sessions[1]->id,
            'full_name' => 'Returning attendee',
            'email' => 'returning@example.test',
            'phone' => '+60 12 590 2441',
        ])->assertViewIs('attendance.success');

    expect(Attendance::whereIn('session_id', $sessions->pluck('id'))->count())->toBe(2);
});

it('shows registrations and session attendance together in one session report', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = Event::create([
        'name' => 'Unified report event',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'organizer_id' => $organizer->id,
        'status' => 'published',
        'pin' => 'KLMN',
    ]);
    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Report session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Registered Person',
        'guest_email' => 'registered@example.test',
        'guest_phone' => '60125902441',
        'organisation' => 'Registered Org',
        'status' => 'pending',
        'registered_at' => now(),
    ]);
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Attended Person',
        'guest_email' => 'attended@example.test',
        'guest_phone' => '60128887766',
        'organisation' => 'Attended Org',
        'status' => 'pending',
        'registered_at' => now(),
    ]);
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Approved No Show',
        'guest_email' => 'noshow@example.test',
        'guest_phone' => '60125554433',
        'organisation' => 'No Show Org',
        'status' => 'approved',
        'registered_at' => now(),
    ]);
    Attendance::create([
        'session_id' => $session->id,
        'full_name' => 'Attended Person',
        'email' => 'attended@example.test',
        'phone' => '60128887766',
        'unit' => 'Attended Org',
    ]);
    Attendance::create([
        'session_id' => $session->id,
        'full_name' => 'Walk In',
        'email' => 'walkin@example.test',
        'phone' => '60127776655',
        'unit' => 'Walk In Org',
    ]);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('Registration &amp; Attendance', false)
        ->assertSee('Registered Person')
        ->assertSee('Registration')
        ->assertSee('Attendance')
        ->assertSee('Pending')
        ->assertSee('Not attended')
        ->assertSee('Attended Person')
        ->assertSee('Attended')
        ->assertSee('Approved No Show')
        ->assertSee('Approved')
        ->assertSee('Walk In')
        ->assertSee('Not registered')
        ->assertSee('Manage Registrations')
        ->assertSee('Export Session CSV')
        ->assertDontSee('Unregistered Attendance')
        ->assertDontSee('Session outcome');

    $eventDetails = $this->get(route('events.show', $event))->assertOk();
    expect(substr_count($eventDetails->getContent(), 'Manage Sessions'))->toBe(1);
});

it('searches and sorts the event management list', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach ([
        ['name' => 'Gamma Planning', 'starts_at' => now()->addDays(3), 'pin' => 'QWER'],
        ['name' => 'Alpha Workshop', 'starts_at' => now()->addDay(), 'pin' => 'TYUI'],
        ['name' => 'Beta Workshop', 'starts_at' => now()->addDays(2), 'pin' => 'OPAS'],
    ] as $eventData) {
        Event::create($eventData + [
            'description' => 'Search and sort test event',
            'ends_at' => $eventData['starts_at']->copy()->addHour(),
            'status' => 'published',
        ]);
    }

    $this->actingAs($admin)
        ->get(route('events.index', ['search' => 'Workshop', 'sort' => 'name_asc']))
        ->assertOk()
        ->assertSeeInOrder(['Alpha Workshop', 'Beta Workshop'])
        ->assertDontSee('Gamma Planning')
        ->assertSee('value="Workshop"', false)
        ->assertSee('value="name_asc" selected', false);
});

it('omits the selected session summary while retaining the time details on attendance entry', function () {
    $event = Event::create([
        'name' => 'Session form event',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'DFGH',
    ]);
    EventSession::create([
        'event_id' => $event->id,
        'name' => 'Current session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);

    $this->withSession(['attendance_event_id' => $event->id])
        ->get(route('attendance.form', $event))
        ->assertOk()
        ->assertDontSee('Selected Session')
        ->assertSee('Time');
});

it('requires a valid phone number for session attendance', function () {
    $event = Event::create([
        'name' => 'Open event',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'STUV',
    ]);
    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Open session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);

    $this->withSession(['attendance_event_id' => $event->id])
        ->from(route('attendance.form', $event))
        ->post(route('attendance.store', $event), [
            'session_id' => $session->id,
            'full_name' => 'Test Attendee',
            'email' => 'attendee@example.test',
            'phone' => '',
        ])
        ->assertRedirect(route('attendance.form', $event))
        ->assertSessionHasErrors('phone');

    expect(Attendance::count())->toBe(0);
});

it('exports session attendance for an event without signatures or geolocation', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = Event::create([
        'name' => 'Export event',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'organizer_id' => $organizer->id,
        'status' => 'published',
        'pin' => 'WXYZ',
    ]);
    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Export session',
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
        'attendance_opens_at' => now(),
        'attendance_closes_at' => now()->addHour(),
    ]);
    $otherSession = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Another session',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'attendance_opens_at' => now()->addDay(),
        'attendance_closes_at' => now()->addDay()->addHour(),
    ]);
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'CSV Attendee',
        'guest_email' => 'csv@example.test',
        'guest_phone' => '60123456789',
        'organisation' => 'Example Org',
        'status' => 'approved',
        'registered_at' => now(),
    ]);
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Registered No Show',
        'guest_email' => 'noshow@example.test',
        'guest_phone' => '60129876543',
        'organisation' => 'Pre-enrolled Org',
        'status' => 'pending',
        'registered_at' => now(),
    ]);
    Attendance::create([
        'session_id' => $session->id,
        'full_name' => 'CSV Attendee',
        'email' => 'csv@example.test',
        'phone' => '012-345 6789',
        'position' => 'Intern',
        'unit' => 'Example Org',
        'signature' => 'private-signature-value',
        'latitude' => '3.1234567',
        'longitude' => '101.1234567',
    ]);
    Attendance::create([
        'session_id' => $otherSession->id,
        'full_name' => 'Other Session Attendee',
        'email' => 'other@example.test',
        'phone' => '60123456789',
    ]);

    $response = $this->actingAs($organizer)
        ->get(route('events.attendance.export', $event))
        ->assertOk()
        ->assertStreamed();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Export event')
        ->toContain('Export session')
        ->toContain('CSV Attendee')
        ->toContain('csv@example.test')
        ->toContain('Another session')
        ->toContain('Other Session Attendee')
        ->toContain('other@example.test')
        ->toContain('Registration Status')
        ->toContain('Attendance Status')
        ->toContain('Registered No Show')
        ->toContain('Pending')
        ->toContain('Not attended')
        ->toContain('Not registered')
        ->not->toContain('private-signature-value')
        ->not->toContain('3.1234567')
        ->not->toContain('101.1234567');

    $sessionResponse = $this->get(route('events.event-sessions.attendance.export', [$event, $session]))
        ->assertOk()
        ->assertStreamed();
    $sessionCsv = $sessionResponse->streamedContent();

    expect($sessionCsv)
        ->toContain('Session')
        ->toContain('Export session')
        ->toContain('CSV Attendee')
        ->not->toContain('Another session')
        ->not->toContain('Other Session Attendee')
        ->not->toContain('other@example.test')
        ->not->toContain('private-signature-value')
        ->not->toContain('3.1234567')
        ->not->toContain('101.1234567');
});
