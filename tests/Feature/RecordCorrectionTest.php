<?php

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function recordCorrectionEvent(User $organizer, string $suffix = ''): array
{
    $event = Event::create([
        'name' => 'Correction event '.$suffix,
        'organizer_id' => $organizer->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'status' => 'published',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);

    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Correction session',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'attendance_opens_at' => now()->subHour(),
        'attendance_closes_at' => now()->addHour(),
    ]);

    return [$event, $session];
}

function recordCorrectionAttendance(EventSession $session, array $attributes = []): Attendance
{
    return Attendance::create(array_merge([
        'session_id' => $session->id,
        'full_name' => 'Original Attendee',
        'email' => 'original@example.test',
        'phone' => '60123456789',
        'position' => 'Intern',
        'unit' => 'Original Organisation',
        'signature' => 'private-signature-value',
        'clock_in_at' => now()->subMinutes(15),
        'verification_method' => 'pin',
    ], $attributes));
}

function recordCorrectionRegistration(Event $event, array $attributes = []): Registration
{
    $registrationAttributes = array_merge([
        'event_id' => $event->id,
        'guest_name' => 'Original Registrant',
        'guest_email' => 'original-registration@example.test',
        'guest_phone' => '60123456789',
        'organisation' => 'Original Organisation',
        'position' => 'Intern',
        'status' => 'approved',
        'registered_at' => now()->subDay(),
        'decision_email_status' => 'accepted',
        'decision_email_for' => 'approved',
        'decision_email_attempted_at' => now()->subHour(),
    ], $attributes);
    $registration = Registration::create($registrationAttributes);
    $registration->forceFill(array_intersect_key($registrationAttributes, array_flip([
        'decision_email_status',
        'decision_email_for',
        'decision_email_attempted_at',
    ])))->save();

    return $registration;
}

it('corrects attendance details while preserving check-in history and auditing only changed field names', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = recordCorrectionEvent($organizer);
    $attendance = recordCorrectionAttendance($session);
    $originalCheckIn = $attendance->clock_in_at->toDateTimeString();

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee(route('events.event-sessions.attendance.edit', [$event, $session, $attendance]))
        ->assertDontSee('data-edit-attendance', false);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.attendance.edit', [$event, $session, $attendance]))
        ->assertOk()
        ->assertSee('Edit Attendance')
        ->assertSee('value="Original Attendee"', false)
        ->assertSee('Cancel');

    $returnUrl = route('events.event-sessions.show', [$event, $session, 'search' => 'Original', 'status' => 'approved', 'page' => 2]);

    $this->actingAs($organizer)
        ->patch(route('events.event-sessions.attendance.update', [$event, $session, $attendance]), [
            'full_name' => 'Corrected Name',
            'email' => ' CORRECTED@example.test ',
            'phone' => '+60 12-345 6789',
            'position' => 'Developer',
            'unit' => 'Corrected Organisation',
            'signature' => 'tampered-signature',
            'clock_in_at' => now()->toDateTimeString(),
            'session_id' => 999,
            '_return_search' => 'Original',
            '_return_status' => 'approved',
            '_return_page' => '2',
        ])
        ->assertRedirect($returnUrl)
        ->assertSessionHas('success', 'Attendance details updated.');

    $attendance->refresh();
    expect($attendance->full_name)->toBe('Corrected Name')
        ->and($attendance->email)->toBe('corrected@example.test')
        ->and($attendance->phone)->toBe('60123456789')
        ->and($attendance->position)->toBe('Developer')
        ->and($attendance->unit)->toBe('Corrected Organisation')
        ->and($attendance->clock_in_at->toDateTimeString())->toBe($originalCheckIn)
        ->and($attendance->signature)->toBe('private-signature-value')
        ->and($attendance->session_id)->toBe($session->id);

    $log = AuditLog::where('auditable_type', Attendance::class)
        ->where('auditable_id', $attendance->id)->sole();
    expect($log->user_id)->toBe($organizer->id)
        ->and($log->description)->toBe('Attendance details corrected.')
        ->and($log->new_values)->toBe(['fields_changed' => ['email', 'full_name', 'position', 'unit']])
        ->and(json_encode($log->getAttributes()))->not->toContain('corrected@example.test')
        ->and(json_encode($log->getAttributes()))->not->toContain('private-signature-value');
});

