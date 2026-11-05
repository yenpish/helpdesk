<?php

use App\Models\Event;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publishingEvent(User $organizer, string $status = 'draft'): Event
{
    return Event::create([
        'name' => 'Publishing authorization event',
        'starts_at' => '2030-10-01 09:00:00',
        'ends_at' => '2030-10-01 17:00:00',
        'organizer_id' => $organizer->id,
        'created_by' => $organizer->id,
        'updated_by' => $organizer->id,
        'status' => $status,
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);
}

it('allows an organizer to publish their own draft event', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = publishingEvent($organizer);

    $this->actingAs($organizer)
        ->post(route('events.publish', $event))
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('success', 'Event published successfully.');

    expect($event->fresh()->status)->toBe('published');
    expect(AuditLog::where('auditable_type', Event::class)->where('auditable_id', $event->id)->latest('id')->value('action'))
        ->toBe('published');
});

it('forbids an organizer from publishing another organizer event', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    $event = publishingEvent($owner);

    $this->actingAs($otherOrganizer)
        ->post(route('events.publish', $event))
        ->assertForbidden();

    expect($event->fresh()->status)->toBe('draft');
});

it('allows an admin to publish another organizer event', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $event = publishingEvent($owner);

    $this->actingAs($admin)
        ->post(route('events.publish', $event))
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('success', 'Event published successfully.');

    expect($event->fresh()->status)->toBe('published');
    expect(AuditLog::where('auditable_type', Event::class)->where('auditable_id', $event->id)->latest('id')->value('user_id'))
        ->toBe($admin->id);
});

it('keeps publishing an already-published event idempotent', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = publishingEvent($organizer, 'published');

    $this->actingAs($organizer)
        ->post(route('events.publish', $event))
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('success', 'This event is already published.');

    expect($event->fresh()->status)->toBe('published');
});

it('prevents cancelled and completed events from being published through either endpoint', function (string $status) {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = publishingEvent($organizer, $status);

    $this->actingAs($organizer)
        ->post(route('events.publish', $event))
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHasErrors('event');

    $this->put(route('events.update', $event), [
        'name' => $event->name,
        'starts_at' => $event->starts_at->toDateTimeString(),
        'ends_at' => $event->ends_at->toDateTimeString(),
        'status' => 'published',
    ])->assertRedirect()
        ->assertSessionHasErrors('status');

    $this->put(route('events.update', $event), [
        'name' => $event->name,
        'starts_at' => $event->starts_at->toDateTimeString(),
        'ends_at' => $event->ends_at->toDateTimeString(),
        'status' => 'draft',
    ])->assertRedirect()
        ->assertSessionHasErrors('status');

    expect($event->fresh()->status)->toBe($status);
})->with(['cancelled', 'completed']);

it('does not show publishing actions for published or terminal events', function (string $status) {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = publishingEvent($organizer, $status);

    $this->actingAs($organizer)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertDontSee(route('events.publish', $event));

    if (in_array($status, ['cancelled', 'completed'], true)) {
        $this->get(route('events.edit', $event))
            ->assertOk()
            ->assertDontSee('<option value="published"', false)
            ->assertDontSee('<option value="draft"', false);
    } else {
        $this->get(route('events.edit', $event))
            ->assertOk()
            ->assertSee('<option value="published"', false);
    }
})->with(['published', 'cancelled', 'completed']);
