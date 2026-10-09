<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeOpenAttendanceSession(): array
{
    $event = Event::create([
        'name' => 'Offline sync event',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => 'published',
        'pin' => 'ABCD',
    ]);

    $session = EventSession::create([
        'event_id' => $event->id,
        'name' => 'Open session',
        'starts_at' => now()->subMinutes(10),
        'ends_at' => now()->addMinutes(50),
        'attendance_opens_at' => now()->subMinutes(10),
        'attendance_closes_at' => now()->addMinutes(50),
    ]);

    return [$event, $session];
}

it('accepts an offline synchronization and records it against the selected session', function () {
    [$event, $session] = makeOpenAttendanceSession();
    $submissionId = '86de5837-94c7-488c-b6aa-6361952b2cab';

    $this->withSession(['attendance_event_id' => $event->id])
        ->withHeader('Accept', 'application/json')
        ->withHeader('X-Attendance-Sync', '1')
        ->postJson(route('attendance.store', $event), [
            'session_id' => $session->id,
            'full_name' => 'Offline Attendee',
            'email' => 'offline@example.test',
            'phone' => '60125902441',
            'offline_submission_id' => $submissionId,
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'synchronized')
        ->assertJsonPath('created', true);

    $attendance = Attendance::where('offline_submission_id', $submissionId)->sole();
    expect($attendance->session_id)->toBe($session->id)
        ->and($attendance->email)->toBe('offline@example.test')
        ->and(Attendance::count())->toBe(1);
});

it('returns the existing record when the same offline submission is retried', function () {
    [$event, $session] = makeOpenAttendanceSession();
    $submissionId = '420be7f5-0e2e-4a1e-954f-b31b34d8201a';
    $payload = [
        'session_id' => $session->id,
        'full_name' => 'Retry Attendee',
        'email' => 'retry@example.test',
        'phone' => '60125902441',
        'offline_submission_id' => $submissionId,
    ];

    $this->withSession(['attendance_event_id' => $event->id])
        ->withHeader('X-Attendance-Sync', '1')
        ->postJson(route('attendance.store', $event), $payload)
        ->assertCreated()
        ->assertJsonPath('created', true);

    $this->withSession(['attendance_event_id' => $event->id])
        ->withHeader('X-Attendance-Sync', '1')
        ->postJson(route('attendance.store', $event), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'synchronized')
        ->assertJsonPath('created', false);

    expect(Attendance::where('offline_submission_id', $submissionId)->count())->toBe(1)
        ->and(Attendance::count())->toBe(1);
});

it('does not report invalid queued attendance as synchronized', function () {
    [$event, $session] = makeOpenAttendanceSession();

    $this->withSession(['attendance_event_id' => $event->id])
        ->withHeader('X-Attendance-Sync', '1')
        ->postJson(route('attendance.store', $event), [
            'session_id' => $session->id,
            'full_name' => 'Invalid Attendee',
            'email' => 'not-an-email',
            'phone' => '60125902441',
            'offline_submission_id' => 'cc6da502-17e6-4c65-a078-1541d5b89332',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(Attendance::count())->toBe(0);
});