it('rejects duplicate attendance email in a session and event phone conflicts', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $firstSession] = recordCorrectionEvent($organizer, 'one');
    $secondSession = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Second correction session',
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
        'attendance_opens_at' => now()->addHours(2),
        'attendance_closes_at' => now()->addHours(3),
    ]);
    $target = recordCorrectionAttendance($firstSession);
    $sameSession = recordCorrectionAttendance($firstSession, [
        'full_name' => 'Other attendee',
        'email' => 'other@example.test',
        'phone' => '60111111111',
    ]);
    recordCorrectionAttendance($secondSession, [
        'full_name' => 'Cross-session attendee',
        'email' => 'cross@example.test',
        'phone' => '60122222222',
    ]);

    $this->actingAs($organizer)
        ->from(route('events.event-sessions.show', [$event, $firstSession]))
        ->patch(route('events.event-sessions.attendance.update', [$event, $firstSession, $target]), [
            'full_name' => 'Original Attendee',
            'email' => $sameSession->email,
            'phone' => '60125902442',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    $this->actingAs($organizer)
        ->from(route('events.event-sessions.show', [$event, $firstSession]))
        ->patch(route('events.event-sessions.attendance.update', [$event, $firstSession, $target]), [
            'full_name' => 'Original Attendee',
            'email' => 'new@example.test',
            'phone' => '60111111111',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('phone');

    $this->actingAs($organizer)
        ->from(route('events.event-sessions.show', [$event, $firstSession]))
        ->patch(route('events.event-sessions.attendance.update', [$event, $firstSession, $target]), [
            'full_name' => 'Original Attendee',
            'email' => 'new@example.test',
            'phone' => '60122222222',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('phone');
});

it('denies attendance correction to another organizer', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $other = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = recordCorrectionEvent($owner);
    $attendance = recordCorrectionAttendance($session);

    $this->actingAs($other)
        ->get(route('events.event-sessions.attendance.edit', [$event, $session, $attendance]))
        ->assertForbidden();

    $this->actingAs($other)
        ->patch(route('events.event-sessions.attendance.update', [$event, $session, $attendance]), [
            'full_name' => 'Unauthorized edit',
            'email' => 'unauthorized@example.test',
            'phone' => '60123456789',
        ])
        ->assertForbidden();

    expect($attendance->fresh()->full_name)->toBe('Original Attendee');
});

it('corrects pre-registration contact details without changing approval or email history', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event] = recordCorrectionEvent($organizer);
    $participant = User::factory()->create(['role' => 'user']);
    $registration = recordCorrectionRegistration($event, ['user_id' => $participant->id]);
    $registeredAt = $registration->registered_at->toDateTimeString();
    $emailAttemptedAt = $registration->decision_email_attempted_at->toDateTimeString();

    $this->actingAs($organizer)
        ->get(route('events.registrations.index', $event))
        ->assertOk()
        ->assertSee(route('events.registrations.edit', [$event, $registration]))
        ->assertDontSee('data-edit-registration', false);

    $this->actingAs($organizer)
        ->get(route('events.registrations.edit', [$event, $registration]))
        ->assertOk()
        ->assertSee('Edit pre-registration')
        ->assertSee('value="Original Registrant"', false)
        ->assertSee('Approved')
        ->assertSee('Cancel');

    Mail::fake();
    $returnUrl = route('events.registrations.index', [$event, 'search' => 'Original', 'status' => 'approved', 'page' => 2]);

    $this->actingAs($organizer)
        ->patch(route('events.registrations.details', [$event, $registration]), [
            'guest_name' => 'Corrected Registrant',
            'guest_email' => ' CORRECTED-REGISTRATION@example.test ',
            'guest_phone' => '+60 12-345 6789',
            'organisation' => 'Corrected Organisation',
            'position' => 'Assistant',
            'status' => 'rejected',
            'event_id' => 999,
            'registered_at' => now()->toDateTimeString(),
            'decision_email_status' => 'failed',
            '_return_search' => 'Original',
            '_return_status' => 'approved',
            '_return_page' => '2',
        ])
        ->assertRedirect($returnUrl)
        ->assertSessionHas('success', 'Pre-registration details updated.');

    $registration->refresh();
    expect($registration->guest_name)->toBe('Corrected Registrant')
        ->and($registration->guest_email)->toBe('corrected-registration@example.test')
        ->and($registration->guest_phone)->toBe('60123456789')
        ->and($registration->organisation)->toBe('Corrected Organisation')
        ->and($registration->position)->toBe('Assistant')
        ->and($registration->status)->toBe('approved')
        ->and($registration->event_id)->toBe($event->id)
        ->and($registration->user_id)->toBe($participant->id)
        ->and($registration->registered_at->toDateTimeString())->toBe($registeredAt)
        ->and($registration->decision_email_status)->toBe('accepted')
        ->and($registration->decision_email_for)->toBe('approved')
        ->and($registration->decision_email_attempted_at->toDateTimeString())->toBe($emailAttemptedAt);
    Mail::assertNothingOutgoing();

    $log = AuditLog::where('auditable_type', Registration::class)
        ->where('auditable_id', $registration->id)->sole();
    expect($log->user_id)->toBe($organizer->id)
        ->and($log->new_values)->toBe(['fields_changed' => ['guest_email', 'guest_name', 'organisation', 'position']])
        ->and(json_encode($log->getAttributes()))->not->toContain('corrected-registration@example.test');
});

it('rejects duplicate pre-registration email and phone details', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event] = recordCorrectionEvent($organizer);
    $target = recordCorrectionRegistration($event);
    $other = recordCorrectionRegistration($event, [
        'guest_name' => 'Other registrant',
        'guest_email' => 'other-registration@example.test',
        'guest_phone' => '60111111111',
    ]);

    $this->actingAs($organizer)
        ->from(route('events.registrations.index', $event))
        ->patch(route('events.registrations.details', [$event, $target]), [
            'guest_name' => 'Corrected Name',
            'guest_email' => $other->guest_email,
            'guest_phone' => $other->guest_phone,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['guest_email', 'guest_phone']);
});

