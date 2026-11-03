
<?php

use App\Mail\FeedbackInvitationMail;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\User;
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
            '1 feedback invitation(s) processed. Check your mail configuration for delivery status.'
        );

    expect(FeedbackInvitation::where('event_id', $event->id)->count())
        ->toBe(1);

    Mail::assertSent(FeedbackInvitationMail::class, 1);
});

it('reissues unused invitations without creating duplicates', function () {
    Mail::fake();

    $organizer = User::factory()->create(['role' => 'organizer']);
    $event = makeFeedbackTestEvent($organizer);
    makeFeedbackTestAttendance($event, 'participant@example.test');

    $this->actingAs($organizer)
        ->post(route('feedback.invitations.send', $event))
        ->assertRedirect();

    $original = FeedbackInvitation::where('event_id', $event->id)->first();
    $this->actingAs($organizer)
        ->post(route('feedback.invitations.send', $event))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(FeedbackInvitation::where('event_id', $event->id)->count())->toBe(1)
        ->and($original->fresh()->token_hash)->not->toBe($original->token_hash);

    Mail::assertSent(FeedbackInvitationMail::class, 2);
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
        'event' => $event->id,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertViewIs('feedback.create')
        ->assertSee('Recorded Participant')
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
        'event' => $event->id,
        'token' => $token,
    ]))->assertNotFound();

    $this->get(route('feedback.invitation.show', [
        'event' => $event->id,
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
        'event' => $event->id,
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
        'event' => $event->id,
        'token' => $token,
    ]))->assertNotFound();

    $this->post(route('feedback.invitation.store', [
        'event' => $event->id,
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
        'event' => $event->id,
        'token' => $token,
    ]))
        ->post(route('feedback.invitation.store', [
            'event' => $event->id,
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
