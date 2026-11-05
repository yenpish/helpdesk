<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeLiveAttendanceAccessFixture(User $organizer): array
{
    $event = Event::create([
        'name' => 'Live monitor event',
        'organizer_id' => $organizer->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => strtoupper(fake()->unique()->bothify('??##')),
    ]);

    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Live session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);

    return [$event, $session];
}

it('allows an admin to refresh the Session Details attendee table without a full page reload', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeLiveAttendanceAccessFixture($organizer);

    $this->actingAs($admin)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('data-attendance-refresh', false)
        ->assertSee('data-attendance-refresh-status', false)
        ->assertDontSee('Up to date')
        ->assertSee('refreshAttendance(true)', false)
        ->assertSee("manual && refreshStatus", false)
        ->assertSee('setInterval(() =>', false)
        ->assertSee('15000', false);

    Attendance::create([
        'full_name' => 'Just Checked In',
        'email' => 'checked-in@example.test',
        'phone' => '60125902441',
        'session_id' => $session->id,
        'verification_method' => 'pin',
        'clock_in_at' => now(),
    ]);

    $initial = $this->actingAs($admin)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('class="attendance-signature-action-cell">Actions', false)
        ->assertSee('data-edit-attendance', false)
        ->assertDontSee('attendance-name-edit', false);
    expect(preg_match(
        '/<td class="attendance-signature-action-cell">\s*<div class="attendance-row-actions">\s*<button[^>]*data-edit-attendance/s',
        $initial->getContent()
    ))->toBe(1);

    $response = $this->actingAs($admin)
        ->getJson(route('events.event-sessions.show', [$event, $session]) . '?attendance_refresh=1')
        ->assertOk()
        ->assertJsonPath('counts.attended', 1);

    expect($response->json('html'))
        ->toContain('Just Checked In')
        ->toContain('class="attendance-signature-action-cell">Actions')
        ->toContain('data-edit-attendance')
        ->toContain('attendance-row-actions');
    expect($initial->getContent())
        ->toContain('data-edit-attendance')
        ->toContain('attendance-row-actions');
});

it('allows the owning organizer to refresh the same Session Details table', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeLiveAttendanceAccessFixture($organizer);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertOk()
        ->assertSee('Session check-ins');

    $this->actingAs($organizer)
        ->getJson(route('events.event-sessions.show', [$event, $session]) . '?attendance_refresh=1')
        ->assertOk()
        ->assertJsonPath('counts.attended', 0);
});

it('denies another organizer access to Session Details and its refresh data', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeLiveAttendanceAccessFixture($owner);

    $this->actingAs($otherOrganizer)
        ->get(route('events.event-sessions.show', [$event, $session]))
        ->assertForbidden();

    $this->actingAs($otherOrganizer)
        ->getJson(route('events.event-sessions.show', [$event, $session]) . '?attendance_refresh=1')
        ->assertForbidden();
});
