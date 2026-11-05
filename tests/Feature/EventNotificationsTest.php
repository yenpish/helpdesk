<?php

use App\Models\Event;
use App\Models\AuditLog;
use App\Models\EventNotificationDelivery;
use App\Models\Registration;
use App\Models\User;
use App\Services\EventNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function notificationEvent(User $organizer, string $status = 'draft'): Event
{
    return Event::create([
        'name' => 'Notification Event',
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(3),
        'organizer_id' => $organizer->id,
        'created_by' => $organizer->id,
        'updated_by' => $organizer->id,
        'status' => $status,
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);
}

function notificationRegistration(Event $event, string $email, string $status = 'approved'): Registration
{
    return Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Test Participant',
        'guest_email' => $email,
        'guest_phone' => '60123456789',
        'status' => $status,
        'registered_at' => now(),
    ]);
}

it('notifies active registrations when an event is published but excludes rejected requests', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = notificationEvent($organizer);
    notificationRegistration($event, 'approved@example.test');
    notificationRegistration($event, 'pending@example.test', 'pending');
    notificationRegistration($event, 'rejected@example.test', 'rejected');

    $this->actingAs($organizer)->post(route('events.publish', $event))->assertRedirect();

    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 2);
    expect(EventNotificationDelivery::where('event_id', $event->id)->where('status', 'accepted')->count())->toBe(2);
});

it('shows notification outcomes and reminder settings without exposing recipient addresses', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = notificationEvent($organizer, 'published');
    notificationRegistration($event, 'private-participant@example.test');
    app(EventNotificationService::class)->sendForEvent($event, 'published');

    $this->actingAs($organizer)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('Sent')
        ->assertDontSee('private-participant@example.test')
        ->assertDontSee('Retry failed')
        ->assertDontSee('@if($batch', false);

    $other = notificationRegistration($event, 'another-private@example.test');
    $accepted = EventNotificationDelivery::where('event_id', $event->id)->firstOrFail();
    EventNotificationDelivery::create([
        'event_id' => $event->id,
        'registration_id' => $other->id,
        'type' => 'published',
        'notification_key' => $accepted->notification_key,
        'recipient_email' => 'another-private@example.test',
        'status' => 'failed',
        'attempt_count' => 1,
        'last_error' => 'Mail transport failed.',
    ]);
    $pending = notificationRegistration($event, 'pending-private@example.test');
    EventNotificationDelivery::create([
        'event_id' => $event->id,
        'registration_id' => $pending->id,
        'type' => 'published',
        'notification_key' => $accepted->notification_key,
        'recipient_email' => 'pending-private@example.test',
        'status' => 'pending',
        'attempt_count' => 1,
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('Sent')
        ->assertSee('Failed')
        ->assertSee('Pending')
        ->assertSee('Retry failed')
        ->assertDontSee('another-private@example.test');

    $this->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee('24 hours before')
        ->assertSee('1 hour before');
});

it('sends an update notice only when published event details meaningfully change', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = notificationEvent($organizer, 'published');
    notificationRegistration($event, 'participant@example.test');
    $data = [
        'name' => $event->name,
        'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
        'ends_at' => $event->ends_at->format('Y-m-d\TH:i'),
        'status' => 'published',
        'expected_sessions' => 0,
        'sync_sessions' => 0,
    ];

    $this->actingAs($organizer)->put(route('events.update', $event), $data)->assertRedirect();
    Mail::assertNothingSent();

    $data['name'] = 'Changed event title';
    $this->put(route('events.update', $event), $data)->assertRedirect();
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 1);
    expect(EventNotificationDelivery::where('event_id', $event->id)->where('type', 'updated')->count())->toBe(1);
});

it('notifies approved participants when a published event is rescheduled', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = notificationEvent($organizer, 'published');
    notificationRegistration($event, 'participant@example.test');
    $this->actingAs($organizer)->put(route('events.update', $event), [
        'name' => $event->name,
        'starts_at' => now()->addDays(6)->format('Y-m-d\\TH:i'),
        'ends_at' => now()->addDays(6)->addHours(3)->format('Y-m-d\\TH:i'),
        'status' => 'published',
        'expected_sessions' => 0,
        'sync_sessions' => 1,
    ])->assertRedirect();

    expect($event->fresh()->starts_at->isSameDay(now()->addDays(6)))->toBeTrue()
        ->and(EventNotificationDelivery::where('event_id', $event->id)->where('type', 'updated')->count())->toBe(1);
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 1);
});

it('sends cancellation notices and does not send reminders for a cancelled event', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = notificationEvent($organizer, 'published');
    $event->reminder_24h = true;
    $event->save();
    notificationRegistration($event, 'participant@example.test');
    $this->actingAs($organizer)->put(route('events.update', $event), [
        'name' => $event->name,
        'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
        'ends_at' => $event->ends_at->format('Y-m-d\TH:i'),
        'status' => 'cancelled',
        'expected_sessions' => 0,
    ])->assertRedirect();

    expect(EventNotificationDelivery::where('event_id', $event->id)->where('type', 'cancelled')->count())->toBe(1);
    app(EventNotificationService::class)->sendDueReminders();
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 1);
});

