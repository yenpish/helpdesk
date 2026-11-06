<?php

use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventType;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\EncryptedRouteIdentifier;

uses(RefreshDatabase::class);

function encryptedRouteEvent(User $owner, string $name): Event
{
    return Event::create([
        'name' => $name,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
        'organizer_id' => $owner->id,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
        'status' => 'draft',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);
}

function encryptedRouteSession(Event $event, string $name): EventSession
{
    return $event->sessions()->create([
        'name' => $name,
        'starts_at' => $event->starts_at,
        'ends_at' => $event->ends_at,
        'attendance_opens_at' => $event->starts_at,
        'attendance_closes_at' => $event->ends_at,
        'pin' => null,
    ]);
}

it('generates URL-safe encrypted record keys and resolves authorized event routes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = encryptedRouteEvent($admin, 'Encrypted route event');
    $url = route('events.show', $event);
    $identifier = basename(parse_url($url, PHP_URL_PATH));

    expect($identifier)->not->toBe((string) $event->id)
        ->and($identifier)->toMatch('/\A[A-Za-z0-9_-]+\z/')
        ->and(EncryptedRouteIdentifier::decode($identifier, Event::class))->toBe((string) $event->id);

    $this->actingAs($admin)->get($url)->assertOk()->assertSee($event->name);
});

it('rejects numeric event identifiers and tampered or truncated encrypted identifiers', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = encryptedRouteEvent($admin, 'Numeric identifier must fail');
    $valid = $event->getRouteKey();
    $tampered = ($valid[0] === 'A' ? 'B' : 'A').substr($valid, 1);
    $truncated = substr($valid, 0, -6);

    $this->actingAs($admin)->get('/events/'.$event->id.'/edit')->assertNotFound();
    $this->actingAs($admin)->get('/events/'.($event->id + 1).'/edit')->assertNotFound();
    $this->actingAs($admin)->get('/events/'.$tampered.'/edit')->assertNotFound();
    $this->actingAs($admin)->get('/events/'.$truncated.'/edit')->assertNotFound();
});

it('rejects plaintext identifiers on archived restore and permanent-delete routes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = encryptedRouteEvent($admin, 'Archived plaintext route target');
    $session = encryptedRouteSession($event, 'Archived plaintext session');
    $event->delete();
    $session->delete();

    $this->actingAs($admin)->post('/events/deleted/'.$event->id.'/restore')->assertNotFound();
    $this->actingAs($admin)->delete('/events/deleted/'.$event->id.'/permanent')->assertNotFound();
    $this->actingAs($admin)->post('/events/deleted/sessions/'.$session->id.'/restore')->assertNotFound();
    $this->actingAs($admin)->delete('/events/deleted/sessions/'.$session->id.'/permanent')->assertNotFound();
});

it('does not let a valid event token bypass ownership or parent-child checks', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $event = encryptedRouteEvent($owner, 'Protected owner event');
    $otherEvent = encryptedRouteEvent($otherOrganizer, 'Different parent event');
    $session = encryptedRouteSession($event, 'Child session');

    $this->actingAs($otherOrganizer)
        ->get(route('events.show', $event))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('events.event-sessions.show', [$otherEvent, $session]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk();
});

it('rejects a correctly encrypted identifier used for the wrong record type', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = encryptedRouteEvent($admin, 'Wrong token type');
    $eventTypeToken = EncryptedRouteIdentifier::encode(\App\Models\EventType::class, $event->id);

    $this->actingAs($admin)->get('/events/'.$eventTypeToken.'/edit')->assertNotFound();
});

it('uses encrypted identifiers for account, role, event type, and location pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $account = User::factory()->create(['role' => 'organizer']);
    $role = Role::firstOrCreate(['slug' => 'route-reviewer'], [
        'name' => 'Route Reviewer',
        'permissions' => ['events.manage'],
        'is_system' => false,
    ]);
    $eventType = EventType::create(['name' => 'Encrypted Route Type']);
    $location = Location::create([
        'name' => 'Encrypted Route Location',
        'latitude' => 3.139,
        'longitude' => 101.6869,
        'allowed_radius' => 100,
    ]);

    foreach ([
        [route('accounts.edit', $account), $account->email],
        [route('roles.edit', $role), 'Route Reviewer'],
        [route('event-types.show', $eventType), 'Encrypted Route Type'],
        [route('locations.show', $location), 'Encrypted Route Location'],
    ] as [$url, $expectedText]) {
        $this->actingAs($admin)->get($url)->assertOk()->assertSee($expectedText);
    }

    foreach ([
        '/accounts/'.$account->id.'/edit',
        '/roles/'.$role->id.'/edit',
        '/event-types/'.$eventType->id,
        '/locations/'.$location->id,
    ] as $numericUrl) {
        $this->actingAs($admin)->get($numericUrl)->assertNotFound();
    }
});

it('resolves an encrypted event after a normal create or edit redirect', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $event = encryptedRouteEvent($owner, 'Redirected event');

    $response = $this->actingAs($owner)
        ->post(route('events.publish', $event))
        ->assertRedirect();

    $this->get($response->headers->get('Location'))->assertOk()->assertSee($event->name);
});
