<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeScheduledEvent(string $start = '2026-10-06 08:00:00', string $end = '2026-10-07 12:59:00', int $sessionDays = 2): array
{
    $event = Event::create([
        'name' => 'Schedule safety event',
        'starts_at' => Carbon::parse($start),
        'ends_at' => Carbon::parse($end),
        'status' => 'published',
        'pin' => 'RSTU',
    ]);

    $sessions = collect();
    $startDate = Carbon::parse($start)->startOfDay();

    for ($day = 0; $day < $sessionDays; $day++) {
        $sessionStart = $startDate->copy()->addDays($day)->setTime(8, 0);
        $sessionEnd = $sessionStart->copy()->setTime(18, 20);
        $sessions->push(EventSession::create([
            'event_id' => $event->id,
            'name' => 'Session ' . ($day + 1),
            'description' => 'Session details ' . ($day + 1),
            'starts_at' => $sessionStart,
            'ends_at' => $sessionEnd,
            'attendance_opens_at' => $sessionStart->copy()->subMinutes(15),
            'attendance_closes_at' => $sessionEnd->copy()->addMinutes(15),
        ]));
    }

    return [$event, $sessions];
}

function scheduleUpdatePayload(Event $event, string $start, string $end, int $sessionCount): array
{
    return [
        'name' => $event->name,
        'starts_at' => $start,
        'ends_at' => $end,
        'status' => $event->status,
        'sync_sessions' => '1',
        'expected_sessions' => (string) $sessionCount,
    ];
}

function recordSessionAttendance(EventSession $session, string $email = 'attendee@example.test'): Attendance
{
    return Attendance::create([
        'full_name' => 'Test Attendee',
        'email' => $email,
        'session_id' => $session->id,
    ]);
}

it('requires confirmation before changing event dates', function () {
    [$event] = makeScheduledEvent();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('events.edit', $event))
        ->put(route('events.update', $event), [
            'name' => $event->name,
            'starts_at' => '2026-10-13 08:00:00',
            'ends_at' => '2026-10-14 12:59:00',
            'status' => 'published',
        ])
        ->assertRedirect(route('events.edit', $event))
        ->assertSessionHasErrors('schedule');

    expect($event->fresh()->starts_at->toDateTimeString())->toBe('2026-10-06 08:00:00');
});

it('shows a short rescheduling confirmation with explicit actions', function () {
    [$event] = makeScheduledEvent();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee('Reschedule sessions?')
        ->assertSee('Attendance records will stay with their sessions.')
        ->assertSee('Reschedule Event')
        ->assertSee('Cancel');
});

it('moves existing sessions by seven days and keeps attendance attached', function () {
    [$event, $sessions] = makeScheduledEvent();
    $firstAttendance = recordSessionAttendance($sessions[0]);
    $secondAttendance = recordSessionAttendance($sessions[1], 'second@example.test');
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('events.update', $event), scheduleUpdatePayload(
            $event,
            '2026-10-13 08:00:00',
            '2026-10-14 12:59:00',
            2
        ))
        ->assertRedirect(route('events.show', $event));

    foreach ($sessions as $session) {
        $updated = $session->fresh();
        expect($updated->starts_at->toDateTimeString())->toBe($session->starts_at->copy()->addDays(7)->toDateTimeString())
            ->and($updated->ends_at->toDateTimeString())->toBe($session->ends_at->copy()->addDays(7)->toDateTimeString())
            ->and($updated->attendance_opens_at->toDateTimeString())->toBe($session->attendance_opens_at->copy()->addDays(7)->toDateTimeString())
            ->and($updated->attendance_closes_at->toDateTimeString())->toBe($updated->starts_at->copy()->setTime(23, 59)->toDateTimeString());
    }

    expect($firstAttendance->fresh()->session_id)->toBe($sessions[0]->id)
        ->and($secondAttendance->fresh()->session_id)->toBe($sessions[1]->id)
        ->and($sessions[0]->fresh()->description)->toBe('Session details 1');
});

