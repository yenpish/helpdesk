
<?php

use App\Mail\FeedbackInvitationMail;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\User;
use App\Services\FeedbackInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function makeFeedbackTestEvent(?User $organizer = null): Event
{
    return Event::create([
        'name' => 'Feedback workflow event',
        'organizer_id' => $organizer?->id,
        'starts_at' => now()->subDays(4),
        'ends_at' => now()->subDays(2),
        'status' => 'completed',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);
}

function makeFeedbackTestAttendance(
    Event $event,
    string $email,
    string $name = 'Feedback Participant'
): Attendance {
    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Test Session',
        'pin' => fake()->unique()->numerify('####'),
    ]);

    return Attendance::create([
        'session_id' => $session->id,
        'full_name' => $name,
        'email' => $email,
    ]);
}

function makeFeedbackTestInvitation(
    Event $event,
    Attendance $attendance,
    string $token = 'test-feedback-token'
): FeedbackInvitation {
    return FeedbackInvitation::create([
        'event_id' => $event->id,
        'attendance_id' => $attendance->id,
        'participant_name' => $attendance->full_name,
        'participant_email' => strtolower(trim($attendance->email)),
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDays(7),
    ]);
}

it('sends one invitation per unique attendance email', function () {
    Mail::fake();

    $organizer = User::factory()->create([
        'role' => 'organizer',
    ]);

    $event = makeFeedbackTestEvent($organizer);

    makeFeedbackTestAttendance(
        $event,
        'Participant@example.test',
        'First Participant'
    );

    makeFeedbackTestAttendance(
        $event,
        'participant@example.test',
        'Duplicate Participant'
    );

    makeFeedbackTestAttendance(
        $event,
        '',
        'No Email Participant'
    );

    $this->actingAs($organizer)
        ->post(route('feedback.invitations.send', $event))
        ->assertRedirect()
        ->assertSessionHas(
            'success',
            'Feedback emails: 1 sent.'
        );

    expect(FeedbackInvitation::where('event_id', $event->id)->count())
        ->toBe(1);

    $actionLog = AuditLog::where('auditable_type', Event::class)
        ->where('auditable_id', $event->id)
        ->where('action', 'sent')
        ->sole();
    expect($actionLog->user_id)->toBe($organizer->id)
        ->and($actionLog->description)->toBe('Feedback invitations sent.')
        ->and($actionLog->getRawOriginal('new_values'))->not->toContain('participant@example.test');

    Mail::assertSent(FeedbackInvitationMail::class, function (FeedbackInvitationMail $mail) use ($event) {
        $invitation = FeedbackInvitation::where('event_id', $event->id)->sole();

        return $invitation->token_hash === hash('sha256', $mail->token);
    });
});

it('leaves valid unused invitations unchanged on repeated sends', function () {
    Mail::fake();

    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = makeFeedbackTestEvent($organizer);
    makeFeedbackTestAttendance($event, 'participant@example.test');

    $this->actingAs($organizer)
        ->post(route('feedback.invitations.send', $event))
        ->assertRedirect();

    $original = FeedbackInvitation::where('event_id', $event->id)->first();
    $originalHash = $original->token_hash;
    $originalExpiry = $original->expires_at->toDateTimeString();
    $this->actingAs($organizer)
        ->post(route('feedback.invitations.send', $event))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(FeedbackInvitation::where('event_id', $event->id)->count())->toBe(1)
        ->and($original->fresh()->token_hash)->toBe($originalHash)
        ->and($original->fresh()->expires_at->toDateTimeString())->toBe($originalExpiry)
        ->and($original->fresh()->status)->toBe('accepted');

    Mail::assertSent(FeedbackInvitationMail::class, 1);
});

it('updates the existing invitation safely when the attendance email changes', function () {
    Mail::fake();
    $this->travelTo(now()->startOfSecond());

    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = makeFeedbackTestEvent($organizer);
    $attendance = makeFeedbackTestAttendance($event, 'old@example.test');

    $this->actingAs($organizer)->post(route('feedback.invitations.send', $event))->assertRedirect();
    $invitation = FeedbackInvitation::sole();
    $oldHash = $invitation->token_hash;

    $attendance->update(['email' => 'new@example.test']);
    $this->actingAs($organizer)->post(route('feedback.invitations.send', $event))->assertRedirect();

    expect(FeedbackInvitation::where('event_id', $event->id)->count())->toBe(1)
        ->and($invitation->fresh()->id)->toBe($invitation->id)
        ->and($invitation->fresh()->participant_email)->toBe('new@example.test')
        ->and($invitation->fresh()->token_hash)->not->toBe($oldHash)
        ->and($invitation->fresh()->expires_at->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString());

    Mail::assertSent(FeedbackInvitationMail::class, 2);
    Mail::assertSent(FeedbackInvitationMail::class, fn ($mail) => $mail->hasTo('new@example.test'));
});

