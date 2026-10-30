<?php

use App\Models\Event;
use App\Models\EventSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSessionDefaultsEvent(): array
{
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = Event::create([
        'name' => 'Session defaults event',
        'organizer_id' => $organizer->id,
        'starts_at' => '2026-11-05 09:30:00',
        'ends_at' => '2026-11-07 16:45:00',
        'status' => 'draft',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);

    return [$organizer, $event];
}

it('prefills new session start and end from the parent event schedule', function () {
    [$organizer, $event] = makeSessionDefaultsEvent();

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.create', $event))
        ->assertOk()
        ->assertSee('name="starts_at"', false)
        ->assertSee('value="2026-11-05T09:30"', false)
        ->assertSee('name="ends_at"', false)
        ->assertSee('value="2026-11-07T16:45"', false);
});

it('allows organizers to override the prefilled session schedule', function () {
    [$organizer, $event] = makeSessionDefaultsEvent();

    $this->actingAs($organizer)
        ->post(route('events.event-sessions.store', $event), [
            'name' => 'Custom schedule',
            'starts_at' => '2026-11-06T10:15',
            'ends_at' => '2026-11-06T12:45',
        ])
        ->assertRedirect(route('events.show', $event));

    $session = $event->sessions()->sole();
    expect($session->starts_at->toDateTimeString())->toBe('2026-11-06 10:15:00')
        ->and($session->ends_at->toDateTimeString())->toBe('2026-11-06 12:45:00')
        ->and($session->attendance_opens_at->toDateTimeString())->toBe('2026-11-06 10:15:00')
        ->and($session->attendance_closes_at->toDateTimeString())->toBe('2026-11-06 23:59:00');
});

it('keeps an existing session schedule when editing it', function () {
    [$organizer, $event] = makeSessionDefaultsEvent();
    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Existing schedule',
        'starts_at' => '2026-11-06 11:00:00',
        'ends_at' => '2026-11-06 14:30:00',
        'attendance_opens_at' => '2026-11-06 11:00:00',
        'attendance_closes_at' => '2026-11-06 23:59:00',
    ]);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.edit', [$event, $session]))
        ->assertOk()
        ->assertSee('value="2026-11-06T11:00"', false)
        ->assertSee('value="2026-11-06T14:30"', false);

    $this->put(route('events.event-sessions.update', [$event, $session]), [
        'name' => 'Existing schedule',
        'starts_at' => '2026-11-06T11:00',
        'ends_at' => '2026-11-06T14:30',
    ])->assertRedirect(route('events.event-sessions.show', [$event, $session]));

    expect($session->fresh()->starts_at->toDateTimeString())->toBe('2026-11-06 11:00:00')
        ->and($session->fresh()->ends_at->toDateTimeString())->toBe('2026-11-06 14:30:00');
});

it('rejects a session schedule that starts before or ends after the parent event', function (array $schedule) {
    [$organizer, $event] = makeSessionDefaultsEvent();

    $this->actingAs($organizer)
        ->from(route('events.event-sessions.create', $event))
        ->post(route('events.event-sessions.store', $event), [
            'name' => 'Out of bounds session',
            ...$schedule,
        ])
        ->assertRedirect(route('events.event-sessions.create', $event))
        ->assertSessionHasErrors('schedule');

    expect($event->sessions()->count())->toBe(0);
})->with([
    'starts before the event' => [[
        'starts_at' => '2026-11-05T09:29',
        'ends_at' => '2026-11-05T10:00',
    ]],
    'ends after the event' => [[
        'starts_at' => '2026-11-07T16:00',
        'ends_at' => '2026-11-07T16:46',
    ]],
]);

it('rejects moving an existing session outside its parent event schedule', function () {
    [$organizer, $event] = makeSessionDefaultsEvent();
    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Existing in-range session',
        'starts_at' => '2026-11-06 10:00:00',
        'ends_at' => '2026-11-06 12:00:00',
        'attendance_opens_at' => '2026-11-06 10:00:00',
        'attendance_closes_at' => '2026-11-06 23:59:00',
    ]);

    $this->actingAs($organizer)
        ->from(route('events.event-sessions.edit', [$event, $session]))
        ->put(route('events.event-sessions.update', [$event, $session]), [
            'name' => 'Moved outside event',
            'starts_at' => '2026-11-07T16:30',
            'ends_at' => '2026-11-07T17:00',
        ])
        ->assertRedirect(route('events.event-sessions.edit', [$event, $session]))
        ->assertSessionHasErrors('schedule');

    expect($session->fresh()->name)->toBe('Existing in-range session')
        ->and($session->fresh()->starts_at->toDateTimeString())->toBe('2026-11-06 10:00:00')
        ->and($session->fresh()->ends_at->toDateTimeString())->toBe('2026-11-06 12:00:00');
});

it('allows overlapping sessions when both remain within the event schedule', function () {
    [$organizer, $event] = makeSessionDefaultsEvent();
    EventSession::create([
        'event_id' => $event->id,
        'name' => 'First session',
        'starts_at' => '2026-11-06 10:00:00',
        'ends_at' => '2026-11-06 12:00:00',
        'attendance_opens_at' => '2026-11-06 10:00:00',
        'attendance_closes_at' => '2026-11-06 23:59:00',
    ]);

    $this->actingAs($organizer)
        ->post(route('events.event-sessions.store', $event), [
            'name' => 'Overlapping session',
            'starts_at' => '2026-11-06T11:00',
            'ends_at' => '2026-11-06T13:00',
        ])
        ->assertRedirect(route('events.show', $event));

    expect($event->sessions()->count())->toBe(2);
});
