<?php

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAuthorizationTestEvent(User $organizer, string $name): Event
{
    return Event::create([
        'name' => $name,
        'organizer_id' => $organizer->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'status' => 'draft',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);
}

it('denies event mutations by another organizer', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    $event = makeAuthorizationTestEvent($owner, 'Protected event');

    $this->actingAs($otherOrganizer)
        ->put(route('events.update', $event), [
            'name' => 'Tampered event',
            'starts_at' => $event->starts_at->toDateTimeString(),
            'ends_at' => $event->ends_at->toDateTimeString(),
            'status' => 'draft',
        ])
        ->assertForbidden();

    expect($event->fresh()->name)->toBe('Protected event');
});

it('rejects a session ID that does not belong to the event in nested routes', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $firstEvent = makeAuthorizationTestEvent($organizer, 'First event');
    $secondEvent = makeAuthorizationTestEvent($organizer, 'Second event');
    $session = EventSession::create([
        'event_id' => $secondEvent->id,
        'name' => 'Second event session',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'attendance_opens_at' => now()->addDay(),
        'attendance_closes_at' => now()->addDay()->setTime(23, 59),
    ]);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.show', [$firstEvent, $session]))
        ->assertNotFound();

    $this->actingAs($organizer)
        ->put(route('events.event-sessions.update', [$firstEvent, $session]), [
            'name' => 'Tampered session',
            'session_date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])
        ->assertNotFound();

    expect($session->fresh()->name)->toBe('Second event session');
});

it('rejects a registration ID from another event when changing registration status', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $firstEvent = makeAuthorizationTestEvent($organizer, 'Registration owner event');
    $secondEvent = makeAuthorizationTestEvent($organizer, 'Registration source event');
    $registration = Registration::create([
        'event_id' => $secondEvent->id,
        'guest_name' => 'Guest Attendee',
        'guest_email' => 'guest@example.test',
        'guest_phone' => '60125902441',
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    $this->actingAs($organizer)
        ->patch(route('events.registrations.status', [$firstEvent, $registration]), [
            'status' => 'approved',
        ])
        ->assertNotFound();

    expect($registration->fresh()->status)->toBe('pending');
});
