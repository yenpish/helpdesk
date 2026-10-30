<?php

use App\Models\Event;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

function validFeedbackPayload(array $overrides = []): array
{
    return array_merge([
        'participant_name' => 'Feedback Participant',
        'participant_email' => 'participant@example.test',
        'rating' => 5,
        'comments' => 'Useful session.',
    ], $overrides);
}

it('renders feedback for the event and persists valid public submissions', function () {
    $event = makeFeedbackTestEvent();

    $this->get(route('feedback.create', $event))
        ->assertOk()
        ->assertViewIs('feedback.create')
        ->assertSee($event->name);

    $this->post(route('feedback.store', $event), validFeedbackPayload([
        'participant_email' => ' Participant@Example.Test ',
    ]))
        ->assertRedirect(route('registrations.public-index') . '#past-events')
        ->assertSessionHas('success', 'Thank you for your feedback!');

    $feedback = Feedback::sole();
    expect($feedback->event_id)->toBe($event->id)
        ->and($feedback->participant_email)->toBe('participant@example.test')
        ->and($feedback->rating)->toBe(5)
        ->and($feedback->comments)->toBe('Useful session.');
});

it('rejects invalid feedback without persisting a response', function () {
    $event = makeFeedbackTestEvent();

    $this->from(route('feedback.create', $event))
        ->post(route('feedback.store', $event), validFeedbackPayload([
            'participant_name' => '',
            'participant_email' => 'not-an-email',
            'rating' => 6,
            'comments' => str_repeat('x', 2001),
        ]))
        ->assertRedirect(route('feedback.create', $event))
        ->assertSessionHasErrors(['participant_name', 'participant_email', 'rating', 'comments']);

    expect(Feedback::count())->toBe(0);
});

it('prevents repeat feedback for the same event and normalized email', function () {
    $event = makeFeedbackTestEvent();
    Feedback::create([
        'event_id' => $event->id,
        'participant_name' => 'First Submitter',
        'participant_email' => 'participant@example.test',
        'rating' => 4,
    ]);

    $this->from(route('feedback.create', $event))
        ->post(route('feedback.store', $event), validFeedbackPayload([
            'participant_email' => 'Participant@Example.Test',
        ]))
        ->assertRedirect(route('feedback.create', $event))
        ->assertSessionHasErrors('participant_email');

    expect(Feedback::where('event_id', $event->id)->count())->toBe(1);
});

it('shows empty and populated results only to an admin or the event owner', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    $admin = User::factory()->create(['role' => 'admin']);
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

it('returns not found for a missing public feedback event', function () {
    $this->get(route('feedback.create', 999999))->assertNotFound();
    $this->post(route('feedback.store', 999999), validFeedbackPayload())->assertNotFound();
});