it('moves session times by the precise event start datetime offset', function () {
    [$event, $sessions] = makeScheduledEvent();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('events.update', $event), scheduleUpdatePayload(
            $event,
            '2026-10-06 11:30:00',
            '2026-10-07 16:29:00',
            2
        ))
        ->assertRedirect(route('events.show', $event));

    expect($sessions[0]->fresh()->starts_at->toDateTimeString())->toBe('2026-10-06 11:30:00')
        ->and($sessions[0]->fresh()->ends_at->toDateTimeString())->toBe('2026-10-06 21:50:00')
        ->and($sessions[0]->fresh()->attendance_opens_at->toDateTimeString())->toBe('2026-10-06 11:15:00')
        ->and($sessions[0]->fresh()->attendance_closes_at->toDateTimeString())->toBe('2026-10-06 23:59:00')
        ->and($sessions[1]->fresh()->starts_at->toDateTimeString())->toBe('2026-10-07 11:30:00');
});

it('creates automatic daily sessions with attendance open until 11:59 PM', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('events.store'), [
            'name' => 'New multi-day event',
            'starts_at' => '2026-10-20 08:00:00',
            'ends_at' => '2026-10-21 16:00:00',
        ])
        ->assertRedirect();

    $event = Event::where('name', 'New multi-day event')->firstOrFail();
    $sessions = $event->sessions()->orderBy('starts_at')->get();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]->starts_at->toDateTimeString())->toBe('2026-10-20 08:00:00')
        ->and($sessions[0]->attendance_opens_at->toDateTimeString())->toBe('2026-10-20 08:00:00')
        ->and($sessions[0]->attendance_closes_at->toDateTimeString())->toBe('2026-10-20 23:59:00')
        ->and($sessions[1]->starts_at->toDateTimeString())->toBe('2026-10-21 08:00:00')
        ->and($sessions[1]->attendance_closes_at->toDateTimeString())->toBe('2026-10-21 23:59:00');
});

it('sets the attendance close to 11:59 PM when a session is manually created or edited', function () {
    [$event, $sessions] = makeScheduledEvent();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('events.event-sessions.store', $event), [
            'name' => 'Manual session',
            'description' => 'Manually added',
            'session_date' => '2026-10-08',
            'start_time' => '09:00',
            'end_time' => '16:00',
        ])
        ->assertRedirect(route('events.show', $event));

    $manualSession = $event->fresh()->sessions()->where('name', 'Manual session')->firstOrFail();
    expect($manualSession->ends_at->toDateTimeString())->toBe('2026-10-08 16:00:00')
        ->and($manualSession->attendance_opens_at->toDateTimeString())->toBe('2026-10-08 09:00:00')
        ->and($manualSession->attendance_closes_at->toDateTimeString())->toBe('2026-10-08 23:59:00');

    $this->actingAs($admin)
        ->put(route('events.event-sessions.update', [$event, $sessions[0]]), [
            'name' => $sessions[0]->name,
            'description' => $sessions[0]->description,
            'session_date' => '2026-10-06',
            'start_time' => '10:00',
            'end_time' => '16:00',
        ])
        ->assertRedirect(route('events.event-sessions.show', [$event, $sessions[0]]));

    expect($sessions[0]->fresh()->ends_at->toDateTimeString())->toBe('2026-10-06 16:00:00')
        ->and($sessions[0]->fresh()->attendance_opens_at->toDateTimeString())->toBe('2026-10-06 10:00:00')
        ->and($sessions[0]->fresh()->attendance_closes_at->toDateTimeString())->toBe('2026-10-06 23:59:00');
});

it('adds only missing daily sessions when the event range expands', function () {
    [$event, $sessions] = makeScheduledEvent();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('events.update', $event), scheduleUpdatePayload(
            $event,
            '2026-10-13 08:00:00',
            '2026-10-15 12:59:00',
            2
        ))
        ->assertRedirect(route('events.show', $event));

    $updatedSessions = $event->fresh()->sessions()->orderBy('starts_at')->get();
    expect($updatedSessions)->toHaveCount(3)
        ->and($updatedSessions->pluck('id')->take(2)->all())->toBe([$sessions[0]->id, $sessions[1]->id])
        ->and($updatedSessions->pluck('starts_at')->map(fn ($time) => $time->toDateString())->all())
        ->toBe(['2026-10-13', '2026-10-14', '2026-10-15'])
        ->and($updatedSessions[2]->name)->toBe('Session 3');
});