it('continues the batch and records individual transport failures', function () {
    $event = makeFeedbackTestEvent();
    makeFeedbackTestAttendance($event, 'failure@example.test');
    makeFeedbackTestAttendance($event, 'success@example.test');

    $summary = app(FeedbackInvitationService::class)->sendForEvent(
        $event,
        function ($invitation) {
            if ($invitation->participant_email === 'failure@example.test') {
                throw new RuntimeException('Transport unavailable');
            }
        }
    );

    expect($summary)->toBe(['accepted' => 1, 'failed' => 1, 'skipped' => 0])
        ->and(FeedbackInvitation::where('status', 'failed')->count())->toBe(1)
        ->and(FeedbackInvitation::where('status', 'accepted')->count())->toBe(1);
});

it('retries failed invitations without duplicating rows and restricts retries to the event owner', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    $event = makeFeedbackTestEvent($organizer);
    $attendance = makeFeedbackTestAttendance($event, 'retry@example.test');
    $invitation = makeFeedbackTestInvitation($event, $attendance, 'failed-original-token');
    $invitation->update(['status' => 'failed']);
    $oldHash = $invitation->token_hash;

    $this->actingAs($organizer)
        ->post(route('feedback.invitations.send', $event))
        ->assertRedirect()
        ->assertSessionHas('success', 'Feedback emails: 1 left unchanged.');
    Mail::assertNothingSent();

    $this->actingAs($otherOrganizer)
        ->post(route('feedback.invitations.retry-failed', $event))
        ->assertForbidden();

    $this->actingAs($organizer)
        ->post(route('feedback.invitations.retry-failed', $event))
        ->assertRedirect()
        ->assertSessionHas('success', 'Feedback emails: 1 sent.');

    expect(FeedbackInvitation::where('event_id', $event->id)->count())->toBe(1)
        ->and($invitation->fresh()->status)->toBe('accepted')
        ->and($invitation->fresh()->token_hash)->not->toBe($oldHash);

    Mail::assertSent(FeedbackInvitationMail::class, 1);
});

it('does not resend or replace a consumed invitation', function () {
    Mail::fake();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = makeFeedbackTestEvent($organizer);
    $attendance = makeFeedbackTestAttendance($event, 'used@example.test');
    $invitation = makeFeedbackTestInvitation($event, $attendance, 'consumed-token');
    $invitation->update(['used_at' => now()]);
    Feedback::create([
        'event_id' => $event->id,
        'participant_name' => $invitation->participant_name,
        'participant_email' => $invitation->participant_email,
        'rating' => 5,
    ]);
    $oldHash = $invitation->token_hash;
    $oldExpiry = $invitation->expires_at->toDateTimeString();

    $this->actingAs($organizer)->post(route('feedback.invitations.send', $event))->assertRedirect();

    expect($invitation->fresh()->token_hash)->toBe($oldHash)
        ->and($invitation->fresh()->expires_at->toDateTimeString())->toBe($oldExpiry)
        ->and($invitation->fresh()->used_at)->not->toBeNull()
        ->and(Feedback::count())->toBe(1);
    Mail::assertNothingSent();
});

