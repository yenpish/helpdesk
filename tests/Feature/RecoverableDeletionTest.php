<?php

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventNotificationDelivery;
use App\Models\EventSession;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\Registration;
use App\Models\User;
use App\Services\EventNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function makeRecoverableEvent(User $owner, string $name = 'Recoverable event'): array
{
    $event = Event::create([
        'name' => $name,
        'organizer_id' => $owner->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(3),
        'status' => 'published',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);

    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Recoverable session',
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(2),
        'attendance_opens_at' => now()->subMinutes(5),
        'attendance_closes_at' => now()->addHour(),
    ]);

    return [$event, $session];
}

it('archives populated events and restores their data and normal visibility', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$event, $session] = makeRecoverableEvent($admin);
    $attendance = Attendance::create([
        'full_name' => 'Preserved attendee',
        'email' => 'preserved@example.test',
        'session_id' => $session->id,
        'signature' => 'data:image/png;base64,c2lnbmF0dXJl',
    ]);
    $registration = Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Registered participant',
        'guest_email' => 'registered@example.test',
        'guest_phone' => '60123456789',
        'status' => 'approved',
    ]);
    $feedback = Feedback::create([
        'event_id' => $event->id,
        'participant_name' => 'Registered participant',
        'participant_email' => 'registered@example.test',
        'rating' => 5,
    ]);
    $invitation = FeedbackInvitation::create([
        'event_id' => $event->id,
        'attendance_id' => $attendance->id,
        'participant_name' => $attendance->full_name,
        'participant_email' => $attendance->email,
        'token_hash' => hash('sha256', 'preserved-token'),
        'expires_at' => now()->addDays(7),
    ]);
    $delivery = EventNotificationDelivery::create([
        'event_id' => $event->id,
        'registration_id' => $registration->id,
        'type' => 'published',
        'notification_key' => 'preserved-notice',
        'recipient_email' => $registration->guest_email,
        'status' => 'accepted',
    ]);

    $this->actingAs($admin)
        ->delete(route('events.destroy', $event))
        ->assertRedirect(route('events.index'))
        ->assertSessionHas('success');

    expect(Event::find($event->id))->toBeNull()
        ->and(Event::withTrashed()->find($event->id)->trashed())->toBeTrue()
        ->and($attendance->fresh()->session_id)->toBe($session->id)
        ->and($attendance->fresh()->session->is($session))->toBeTrue()
        ->and($registration->fresh())->not->toBeNull()
        ->and($feedback->fresh())->not->toBeNull()
        ->and($invitation->fresh())->not->toBeNull()
        ->and($delivery->fresh())->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('events.index'))
        ->assertDontSee($event->name);
    $this->actingAs($admin)
        ->get(route('events.show', $event))
        ->assertNotFound();
    $this->get(route('registrations.public-index'))->assertDontSee($event->name);

    $this->actingAs($admin)
        ->get(route('events.deleted'))
        ->assertOk()
        ->assertSee($event->name)
        ->assertSee('1 check-ins')
        ->assertSee('1 pre-registrations');
    $this->actingAs($admin)
        ->delete(route('events.deleted.permanent', $event))
        ->assertRedirect()
        ->assertSessionHasErrors('permanent_delete');

    $this->actingAs($admin)
        ->post(route('events.deleted.restore', $event))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Event::find($event->id))->not->toBeNull()
        ->and($event->fresh()->sessions()->find($session->id))->not->toBeNull()
        ->and($attendance->fresh()->session->id)->toBe($session->id);

    $this->get(route('registrations.public-index'))->assertSee($event->name);
});

it('limits deleted items to their event owner and denies direct cross-owner restoration', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    [$event] = makeRecoverableEvent($owner, 'Owner-only deleted event');

    $this->actingAs($owner)->delete(route('events.destroy', $event))->assertRedirect();

    $this->actingAs($otherOrganizer)
        ->get(route('events.deleted'))
        ->assertOk()
        ->assertDontSee($event->name);
    $this->actingAs($otherOrganizer)
        ->post(route('events.deleted.restore', $event))
        ->assertForbidden();
    expect(Event::withTrashed()->find($event->id)->trashed())->toBeTrue();
});

it('archives sessions with check-ins, removes them from active exports and restores the attendee table', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$event, $session] = makeRecoverableEvent($admin);
    $attendance = Attendance::create([
        'full_name' => 'Session history attendee',
        'email' => 'session-history@example.test',
        'session_id' => $session->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('events.event-sessions.destroy', [$event, $session]))
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('success');

    expect(EventSession::find($session->id))->toBeNull()
        ->and(EventSession::withTrashed()->find($session->id)->trashed())->toBeTrue()
        ->and($attendance->fresh()->session->id)->toBe($session->id);

    $this->actingAs($admin)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertNotFound();
    $this->actingAs($admin)->get(route('home'))->assertDontSee('Recoverable session');
    $this->get(route('registrations.public-index'))->assertDontSee($event->name);

    $csv = $this->actingAs($admin)->get(route('events.attendance.export', $event))->streamedContent();
    expect($csv)->not->toContain('Session history attendee')
        ->and($attendance->fresh()->session->name)->toBe('Recoverable session');

    $this->actingAs($admin)
        ->get(route('events.deleted'))
        ->assertOk()
        ->assertSee('Check-in history kept');

    $this->actingAs($admin)
        ->post(route('events.deleted.sessions.restore', $session))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('Session history attendee');
    $this->get(route('home'))->assertSee('Recoverable session');
    $this->get(route('registrations.public-index'))->assertSee($event->name);
});

