<?php

use App\Mail\RegistrationDecisionMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function approvalWorkflowEvent(User $organizer): Event
{
    $event = Event::create([
        'name' => 'Registration approval event',
        'starts_at' => now()->addDays(4),
        'ends_at' => now()->addDays(4)->addHours(2),
        'organizer_id' => $organizer->id,
        'status' => 'published',
        'pin' => 'R7Q2',
    ]);

    EventSession::create([
        'event_id' => $event->id,
        'name' => 'Morning session',
        'starts_at' => $event->starts_at,
        'ends_at' => $event->ends_at,
        'attendance_opens_at' => $event->starts_at,
        'attendance_closes_at' => $event->ends_at,
    ]);

    return $event;
}

function approvalWorkflowRegistration(Event $event, string $status = 'pending'): Registration
{
    return Registration::create([
        'event_id' => $event->id,
        'guest_name' => 'Sample Attendee',
        'guest_email' => 'sample-attendee@example.test',
        'guest_phone' => '60123456789',
        'organisation' => 'Example Organisation',
        'position' => 'Intern',
        'status' => $status,
        'registered_at' => now(),
    ]);
}

it('emails an attendee when approved and clarifies event registration is separate from attendance', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = approvalWorkflowEvent($organizer);
    $registration = approvalWorkflowRegistration($event);

    $this->actingAs($organizer)
        ->patch(route('events.registrations.status', [$event, $registration]), ['status' => 'approved'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Registration approved. Email sent.');

    expect($registration->fresh()->status)->toBe('approved')
        ->and($registration->fresh()->decision_email_status)->toBe('accepted')
        ->and($registration->fresh()->decision_email_for)->toBe('approved')
        ->and(AuditLog::where('auditable_type', Registration::class)->where('auditable_id', $registration->id)->where('action', 'updated')->exists())->toBeTrue();

    Mail::assertSent(RegistrationDecisionMail::class, 1);
    $mail = Mail::sent(RegistrationDecisionMail::class)->first();
    expect($mail->hasTo('sample-attendee@example.test'))->toBeTrue()
        ->and($mail->render())
        ->toContain('Your registration for')
        ->toContain('We look forward to seeing you!');
});

it('emails a rejection with a correction path and resends no email for an unchanged decision', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = approvalWorkflowEvent($organizer);
    $registration = approvalWorkflowRegistration($event);

    $this->actingAs($organizer)
        ->patch(route('events.registrations.status', [$event, $registration]), ['status' => 'rejected'])
        ->assertRedirect();
    $this->actingAs($organizer)
        ->patch(route('events.registrations.status', [$event, $registration]), ['status' => 'rejected'])
        ->assertRedirect()
        ->assertSessionHas('success', 'No status change was needed; no email was sent.');

    Mail::assertSent(RegistrationDecisionMail::class, 1);
    expect(AuditLog::where('auditable_type', Registration::class)->where('auditable_id', $registration->id)->where('action', 'updated')->count())->toBe(2);
    Mail::assertSent(RegistrationDecisionMail::class, fn (RegistrationDecisionMail $mail): bool =>
        str_contains($mail->render(), route('registrations.create', $event))
    );
});

it('does not let another organizer change a decision or send its email', function () {
    Mail::fake();
    $owner = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    $event = approvalWorkflowEvent($owner);
    $registration = approvalWorkflowRegistration($event);

    $this->actingAs($otherOrganizer)
        ->patch(route('events.registrations.status', [$event, $registration]), ['status' => 'approved'])
        ->assertForbidden();

    expect($registration->fresh()->status)->toBe('pending');
    Mail::assertNothingOutgoing();
});

it('tracks a failed decision email and lets the authorized organizer retry without changing status', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = approvalWorkflowEvent($organizer);
    $registration = approvalWorkflowRegistration($event);

    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('private transport diagnostic'));
    $this->actingAs($organizer)
        ->patch(route('events.registrations.status', [$event, $registration]), ['status' => 'approved'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Registration approved. Email could not be sent.');

    expect($registration->fresh()->status)->toBe('approved')
        ->and($registration->fresh()->decision_email_status)->toBe('failed');

    Mail::shouldReceive('to')->once()->andReturn(new class
    {
        public function send($mailable): void {}
    });
    $this->actingAs($organizer)
        ->patch(route('events.registrations.status', [$event, $registration]), [
            'status' => 'approved',
            'retry_decision_email' => '1',
        ])
        ->assertRedirect();

    $retryLog = AuditLog::where('auditable_type', Registration::class)
        ->where('auditable_id', $registration->id)
        ->where('action', 'retried')
        ->firstOrFail();

    expect($registration->fresh()->status)->toBe('approved')
        ->and($registration->fresh()->decision_email_status)->toBe('accepted')
        ->and(AuditLog::where('auditable_type', Registration::class)->where('auditable_id', $registration->id)->where('action', 'retried')->count())->toBe(1)
        ->and($retryLog->getRawOriginal('new_values'))->not->toContain('private transport diagnostic');
});

it('lets a rejected or cancelled attendee correct and resubmit the same event registration', function (string $status) {
    $event = approvalWorkflowEvent(User::factory()->create(['role' => 'organizer']));
    $registration = approvalWorkflowRegistration($event, $status);

    $this->post(route('registrations.store', $event), [
        'guest_name' => 'Corrected Attendee',
        'guest_email' => 'SAMPLE-attendee@example.test',
        'guest_phone' => '+60 12-345 6789',
        'organisation' => 'Corrected Organisation',
        'position' => 'Trainee',
    ])->assertRedirect(route('registrations.success', $registration))
        ->assertSessionHas('success', 'Your corrected pre-registration has been sent for review.');

    expect(Registration::where('event_id', $event->id)->count())->toBe(1)
        ->and($registration->fresh()->status)->toBe('pending')
        ->and($registration->fresh()->guest_name)->toBe('Corrected Attendee')
        ->and($registration->fresh()->guest_phone)->toBe('60123456789')
        ->and($registration->fresh()->organisation)->toBe('Corrected Organisation')
        ->and(AuditLog::where('auditable_type', Registration::class)->where('auditable_id', $registration->id)->where('action', 'resubmitted')->exists())->toBeTrue();
})->with(['rejected', 'cancelled']);

it('does not allow resubmission of a rejected registration with a different phone number', function () {
    $event = approvalWorkflowEvent(User::factory()->create(['role' => 'organizer']));
    $registration = approvalWorkflowRegistration($event, 'rejected');

    $this->from(route('registrations.create', $event))
        ->post(route('registrations.store', $event), [
            'guest_name' => 'Unexpected Attendee',
            'guest_email' => 'sample-attendee@example.test',
            'guest_phone' => '60111111111',
        ])
        ->assertRedirect(route('registrations.create', $event))
        ->assertSessionHasErrors('guest_email');

    expect($registration->fresh()->status)->toBe('rejected')
        ->and($registration->fresh()->guest_name)->toBe('Sample Attendee');
});
