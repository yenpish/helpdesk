<?php

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeConflictLocation(string $name): Location
{
    return Location::create([
        'name' => $name,
        'latitude' => 3.1390000,
        'longitude' => 101.6869000,
        'allowed_radius' => 150,
    ]);
}

function makeConflictEvent(
    User $organizer,
    ?Location $location,
    string $name,
    string $eventStart = '2026-11-10 09:00:00',
    string $eventEnd = '2026-11-10 17:00:00'
): Event {
    return Event::create([
        'name' => $name,
        'organizer_id' => $organizer->id,
        'location_id' => $location?->id,
        'starts_at' => $eventStart,
        'ends_at' => $eventEnd,
        'status' => 'draft',
    ]);
}

function makeConflictSession(Event $event, string $name, string $start, string $end): EventSession
{
    return EventSession::create([
        'event_id' => $event->id,
        'name' => $name,
        'starts_at' => $start,
        'ends_at' => $end,
        'attendance_opens_at' => $start,
        'attendance_closes_at' => $end,
    ]);
}

it('rejects a cross-event overlap at the same location without exposing another organizers event', function () {
    $location = makeConflictLocation('Shared room');
    $owner = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    $existingEvent = makeConflictEvent($otherOrganizer, $location, 'Private planning event');
    makeConflictSession($existingEvent, 'Private session', '2026-11-10 10:00:00', '2026-11-10 12:00:00');
    $event = makeConflictEvent($owner, $location, 'My event');

    $this->actingAs($owner)
        ->from(route('events.event-sessions.create', $event))
        ->post(route('events.event-sessions.store', $event), [
            'name' => 'Overlapping session',
            'starts_at' => '2026-11-10T11:00',
            'ends_at' => '2026-11-10T13:00',
        ])
        ->assertRedirect(route('events.event-sessions.create', $event))
        ->assertSessionHasErrors('starts_at');

    $message = session('errors')->getBag('default')->first('starts_at');
    expect($message)->toContain('A session at this location already overlaps this time.')
        ->not->toContain('Private planning event')
        ->not->toContain('Private session');

    expect($event->sessions()->count())->toBe(0);
});

it('allows overlaps at different locations and adjacent sessions at the same location', function () {
    $firstLocation = makeConflictLocation('Room A');
    $secondLocation = makeConflictLocation('Room B');
    $organizer = User::factory()->create(['role' => 'organizer']);
    $firstEvent = makeConflictEvent($organizer, $firstLocation, 'First event');
    makeConflictSession($firstEvent, 'First session', '2026-11-10 10:00:00', '2026-11-10 12:00:00');
    $otherLocationEvent = makeConflictEvent($organizer, $secondLocation, 'Other location event');

    $this->actingAs($organizer)
        ->post(route('events.event-sessions.store', $otherLocationEvent), [
            'name' => 'Different location overlap',
            'starts_at' => '2026-11-10T11:00',
            'ends_at' => '2026-11-10T13:00',
        ])
        ->assertRedirect(route('events.show', $otherLocationEvent));

    $this->post(route('events.event-sessions.store', $firstEvent), [
        'name' => 'Adjacent session',
        'starts_at' => '2026-11-10T12:00',
        'ends_at' => '2026-11-10T14:00',
    ])->assertRedirect(route('events.show', $firstEvent));

    expect($otherLocationEvent->sessions()->count())->toBe(1)
        ->and($firstEvent->sessions()->count())->toBe(2);
});

it('excludes the current session on edit and blocks a move that overlaps another session', function () {
    $location = makeConflictLocation('Edit room');
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = makeConflictEvent($organizer, $location, 'Editable event');
    makeConflictSession($event, 'Session 1', '2026-11-10 10:00:00', '2026-11-10 12:00:00');
    $sessionToEdit = makeConflictSession($event, 'Session 2', '2026-11-10 13:00:00', '2026-11-10 14:00:00');

    expect(app(\App\Support\SessionScheduleConflictChecker::class)->conflictMessage(
        $location->id,
        [[
            'name' => 'Session 2',
            'event_name' => $event->name,
            'starts_at' => \Carbon\Carbon::parse('2026-11-10 11:00:00'),
            'ends_at' => \Carbon\Carbon::parse('2026-11-10 13:00:00'),
        ]],
        null,
        $sessionToEdit->id
    ))->not->toBeNull();

    $response = $this->actingAs($organizer)
        ->from(route('events.event-sessions.edit', [$event, $sessionToEdit]))
        ->put(route('events.event-sessions.update', [$event, $sessionToEdit]), [
            'name' => $sessionToEdit->name,
            'starts_at' => '2026-11-10T11:00',
            'ends_at' => '2026-11-10T13:00',
        ])
        ->assertRedirect(route('events.event-sessions.edit', [$event, $sessionToEdit]));

    $response->assertSessionHasErrors('starts_at');

    expect($sessionToEdit->fresh()->starts_at->toDateTimeString())->toBe('2026-11-10 13:00:00');
});