it('requires an active session for PIN attendance and rejects an archived session QR link', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$event, $session] = makeRecoverableEvent($admin);
    $session->update([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);
    $payload = json_encode([
        'version' => 1,
        'event_id' => (int) $event->id,
        'session_id' => (int) $session->id,
        'expires_at' => now()->addHour()->timestamp,
    ], JSON_THROW_ON_ERROR);
    $encrypted = Crypt::encryptString($payload);
    $token = rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');

    $session->delete();

    $this->get(route('attendance.encrypted', $token))->assertNotFound();
    $this->from(route('attendance.pin'))
        ->post(route('attendance.verify'), ['pin' => $event->pin])
        ->assertRedirect(route('attendance.pin'))
        ->assertSessionHasErrors('pin');

    expect(app(EventNotificationService::class)->sendDueReminders())->toBe(0);
});

it('does not send a due reminder after its event is archived', function () {
    Mail::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    [$event] = makeRecoverableEvent($admin, 'Archived reminder event');
    $event->update([
        'starts_at' => now()->addHours(23)->addMinutes(30),
        'ends_at' => now()->addHours(24)->addMinutes(30),
        'reminder_24h' => true,
    ]);
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Approved participant',
        'guest_email' => 'reminder-participant@example.test',
        'guest_phone' => '60123456789',
        'status' => 'approved',
    ]);

    $this->actingAs($admin)->delete(route('events.destroy', $event))->assertRedirect();

    expect(app(EventNotificationService::class)->sendDueReminders())->toBe(0)
        ->and(EventNotificationDelivery::where('event_id', $event->id)->exists())->toBeFalse();
    Mail::assertNothingOutgoing();
});

it('allows permanent deletion only when business history is absent and retains audit entries', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$event, $session] = makeRecoverableEvent($admin, 'Empty archived event');

    $this->actingAs($admin)->delete(route('events.destroy', $event))->assertRedirect();
    $this->actingAs($admin)
        ->delete(route('events.deleted.permanent', $event))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Event::withTrashed()->find($event->id))->toBeNull()
        ->and(AuditLog::where('auditable_type', Event::class)->where('auditable_id', $event->id)->where('action', 'permanently_deleted')->exists())->toBeTrue();

    [$populatedEvent, $populatedSession] = makeRecoverableEvent($admin, 'Not permanently deletable');
    Attendance::create([
        'full_name' => 'Cannot discard',
        'email' => 'cannot-discard@example.test',
        'session_id' => $populatedSession->id,
    ]);
    $this->actingAs($admin)->delete(route('events.destroy', $populatedEvent))->assertRedirect();
    $this->actingAs($admin)
        ->delete(route('events.deleted.permanent', $populatedEvent))
        ->assertRedirect()
        ->assertSessionHasErrors('permanent_delete');
    expect(Event::withTrashed()->find($populatedEvent->id))->not->toBeNull();
});

it('protects the last session while registrations exist but allows safe session cleanup', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$event, $session] = makeRecoverableEvent($admin, 'Registered event');
    Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Approved participant',
        'guest_email' => 'session-delete@example.test',
        'guest_phone' => '60123456789',
        'status' => 'approved',
    ]);
    $this->actingAs($admin)->delete(route('events.event-sessions.destroy', [$event, $session]));

    $this->actingAs($admin)
        ->delete(route('events.deleted.sessions.permanent', $session))
        ->assertRedirect()
        ->assertSessionHasErrors('permanent_delete');
    expect(EventSession::withTrashed()->find($session->id))->not->toBeNull();

    [$emptyEvent, $emptySession] = makeRecoverableEvent($admin, 'Empty session event');
    $this->actingAs($admin)->delete(route('events.event-sessions.destroy', [$emptyEvent, $emptySession]));
    $this->actingAs($admin)
        ->delete(route('events.deleted.sessions.permanent', $emptySession))
        ->assertRedirect()
        ->assertSessionHas('success');
    expect(EventSession::withTrashed()->find($emptySession->id))->toBeNull()
        ->and(AuditLog::where('auditable_type', EventSession::class)->where('auditable_id', $emptySession->id)->where('action', 'permanently_deleted')->exists())->toBeTrue();
});