it('returns a pre-registration validation failure to its edit page with entered values retained', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event] = recordCorrectionEvent($organizer);
    $registration = recordCorrectionRegistration($event);
    Mail::fake();
    $editUrl = route('events.registrations.edit', [$event, $registration, 'search' => 'Original', 'status' => 'approved', 'page' => 2]);

    $this->actingAs($organizer)
        ->get($editUrl)
        ->assertOk()
        ->assertSee(route('events.registrations.index', [$event, 'search' => 'Original', 'status' => 'approved', 'page' => 2]))
        ->assertSee('Cancel');

    $this->from($editUrl)
        ->followingRedirects()
        ->patch(route('events.registrations.details', [$event, $registration]), [
            'guest_name' => 'Retained Name',
            'guest_email' => 'not-an-email',
            'guest_phone' => '60123456789',
            '_return_search' => 'Original',
            '_return_status' => 'approved',
            '_return_page' => '2',
        ])
        ->assertOk()
        ->assertSee('Edit pre-registration')
        ->assertSee('value="Retained Name"', false)
        ->assertSee('value="not-an-email"', false)
        ->assertSee('role="alert"', false)
        ->assertSee('class="field-error"', false);

    expect($registration->fresh()->guest_name)->toBe('Original Registrant')
        ->and($registration->fresh()->guest_email)->toBe('original-registration@example.test');
    Mail::assertNothingOutgoing();
});

