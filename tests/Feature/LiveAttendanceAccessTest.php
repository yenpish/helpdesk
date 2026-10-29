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

it('allows an admin to view and poll Live Attendance', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeLiveAttendanceAccessFixture($organizer);

    $this->actingAs($admin)
        ->get(route('events.event-sessions.live-attendance', [$event, $session]))
        ->assertOk()
        ->assertSee('Live Attendance')
        ->assertSee('window.setInterval(refresh, 5000)', false);

    Attendance::create([
        'full_name' => 'Just Checked In',
        'email' => 'checked-in@example.test',
        'phone' => '60125902441',
        'session_id' => $session->id,
        'verification_method' => 'pin',
        'clock_in_at' => now(),
    ]);

    $this->actingAs($admin)
        ->getJson(route('events.event-sessions.live-attendance.data', [$event, $session]))
        ->assertOk()
        ->assertJsonPath('checked_in', 1)
        ->assertJsonPath('check_ins.0.name', 'Just Checked In');
});

it('allows the owning organizer to view Live Attendance', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeLiveAttendanceAccessFixture($organizer);

    $this->actingAs($organizer)
        ->get(route('events.event-sessions.live-attendance', [$event, $session]))
        ->assertOk();

    $this->actingAs($organizer)
        ->getJson(route('events.event-sessions.live-attendance.data', [$event, $session]))
        ->assertOk()
        ->assertJsonPath('checked_in', 0);
});

it('denies another organizer access to Live Attendance and its data endpoint', function () {
    $owner = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    [$event, $session] = makeLiveAttendanceAccessFixture($owner);

    $this->actingAs($otherOrganizer)
        ->get(route('events.event-sessions.live-attendance', [$event, $session]))
        ->assertForbidden();

    $this->actingAs($otherOrganizer)
        ->getJson(route('events.event-sessions.live-attendance.data', [$event, $session]))
        ->assertForbidden();
});