it('removes an empty session that falls outside a shortened event range', function () {
    [$event, $sessions] = makeScheduledEvent('2026-10-06 08:00:00', '2026-10-08 12:59:00', 3);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('events.update', $event), scheduleUpdatePayload(
            $event,
            '2026-10-06 08:00:00',
            '2026-10-07 12:59:00',
            3
        ))
        ->assertRedirect(route('events.show', $event));

    expect($event->fresh()->sessions()->pluck('id')->all())->toBe([$sessions[0]->id, $sessions[1]->id])
        ->and(EventSession::find($sessions[2]->id))->toBeNull()
        ->and($sessions[0]->fresh()->name)->toBe('Session 1');
});

it('rejects shortening past an attended session without saving event or session changes', function () {
    [$event, $sessions] = makeScheduledEvent('2026-10-06 08:00:00', '2026-10-08 12:59:00', 3);
    $attendance = recordSessionAttendance($sessions[2]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('events.edit', $event))
        ->put(route('events.update', $event), scheduleUpdatePayload(
            $event,
            '2026-10-06 08:00:00',
            '2026-10-07 12:59:00',
            3
        ))
        ->assertRedirect(route('events.edit', $event))
        ->assertSessionHasErrors('schedule');

    expect($event->fresh()->ends_at->toDateTimeString())->toBe('2026-10-08 12:59:00')
        ->and($sessions[2]->fresh()->starts_at->toDateTimeString())->toBe('2026-10-08 08:00:00')
        ->and($attendance->fresh()->session_id)->toBe($sessions[2]->id)
        ->and($event->fresh()->sessions()->count())->toBe(3);
});

it('rejects direct deletion of a session with attendance', function () {
    [$event, $sessions] = makeScheduledEvent();
    $attendance = recordSessionAttendance($sessions[0]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('events.event-sessions.show', [$event, $sessions[0]]))
        ->delete(route('events.event-sessions.destroy', [$event, $sessions[0]]))
        ->assertRedirect(route('events.event-sessions.show', [$event, $sessions[0]]))
        ->assertSessionHasErrors('session');

    expect($sessions[0]->fresh())->not->toBeNull()
        ->and($attendance->fresh()->session_id)->toBe($sessions[0]->id);
});

it('allows direct deletion of a session without attendance', function () {
    [$event, $sessions] = makeScheduledEvent();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->delete(route('events.event-sessions.destroy', [$event, $sessions[0]]))
        ->assertRedirect(route('events.show', $event));

    expect(EventSession::find($sessions[0]->id))->toBeNull();
});

it('rejects deleting an event when any of its sessions has attendance', function () {
    [$event, $sessions] = makeScheduledEvent();
    $attendance = recordSessionAttendance($sessions[1]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('events.index'))
        ->delete(route('events.destroy', $event))
        ->assertRedirect(route('events.index'))
        ->assertSessionHasErrors('event');

    expect($event->fresh())->not->toBeNull()
        ->and($sessions[0]->fresh())->not->toBeNull()
        ->and($sessions[1]->fresh())->not->toBeNull()
        ->and($attendance->fresh()->session_id)->toBe($sessions[1]->id);
});

it('allows deleting an event when no session has attendance', function () {
    [$event, $sessions] = makeScheduledEvent();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->delete(route('events.destroy', $event))
        ->assertRedirect(route('events.index'));

    expect(Event::find($event->id))->toBeNull()
        ->and(EventSession::whereIn('id', $sessions->pluck('id'))->count())->toBe(0);
});

it('prevents marking an event completed before its scheduled end', function () {
    [$event] = makeScheduledEvent();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('events.edit', $event))
        ->put(route('events.update', $event), [
            'name' => $event->name,
            'starts_at' => $event->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $event->ends_at->format('Y-m-d H:i:s'),
            'status' => 'completed',
        ])
        ->assertRedirect(route('events.edit', $event))
        ->assertSessionHasErrors('status');

    expect($event->fresh()->status)->toBe('published');
});