it('sends configured reminders once to approved registrants and never after an event starts', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = notificationEvent($organizer, 'published');
    $event->forceFill(['starts_at' => now()->addHours(24), 'reminder_24h' => true])->save();
    notificationRegistration($event, 'approved@example.test');
    notificationRegistration($event, 'pending@example.test', 'pending');

    app(EventNotificationService::class)->sendDueReminders();
    app(EventNotificationService::class)->sendDueReminders();
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 1);
    expect(EventNotificationDelivery::where('event_id', $event->id)->where('type', 'reminder_24h')->count())->toBe(1);

    $event->forceFill(['starts_at' => now()->subMinute()])->save();
    app(EventNotificationService::class)->sendDueReminders();
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 1);
});

it('sends 24-hour and 1-hour reminders only inside their configured timing windows', function () {
    Mail::fake();
    $this->travelTo(\Carbon\Carbon::parse('2026-11-03 09:00:00'));
    $organizer = User::factory()->create(['role' => 'organizer']);
    $service = app(EventNotificationService::class);

    $dayEvent = notificationEvent($organizer, 'published');
    $dayEvent->forceFill([
        'starts_at' => now()->addHours(24)->addMinute(),
        'reminder_24h' => true,
    ])->save();
    notificationRegistration($dayEvent, 'day@example.test');

    $service->sendDueReminders();
    Mail::assertNothingSent();
    $this->travel(1)->minutes();
    $service->sendDueReminders();
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 1);

    $hourEvent = notificationEvent($organizer, 'published');
    $hourEvent->forceFill([
        'starts_at' => now()->addHours(1)->addMinute(),
        'reminder_1h' => true,
    ])->save();
    notificationRegistration($hourEvent, 'hour@example.test');

    $service->sendDueReminders();
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 1);
    $this->travel(1)->minutes();
    $service->sendDueReminders();
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 2);
    expect(EventNotificationDelivery::where('type', 'reminder_1h')->count())->toBe(1);
});

it('uses the rescheduled start time for a new reminder attempt without duplicating the old one', function () {
    Mail::fake();
    $this->travelTo(\Carbon\Carbon::parse('2026-11-03 09:00:00'));
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = notificationEvent($organizer, 'published');
    $event->forceFill(['starts_at' => now()->addHours(24), 'reminder_24h' => true])->save();
    notificationRegistration($event, 'participant@example.test');
    $service = app(EventNotificationService::class);

    $service->sendDueReminders();
    $event->forceFill(['starts_at' => now()->addHours(48)])->save();
    $service->sendDueReminders();
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 1);

    $this->travel(24)->hours();
    $service->sendDueReminders();
    Mail::assertSent(\App\Mail\EventParticipantNotification::class, 2);
    expect(EventNotificationDelivery::where('event_id', $event->id)->where('type', 'reminder_24h')->count())->toBe(2)
        ->and(EventNotificationDelivery::where('event_id', $event->id)->distinct('notification_key')->count('notification_key'))->toBe(2);
});

it('records transport failures individually and retries only failed deliveries', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = notificationEvent($organizer, 'published');
    notificationRegistration($event, 'participant@example.test');
    notificationRegistration($event, 'second@example.test');
    $attempt = 0;
    Mail::shouldReceive('to')->twice()->andReturnUsing(function () use (&$attempt) {
        if (++$attempt === 1) {
            throw new RuntimeException('private transport detail');
        }

        return new class {
            public function send($mailable): void {}
        };
    });

    app(EventNotificationService::class)->sendForEvent($event, 'updated', 'test-notification-key');
    $failed = EventNotificationDelivery::where('status', 'failed')->sole();
    expect(EventNotificationDelivery::where('status', 'accepted')->count())->toBe(1)
        ->and($failed->last_error)->not->toContain('private transport detail');

    Mail::shouldReceive('to')->once()->andReturn(new class {
        public function send($mailable): void {}
    });
    expect(app(EventNotificationService::class)->retryFailed($event, 'test-notification-key'))->toBe(1)
        ->and(EventNotificationDelivery::where('status', 'accepted')->count())->toBe(2)
        ->and($failed->fresh()->attempt_count)->toBe(2);
    expect(app(EventNotificationService::class)->retryFailed($event, 'test-notification-key'))->toBe(0);
});

it('restricts notification retry to admins and the event owner', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException());
    $owner = User::factory()->create(['role' => 'organizer']);
    $event = notificationEvent($owner, 'published');
    notificationRegistration($event, 'participant@example.test');
    $key = '123e4567-e89b-12d3-a456-426614174000';
    app(EventNotificationService::class)->sendForEvent($event, 'updated', $key);

    $other = User::factory()->create(['role' => 'organizer']);
    $this->actingAs($other)
        ->post(route('events.notifications.retry', [$event, $key]))
        ->assertForbidden();

    Mail::shouldReceive('to')->once()->andReturn(new class {
        public function send($mailable): void {}
    });
    $this->actingAs($owner)
        ->post(route('events.notifications.retry', [$event, $key]))
        ->assertRedirect();
    expect(EventNotificationDelivery::sole()->status)->toBe('accepted')
        ->and(AuditLog::where('auditable_type', Event::class)->where('auditable_id', $event->id)->where('action', 'retried')->count())->toBe(1)
        ->and(AuditLog::where('auditable_type', Event::class)->where('auditable_id', $event->id)->where('action', 'retried')->value('user_id'))->toBe($owner->id);
});
