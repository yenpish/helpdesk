<?php

use App\Models\Event;
use App\Models\EventType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeConfigurationDependencyFixtures(): array
{
    $eventType = EventType::create(['name' => 'Deletion test type']);
    $location = Location::create([
        'name' => 'Deletion test location',
        'latitude' => 3.1390,
        'longitude' => 101.6869,
        'allowed_radius' => 100,
    ]);
    $event = Event::create([
        'name' => 'Configuration dependency event',
        'event_type_id' => $eventType->id,
        'location_id' => $location->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'status' => 'draft',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);

    return [$eventType, $location, $event];
}

it('blocks admins from deleting event types and locations referenced by events', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$eventType, $location, $event] = makeConfigurationDependencyFixtures();

    $this->actingAs($admin)
        ->delete(route('event-types.destroy', $eventType))
        ->assertRedirect(route('event-types.index'))
        ->assertSessionHas('error', 'This event type cannot be deleted while events use it.');

    $this->actingAs($admin)
        ->delete(route('locations.destroy', $location))
        ->assertRedirect(route('locations.index'))
        ->assertSessionHas('error', 'This location cannot be deleted while events use it.');

    expect($eventType->fresh())->not->toBeNull()
        ->and($location->fresh())->not->toBeNull()
        ->and($event->fresh()->event_type_id)->toBe($eventType->id)
        ->and($event->fresh()->location_id)->toBe($location->id);
});

it('allows admins to delete unreferenced event types and locations', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $eventType = EventType::create(['name' => 'Unreferenced event type']);
    $location = Location::create([
        'name' => 'Unreferenced location',
        'latitude' => 3.1390,
        'longitude' => 101.6869,
        'allowed_radius' => 100,
    ]);

    $this->actingAs($admin)
        ->delete(route('event-types.destroy', $eventType))
        ->assertRedirect(route('event-types.index'));
    $this->actingAs($admin)
        ->delete(route('locations.destroy', $location))
        ->assertRedirect(route('locations.index'));

    expect(EventType::find($eventType->id))->toBeNull()
        ->and(Location::find($location->id))->toBeNull();
});

it('prevents non-admins from deleting event types or locations', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$eventType, $location] = makeConfigurationDependencyFixtures();

    $this->actingAs($organizer)
        ->delete(route('event-types.destroy', $eventType))
        ->assertForbidden();
    $this->actingAs($organizer)
        ->delete(route('locations.destroy', $location))
        ->assertForbidden();

    expect($eventType->fresh())->not->toBeNull()
        ->and($location->fresh())->not->toBeNull();
});

it('retains restrictive database foreign keys if the controller guard is bypassed', function () {
    [$eventType, $location] = makeConfigurationDependencyFixtures();

    expect(fn () => $eventType->delete())->toThrow(QueryException::class);
    expect(fn () => $location->delete())->toThrow(QueryException::class);

    expect($eventType->fresh())->not->toBeNull()
        ->and($location->fresh())->not->toBeNull();
});
