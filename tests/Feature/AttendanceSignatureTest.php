<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSignatureTestSession(User $organizer): array
{
    $event = Event::create([
        'name' => 'Signature test event',
        'organizer_id' => $organizer->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);

    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Signature test session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);

    return [$event, $session];
}

function makeSignatureTestAttendance(EventSession $session, ?string $signature): Attendance
{
    return Attendance::create([
        'session_id' => $session->id,
        'full_name' => 'Signature Attendee',
        'email' => 'signature@example.test',
        'phone' => '60125902441',
        'signature' => $signature,
        'verification_method' => 'pin',
    ]);
}

it('persists the submitted PNG signature on a normal online attendance record', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeSignatureTestSession($organizer);
    $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/a2YAAAAASUVORK5CYII=';
    $access = makeAttendanceAccessGrant($event);

    $this->withSession($access['session'])
        ->post(route('attendance.store', ['access' => $access['access']]), [
            'session_id' => $session->id,
            'full_name' => 'Signed Attendee',
            'email' => 'signed@example.test',
            'phone' => '60125902441',
            'signature' => $signature,
        ])
        ->assertOk()
        ->assertViewIs('attendance.success');

    expect(Attendance::sole()->signature)->toBe($signature);
});

it('allows an admin and the owning organizer to view the saved signature action', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $admin = User::factory()->create(['role' => 'admin']);
    [$event, $session] = makeSignatureTestSession($organizer);
    $signature = 'data:image/png;base64,' . base64_encode('saved-signature');
    makeSignatureTestAttendance($session, $signature);

    foreach ([$admin, $organizer] as $viewer) {
        $this->actingAs($viewer)
            ->get(route('events.event-sessions.show', [$event, $session]))
            ->assertOk()
            ->assertSee('aria-label="View signature"', false)
            ->assertSee('title="View signature"', false)
            ->assertSee('data-signature="' . $signature . '"', false)
            ->assertSee('attendance-signature-dialog', false)
            ->assertDontSee('<th>Signature', false);
    }
});

it('denies an unrelated organizer access to attendee signatures', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeSignatureTestSession($owner);
    $signature = 'data:image/png;base64,' . base64_encode('private-signature');
    makeSignatureTestAttendance($session, $signature);

    $this->actingAs($otherOrganizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertForbidden()
        ->assertDontSee($signature, false);
});

it('shows a no-signature message for attendance records without a saved signature', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeSignatureTestSession($organizer);
    makeSignatureTestAttendance($session, null);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('aria-label="No signature recorded" title="No signature recorded"', false)
        ->assertSee('No signature recorded');
});