it('returns an attendance validation failure to its edit page with entered values retained', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = recordCorrectionEvent($organizer);
    $attendance = recordCorrectionAttendance($session);
    $editUrl = route('events.event-sessions.attendance.edit', [$event, $session, $attendance, 'search' => 'Original', 'status' => 'approved', 'page' => 2]);

    $this->actingAs($organizer)
        ->get($editUrl)
        ->assertOk()
        ->assertSee(route('events.event-sessions.show', [$event, $session, 'search' => 'Original', 'status' => 'approved', 'page' => 2]))
        ->assertSee('Cancel');

    $this->from($editUrl)
        ->followingRedirects()
        ->patch(route('events.event-sessions.attendance.update', [$event, $session, $attendance]), [
            'full_name' => 'Retained Attendance Name',
            'email' => 'not-an-email',
            'phone' => '60123456789',
            '_return_search' => 'Original',
            '_return_status' => 'approved',
            '_return_page' => '2',
        ])
        ->assertOk()
        ->assertSee('Edit Attendance')
        ->assertSee('value="Retained Attendance Name"', false)
        ->assertSee('value="not-an-email"', false)
        ->assertSee('role="alert"', false)
        ->assertSee('class="field-error"', false);

    expect($attendance->fresh()->full_name)->toBe('Original Attendee')
        ->and($attendance->fresh()->email)->toBe('original@example.test');
});

it('denies pre-registration correction to another organizer', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $other = User::factory()->create(['role' => 'organizer']);
    [$event] = recordCorrectionEvent($owner);
    $registration = recordCorrectionRegistration($event);

    $this->actingAs($other)
        ->get(route('events.registrations.edit', [$event, $registration]))
        ->assertForbidden();

    $this->actingAs($other)
        ->patch(route('events.registrations.details', [$event, $registration]), [
            'guest_name' => 'Unauthorized edit',
            'guest_email' => 'unauthorized@example.test',
            'guest_phone' => '60123456789',
        ])
        ->assertForbidden();

    expect($registration->fresh()->guest_name)->toBe('Original Registrant');
});

it('allows an admin to correct both attendance and pre-registration details', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $admin = User::factory()->create(['role' => 'admin']);
    [$event, $session] = recordCorrectionEvent($organizer, 'admin');
    $attendance = recordCorrectionAttendance($session);
    $registration = recordCorrectionRegistration($event);

    $this->actingAs($admin)
        ->get(route('events.event-sessions.attendance.edit', [$event, $session, $attendance]))
        ->assertOk()
        ->assertSee('Edit Attendance');

    $this->actingAs($admin)
        ->patch(route('events.event-sessions.attendance.update', [$event, $session, $attendance]), [
            'full_name' => 'Admin Corrected Attendance',
            'email' => 'admin-attendance@example.test',
            'phone' => '60123456789',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('events.registrations.edit', [$event, $registration]))
        ->assertOk()
        ->assertSee('Edit pre-registration');

    $this->actingAs($admin)
        ->patch(route('events.registrations.details', [$event, $registration]), [
            'guest_name' => 'Admin Corrected Registration',
            'guest_email' => 'admin-registration@example.test',
            'guest_phone' => '60123456789',
        ])
        ->assertRedirect();

    expect($attendance->fresh()->full_name)->toBe('Admin Corrected Attendance')
        ->and($registration->fresh()->guest_name)->toBe('Admin Corrected Registration');
});

it('rejects attendance and pre-registration IDs from a different parent record', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = recordCorrectionEvent($organizer, 'parent');
    [$otherEvent, $otherSession] = recordCorrectionEvent($organizer, 'other');
    $attendance = recordCorrectionAttendance($otherSession);
    $registration = recordCorrectionRegistration($otherEvent);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.attendance.edit', [$event, $session, $attendance]))
        ->assertNotFound();

    $this->actingAs($organizer)
        ->get(route('events.registrations.edit', [$event, $registration]))
        ->assertNotFound();

    $this->actingAs($organizer)
        ->patch(route('events.event-sessions.attendance.update', [$event, $session, $attendance]), [
            'full_name' => 'Tampered',
            'email' => 'tampered-attendance@example.test',
            'phone' => '60123456789',
        ])
        ->assertNotFound();

    $this->actingAs($organizer)
        ->patch(route('events.registrations.details', [$event, $registration]), [
            'guest_name' => 'Tampered',
            'guest_email' => 'tampered-registration@example.test',
            'guest_phone' => '60123456789',
        ])
        ->assertNotFound();

    expect($attendance->fresh()->full_name)->toBe('Original Attendee')
        ->and($registration->fresh()->guest_name)->toBe('Original Registrant');
});