it('displays a valid invitation form without editable identity fields', function () {
    $event = makeFeedbackTestEvent();

    $attendance = makeFeedbackTestAttendance(
        $event,
        'participant@example.test',
        'Recorded Participant'
    );

    $token = 'valid-feedback-token';

    makeFeedbackTestInvitation($event, $attendance, $token);

    $this->get(route('feedback.invitation.show', [
        'event' => $event,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertViewIs('feedback.create')
        ->assertSee('Recorded Participant')
        ->assertSee('action="'.route('feedback.invitation.store', [
            'event' => $event,
            'token' => $token,
        ]).'"', false)
        ->assertDontSee('name="participant_name"', false)
        ->assertDontSee('name="participant_email"', false);
});

it('rejects invalid and expired invitation links', function () {
    $event = makeFeedbackTestEvent();

    $attendance = makeFeedbackTestAttendance(
        $event,
        'participant@example.test'
    );

    $token = 'expired-feedback-token';

    $invitation = makeFeedbackTestInvitation(
        $event,
        $attendance,
        $token
    );

    $invitation->update([
        'expires_at' => now()->subMinute(),
    ]);

    $this->get(route('feedback.invitation.show', [
        'event' => $event,
        'token' => $token,
    ]))->assertNotFound();

    $this->get(route('feedback.invitation.show', [
        'event' => $event,
        'token' => 'invalid-token',
    ]))->assertNotFound();
});

it('submits feedback using the invitation identity and consumes the token', function () {
    $event = makeFeedbackTestEvent();

    $attendance = makeFeedbackTestAttendance(
        $event,
        'participant@example.test',
        'Recorded Participant'
    );

    $token = 'successful-feedback-token';

    $invitation = makeFeedbackTestInvitation(
        $event,
        $attendance,
        $token
    );

    $this->post(route('feedback.invitation.store', [
        'event' => $event,
        'token' => $token,
    ]), [
        'rating' => 5,
        'comments' => 'Very useful event.',
    ])
        ->assertOk()
        ->assertViewIs('feedback.thank-you')
        ->assertSee($event->name);

    $feedback = Feedback::sole();

    expect($feedback->event_id)->toBe($event->id)
        ->and($feedback->participant_name)->toBe('Recorded Participant')
        ->and($feedback->participant_email)->toBe('participant@example.test')
        ->and($feedback->rating)->toBe(5)
        ->and($feedback->comments)->toBe('Very useful event.')
        ->and($invitation->fresh()->used_at)->not->toBeNull();

    $this->get(route('feedback.invitation.show', [
        'event' => $event,
        'token' => $token,
    ]))->assertNotFound();

    $this->post(route('feedback.invitation.store', [
        'event' => $event,
        'token' => $token,
    ]), [
        'rating' => 4,
    ])->assertNotFound();

    expect(Feedback::count())->toBe(1);
});

it('rejects invalid feedback without consuming the invitation', function () {
    $event = makeFeedbackTestEvent();

    $attendance = makeFeedbackTestAttendance(
        $event,
        'participant@example.test'
    );

    $token = 'invalid-feedback-submission-token';

    $invitation = makeFeedbackTestInvitation(
        $event,
        $attendance,
        $token
    );

    $this->from(route('feedback.invitation.show', [
        'event' => $event,
        'token' => $token,
    ]))
        ->post(route('feedback.invitation.store', [
            'event' => $event,
            'token' => $token,
        ]), [
            'rating' => 6,
            'comments' => str_repeat('x', 2001),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['rating', 'comments']);

    expect(Feedback::count())->toBe(0)
        ->and($invitation->fresh()->used_at)->toBeNull();
});

it('shows feedback results only to an admin or the event owner', function () {
    $organizer = User::factory()->create([
        'role' => 'organizer',
    ]);

    $otherOrganizer = User::factory()->create([
        'role' => 'organizer',
    ]);

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $event = makeFeedbackTestEvent($organizer);

    $this->actingAs($organizer)
        ->get(route('feedback.index', $event))
        ->assertOk()
        ->assertSee('No ratings yet')
        ->assertSee('No feedback has been submitted for this event yet.');

    Feedback::create([
        'event_id' => $event->id,
        'participant_name' => 'First Feedback',
        'participant_email' => 'first@example.test',
        'rating' => 4,
        'comments' => 'Good.',
    ]);

    Feedback::create([
        'event_id' => $event->id,
        'participant_name' => 'Second Feedback',
        'participant_email' => 'second@example.test',
        'rating' => 5,
    ]);

    foreach ([$organizer, $admin] as $viewer) {
        $this->actingAs($viewer)
            ->get(route('feedback.index', $event))
            ->assertOk()
            ->assertSee('4.5 / 5')
            ->assertSee('First Feedback')
            ->assertSee('Second Feedback')
            ->assertSee('Good.');
    }

    $this->actingAs($otherOrganizer)
        ->get(route('feedback.index', $event))
        ->assertForbidden();
});

it('returns not found for invitations belonging to a missing event', function () {
    $this->get(route('feedback.invitation.show', [
        'event' => 999999,
        'token' => 'nonexistent-token',
    ]))->assertNotFound();

    $this->post(route('feedback.invitation.store', [
        'event' => 999999,
        'token' => 'nonexistent-token',
    ]), [
        'rating' => 5,
    ])->assertNotFound();
});