it('checks generated sessions when creating an event', function () {
    $location = makeConflictLocation('Event create room');
    $organizer = User::factory()->create(['role' => 'organizer']);
    $existingEvent = makeConflictEvent($organizer, $location, 'Existing event');
    makeConflictSession($existingEvent, 'Existing session', '2026-11-10 10:00:00', '2026-11-10 12:00:00');

    $this->actingAs($organizer)
        ->from(route('events.create'))
        ->post(route('events.store'), [
            'name' => 'Conflicting generated event',
            'starts_at' => '2026-11-10T11:00',
            'ends_at' => '2026-11-10T13:00',
            'location_id' => $location->id,
        ])
        ->assertRedirect(route('events.create'))
        ->assertSessionHasErrors('starts_at');

    expect(Event::where('name', 'Conflicting generated event')->exists())->toBeFalse();
});

it('checks shifted sessions when an event schedule changes', function () {
    $location = makeConflictLocation('Reschedule room');
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = makeConflictEvent($organizer, $location, 'Rescheduled event', '2026-11-10 09:00:00', '2026-11-10 12:00:00');
    makeConflictSession($event, 'Session 1', '2026-11-10 09:00:00', '2026-11-10 12:00:00');
    $otherEvent = makeConflictEvent($organizer, $location, 'Other event');
    makeConflictSession($otherEvent, 'Existing session', '2026-11-10 11:00:00', '2026-11-10 12:00:00');

    $this->actingAs($organizer)
        ->from(route('events.edit', $event))
        ->put(route('events.update', $event), [
            'name' => $event->name,
            'starts_at' => '2026-11-10T10:00',
            'ends_at' => '2026-11-10T13:00',
            'location_id' => $location->id,
            'status' => $event->status,
            'sync_sessions' => '1',
            'expected_sessions' => '1',
        ])
        ->assertRedirect(route('events.edit', $event))
        ->assertSessionHasErrors('starts_at');

    expect($event->fresh()->starts_at->toDateTimeString())->toBe('2026-11-10 09:00:00');
});

it('checks sessions when an event is assigned to a conflicting location', function () {
    $currentLocation = makeConflictLocation('Current room');
    $newLocation = makeConflictLocation('New room');
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = makeConflictEvent($organizer, $currentLocation, 'Moved event');
    makeConflictSession($event, 'Session 1', '2026-11-10 09:00:00', '2026-11-10 11:00:00');
    $otherEvent = makeConflictEvent($organizer, $newLocation, 'Existing event');
    makeConflictSession($otherEvent, 'Session 1', '2026-11-10 10:00:00', '2026-11-10 12:00:00');

    $this->actingAs($organizer)
        ->from(route('events.edit', $event))
        ->put(route('events.update', $event), [
            'name' => $event->name,
            'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $event->ends_at->format('Y-m-d\TH:i'),
            'location_id' => $newLocation->id,
            'status' => $event->status,
        ])
        ->assertRedirect(route('events.edit', $event))
        ->assertSessionHasErrors('starts_at');

    expect($event->fresh()->location_id)->toBe($currentLocation->id);
});

it('allows overlapping sessions when the event has no assigned location', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = makeConflictEvent($organizer, null, 'Unassigned location event');
    makeConflictSession($event, 'Existing session', '2026-11-10 10:00:00', '2026-11-10 12:00:00');

    $this->actingAs($organizer)
        ->post(route('events.event-sessions.store', $event), [
            'name' => 'Overlapping session',
            'starts_at' => '2026-11-10T11:00',
            'ends_at' => '2026-11-10T13:00',
        ])
        ->assertRedirect(route('events.show', $event));

    expect($event->sessions()->count())->toBe(2);
});

it('rejects invalid event and session intervals with field errors', function () {
    $location = makeConflictLocation('Validation room');
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = makeConflictEvent($organizer, $location, 'Validation event');

    $this->actingAs($organizer)
        ->from(route('events.event-sessions.create', $event))
        ->post(route('events.event-sessions.store', $event), [
            'name' => 'Invalid session',
            'starts_at' => '2026-11-10T12:00',
            'ends_at' => '2026-11-10T12:00',
        ])
        ->assertRedirect(route('events.event-sessions.create', $event))
        ->assertSessionHasErrors('ends_at');

    $this->from(route('events.create'))
        ->post(route('events.store'), [
            'name' => 'Invalid event',
            'starts_at' => '2026-11-10T13:00',
            'ends_at' => '2026-11-10T12:00',
            'location_id' => $location->id,
        ])
        ->assertRedirect(route('events.create'))
        ->assertSessionHasErrors('ends_at');
});
