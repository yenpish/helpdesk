<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRestorationSafetyFixture(User $owner, Location $location, string $name): array
{
    $event = Event::create([
        'name' => $name,
        'organizer_id' => $owner->id,
        'location_id' => $location->id,
        'starts_at' => '2026-11-10 09:00:00',
        'ends_at' => '2026-11-10 17:00:00',
        'status' => 'draft',
    ]);

    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Session 1',
        'starts_at' => '2026-11-10 10:00:00',
        'ends_at' => '2026-11-10 12:00:00',
        'attendance_opens_at' => '2026-11-10 10:00:00',
        'attendance_closes_at' => '2026-11-10 12:00:00',
    ]);

    return [$event, $session];
}

function makeRestorationLocation(string $name): Location
{
    return Location::create([
        'name' => $name,
        'latitude' => 3.1390000,
        'longitude' => 101.6869000,
        'allowed_radius' => 150,
    ]);
}

it('keeps an archived session and its check-ins when a replacement occupies its schedule', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $location = makeRestorationLocation('Restore room');
    [$event, $archived] = makeRestorationSafetyFixture($owner, $location, 'Replacement event');
    $attendance = Attendance::create([
        'full_name' => 'Historical attendee',
        'email' => 'historical-session@example.test',
        'session_id' => $archived->id,
    ]);

    $this->actingAs($owner)
        ->delete(route('events.event-sessions.destroy', [$event, $archived]))
        ->assertRedirect();

    $this->post(route('events.event-sessions.store', $event), [
        'name' => 'Session 1',
        'starts_at' => '2026-11-10T10:00',
        'ends_at' => '2026-11-10T12:00',
    ])->assertRedirect();
    $replacement = $event->sessions()->firstOrFail();

    $this->from(route('events.deleted'))
        ->post(route('events.deleted.sessions.restore', $archived))
        ->assertRedirect(route('events.deleted'))
        ->assertSessionHasErrors('restore_session');

    expect($archived->fresh()->trashed())->toBeTrue()
        ->and($replacement->fresh()->trashed())->toBeFalse()
        ->and($attendance->fresh()->session_id)->toBe($archived->id)
        ->and($event->sessions()->count())->toBe(1);
});

it('blocks restoring a session that conflicts with another event at the same location', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $location = makeRestorationLocation('Cross event restore room');
    [$event, $archived] = makeRestorationSafetyFixture($owner, $location, 'Archived source event');
    $this->actingAs($owner)->delete(route('events.event-sessions.destroy', [$event, $archived]));

    [$otherEvent] = makeRestorationSafetyFixture($owner, $location, 'Active event');
    $otherEvent->sessions()->create([
        'name' => 'Active session',
        'starts_at' => '2026-11-10 11:00:00',
        'ends_at' => '2026-11-10 13:00:00',
        'attendance_opens_at' => '2026-11-10 11:00:00',
        'attendance_closes_at' => '2026-11-10 13:00:00',
    ]);

    $this->actingAs($owner)
        ->from(route('events.deleted'))
        ->post(route('events.deleted.sessions.restore', $archived))
        ->assertRedirect(route('events.deleted'))
        ->assertSessionHasErrors('restore_session');

    expect($archived->fresh()->trashed())->toBeTrue();
});

it('keeps a session archived when its schedule no longer fits the parent event', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    [$event, $archived] = makeRestorationSafetyFixture($owner, makeRestorationLocation('Changed event room'), 'Changed parent schedule');
    $this->actingAs($owner)->delete(route('events.event-sessions.destroy', [$event, $archived]));
    $event->update(['ends_at' => '2026-11-10 11:00:00']);

    $this->actingAs($owner)
        ->from(route('events.deleted'))
        ->post(route('events.deleted.sessions.restore', $archived))
        ->assertRedirect(route('events.deleted'))
        ->assertSessionHasErrors('restore_session');

    expect($archived->fresh()->trashed())->toBeTrue();
});

it('blocks restoring an archived event when its sessions now conflict at the same location', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $location = makeRestorationLocation('Event restore room');
    [$archivedEvent] = makeRestorationSafetyFixture($owner, $location, 'Archived event with session');
    $this->actingAs($owner)->delete(route('events.destroy', $archivedEvent));

    [$activeEvent] = makeRestorationSafetyFixture($owner, $location, 'Replacement event');
    $activeEvent->sessions()->create([
        'name' => 'Replacement session',
        'starts_at' => '2026-11-10 11:00:00',
        'ends_at' => '2026-11-10 13:00:00',
        'attendance_opens_at' => '2026-11-10 11:00:00',
        'attendance_closes_at' => '2026-11-10 13:00:00',
    ]);

    $this->actingAs($owner)
        ->from(route('events.deleted'))
        ->post(route('events.deleted.restore', $archivedEvent))
        ->assertRedirect(route('events.deleted'))
        ->assertSessionHasErrors('restore_event');

    expect($archivedEvent->fresh()->trashed())->toBeTrue();
});

it('denies a different organizer direct access to restore an archived session', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $other = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeRestorationSafetyFixture($owner, makeRestorationLocation('Private restore room'), 'Private restore event');
    $this->actingAs($owner)->delete(route('events.event-sessions.destroy', [$event, $session]));

    $this->actingAs($other)
        ->post(route('events.deleted.sessions.restore', $session))
        ->assertForbidden();

    expect($session->fresh()->trashed())->toBeTrue();
});

it('orders sessions by schedule with an id tie-breaker on event and manage pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'name' => 'Chronological event',
        'organizer_id' => $admin->id,
        'starts_at' => '2026-11-10 09:00:00',
        'ends_at' => '2026-11-10 17:00:00',
        'status' => 'draft',
    ]);
    $later = $event->sessions()->create([
        'name' => 'Later session',
        'starts_at' => '2026-11-10 13:00:00',
        'ends_at' => '2026-11-10 14:00:00',
    ]);
    $earlier = $event->sessions()->create([
        'name' => 'Earlier session',
        'starts_at' => '2026-11-10 10:00:00',
        'ends_at' => '2026-11-10 11:00:00',
    ]);
    $sameTime = $event->sessions()->create([
        'name' => 'Same-time session',
        'starts_at' => '2026-11-10 10:00:00',
        'ends_at' => '2026-11-10 11:00:00',
    ]);

    expect($event->fresh()->sessions->pluck('id')->all())
        ->toBe([$earlier->id, $sameTime->id, $later->id]);

    $this->actingAs($admin)->get(route('events.show', $event))->assertOk()
        ->assertSeeInOrder(['Earlier session', 'Same-time session', 'Later session']);
    $this->get(route('events.event-sessions.index', $event))->assertOk()
        ->assertSeeInOrder(['Earlier session', 'Same-time session', 'Later session']);

    $event->update(['status' => 'published', 'pin' => 'AB23']);
    $pinResponse = $this->post(route('attendance.verify'), ['pin' => 'AB23']);
    $attendanceForm = $this->get($pinResponse->headers->get('Location'));
    $attendanceForm->assertOk()
        ->assertSeeInOrder(['Earlier session', 'Same-time session', 'Later session']);
});
