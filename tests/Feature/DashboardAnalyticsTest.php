<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dashboardEvent(User $organizer, string $name, string $status = 'published'): Event
{
    return Event::create([
        'name' => $name,
        'starts_at' => now()->startOfDay(),
        'ends_at' => now()->startOfDay()->addDay(),
        'status' => $status,
        'organizer_id' => $organizer->id,
        'pin' => strtoupper(substr(md5($name), 0, 4)),
    ]);
}

function dashboardSession(Event $event, string $name = 'Session'): EventSession
{
    return EventSession::create([
        'event_id' => $event->id,
        'name' => $name,
        'starts_at' => now()->startOfDay()->addHours(8),
        'ends_at' => now()->startOfDay()->addHours(10),
        'attendance_opens_at' => now()->startOfDay()->addHours(8),
        'attendance_closes_at' => now()->startOfDay()->setTime(23, 59),
    ]);
}

function dashboardCheckIn(EventSession $session, string $email, string $checkedInAt): Attendance
{
    return Attendance::create([
        'session_id' => $session->id,
        'full_name' => 'Dashboard Attendee',
        'email' => $email,
        'clock_in_at' => $checkedInAt,
    ]);
}

it('counts published sessions for the organizer and check-ins by their recorded local timestamp', function () {
    $this->travelTo(Carbon::parse('2026-10-09 10:00:00', config('app.timezone')));
    $organizer = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);

    $published = dashboardEvent($organizer, 'Organizer published event');
    $draft = dashboardEvent($organizer, 'Organizer draft event', 'draft');
    $otherEvent = dashboardEvent($otherOrganizer, 'Other organizer event');
    $publishedSession = dashboardSession($published);
    $draftSession = dashboardSession($draft);
    $otherSession = dashboardSession($otherEvent);

    dashboardCheckIn($publishedSession, 'published@example.test', '2026-10-09 09:15:00');
    dashboardCheckIn($draftSession, 'draft@example.test', '2026-10-09 09:30:00');
    dashboardCheckIn($otherSession, 'other@example.test', '2026-10-09 09:45:00');
    Attendance::create([
        'session_id' => $draftSession->id,
        'full_name' => 'Offline Attendee',
        'email' => 'offline@example.test',
        'client_submitted_at' => '2026-10-08 23:50:00',
        'created_at' => '2026-10-09 09:30:00',
        'updated_at' => '2026-10-09 09:30:00',
    ]);

    Registration::create([
        'event_id' => $published->id,
        'guest_name' => 'Pending Guest',
        'guest_email' => 'pending@example.test',
        'guest_phone' => '60123456789',
        'status' => 'pending',
        'registered_at' => now(),
    ]);
    Registration::create([
        'event_id' => $draft->id,
        'guest_name' => 'Draft Guest',
        'guest_email' => 'draft-registration@example.test',
        'guest_phone' => '60123456780',
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    $this->actingAs($organizer)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('<dt>Sessions today</dt><dd>1</dd>', false)
        ->assertSee('<dt>Check-ins today</dt><dd>2</dd>', false)
        ->assertSee('3 check-ins · 03 Oct 2026 to 09 Oct 2026')
        ->assertSee('2026-10-08: 1 check-ins')
        ->assertSee('<dt>Pending pre-registrations</dt><dd>1</dd>', false)
        ->assertSee('Organizer published event')
        ->assertDontSee('Other organizer event');
});

it('filters the attendance trend by the selected date range and organizer scope', function () {
    $this->travelTo(Carbon::parse('2026-10-09 10:00:00', config('app.timezone')));
    $organizer = User::factory()->create(['role' => 'organizer']);
    $otherOrganizer = User::factory()->create(['role' => 'organizer']);
    $ownedSession = dashboardSession(dashboardEvent($organizer, 'Owned trend event'));
    $otherSession = dashboardSession(dashboardEvent($otherOrganizer, 'Other trend event'));

    dashboardCheckIn($ownedSession, 'oct-seven@example.test', '2026-10-07 12:00:00');
    dashboardCheckIn($ownedSession, 'oct-eight@example.test', '2026-10-08 12:00:00');
    dashboardCheckIn($ownedSession, 'oct-nine@example.test', '2026-10-09 12:00:00');
    dashboardCheckIn($otherSession, 'other-oct-seven@example.test', '2026-10-07 13:00:00');

    $this->actingAs($organizer)
        ->get(route('home', [
            'range' => 'custom',
            'from' => '2026-10-07',
            'to' => '2026-10-08',
        ]))
        ->assertOk()
        ->assertSee('2 check-ins · 07 Oct 2026 to 08 Oct 2026')
        ->assertSee('<title>2026-10-07: 1 check-ins</title>', false)
        ->assertDontSee('2026-10-09: 1 check-ins');
});

it('renders a zero-attendance trend for a selected empty date range', function () {
    $this->travelTo(Carbon::parse('2026-10-09 10:00:00', config('app.timezone')));
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('home', [
            'range' => 'custom',
            'from' => '2026-10-01',
            'to' => '2026-10-03',
        ]))
        ->assertOk()
        ->assertSee('0 check-ins · 01 Oct 2026 to 03 Oct 2026')
        ->assertSee('No check-ins in this date range.')
        ->assertSee('aria-label="Daily attendance check-ins from 01 Oct 2026 to 03 Oct 2026"', false);
});

it('falls back safely when a custom attendance date range is invalid', function () {
    $this->travelTo(Carbon::parse('2026-10-09 10:00:00', config('app.timezone')));
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('home', [
            'range' => 'custom',
            'from' => '2026-10-09',
            'to' => '2026-10-01',
        ]))
        ->assertOk()
        ->assertSee('Enter a valid start and end date')
        ->assertSee('Attendance over time');
});
