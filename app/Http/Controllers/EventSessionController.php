<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventSessionController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorizeEvent($event);
        $event->load('sessions');

        return view('event-sessions.index', compact('event'));
    }

    public function create(Event $event): View
    {
        $this->authorizeEvent($event);
        return view('event-sessions.create', compact('event'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($event);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $startsAt = Carbon::parse(
            $validated['session_date'] . ' ' . $validated['start_time']
        );

        $endsAt = Carbon::parse(
            $validated['session_date'] . ' ' . $validated['end_time']
        );

        $session = $event->sessions()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,

            // Default attendance window for now.
            'attendance_opens_at' => $startsAt,
            'attendance_closes_at' => $startsAt->copy()->setTime(23, 59),

            // Session PIN is no longer used as the public access mechanism.
            'pin' => null,

            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'auditable_type' => EventSession::class,
            'auditable_id' => $session->id,
            'description' => 'Event session created.',
            'new_values' => $session->toArray(),
        ]);

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Session created successfully.');
    }

    public function show(Request $request, Event $event, EventSession $eventSession): View
    {
        $this->authorizeEvent($event);
        abort_unless($eventSession->event_id === $event->id, 404);

        $eventSession->load([
            'event',
            'createdBy',
            'updatedBy',
            'attendances',
        ]);

        $registrations = Registration::with('user')
            ->where('event_id', $event->id)
            ->latest('registered_at')
            ->get();

        $attendanceEmails = $eventSession->attendances
            ->filter(fn ($attendance) => filled($attendance->email))
            ->keyBy(fn ($attendance) => strtolower(trim($attendance->email)));
        $registeredEmails = collect();
        $registrationAttendanceRows = $registrations->map(function ($registration) use ($attendanceEmails, $eventSession, $registeredEmails) {
            $email = strtolower(trim($registration->guest_email ?? $registration->user?->email ?? ''));
            if ($email !== '') {
                $registeredEmails->put($email, true);
            }

            $attendance = $email !== '' ? $attendanceEmails->get($email) : null;
            return [
                'name' => $registration->guest_name ?? $registration->user?->name,
                'email' => $email,
                'phone' => $attendance?->phone ?? $registration->guest_phone,
                'organisation' => $registration->organisation ?? $attendance?->unit,
                'registration_status' => ucfirst($registration->status),
                'attendance_status' => $attendance ? 'Attended' : 'Not attended',
                'attended_at' => $attendance?->created_at,
            ];
        });

        $unregisteredAttendanceRows = $eventSession->attendances
            ->filter(function ($attendance) use ($registeredEmails) {
                $email = strtolower(trim($attendance->email ?? ''));

                return $email === '' || !$registeredEmails->has($email);
            })
            ->map(fn ($attendance) => [
                'name' => $attendance->full_name,
                'email' => $attendance->email,
                'phone' => $attendance->phone,
                'organisation' => $attendance->unit,
                'registration_status' => 'Not pre-registered',
                'attendance_status' => 'Attended',
                'attended_at' => $attendance->created_at,
            ]);

        $registrationAttendanceRows = $registrationAttendanceRows
            ->concat($unregisteredAttendanceRows)
            ->values();

        $registeredCount = $registrations->count();
        $attendedRegisteredCount = $registrationAttendanceRows
            ->filter(fn (array $row) => $row['registration_status'] !== 'Not pre-registered'
                && $row['attendance_status'] === 'Attended')
            ->pluck('email')
            ->filter()
            ->unique()
            ->count();
        $notAttendedCount = max(0, $registeredCount - $attendedRegisteredCount);
        $attendedCount = $attendedRegisteredCount;
        $attendanceRate = $registeredCount > 0
            ? round(($attendedRegisteredCount / $registeredCount) * 100)
            : 0;

        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', '');
        $validStatusFilters = [
            'pre_registered', 'not_pre_registered', 'pending', 'approved', 'cancelled',
            'rejected', 'attended', 'not_attended',
        ];
        if (!in_array($statusFilter, $validStatusFilters, true)) {
            $statusFilter = '';
        }

        $filteredRows = $registrationAttendanceRows->filter(function (array $row) use ($search, $statusFilter): bool {
            if ($search !== '') {
                $searchable = strtolower(implode(' ', [
                    $row['name'] ?? '',
                    $row['email'] ?? '',
                    $row['phone'] ?? '',
                    $row['organisation'] ?? '',
                ]));

                if (!str_contains($searchable, strtolower($search))) {
                    return false;
                }
            }

            return match ($statusFilter) {
                'pre_registered' => $row['registration_status'] !== 'Not pre-registered',
                'not_pre_registered' => $row['registration_status'] === 'Not pre-registered',
                'pending' => strtolower($row['registration_status']) === 'pending',
                'approved' => strtolower($row['registration_status']) === 'approved',
                'cancelled' => strtolower($row['registration_status']) === 'cancelled',
                'rejected' => strtolower($row['registration_status']) === 'rejected',
                'attended' => $row['attendance_status'] === 'Attended',
                'not_attended' => $row['registration_status'] !== 'Not pre-registered'
                    && $row['attendance_status'] === 'Not attended',
                default => true,
            };
        })->values();

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $registrationAttendanceRows = new LengthAwarePaginator(
            $filteredRows->forPage($currentPage, $perPage)->values(),
            $filteredRows->count(),
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        return view('event-sessions.show', compact(
            'event',
            'eventSession',
            'registrationAttendanceRows',
            'registeredCount',
            'attendedCount',
            'notAttendedCount',
            'attendanceRate',
            'search',
            'statusFilter'
        ));
    }

    public function exportAttendance(Event $event, EventSession $eventSession): StreamedResponse
    {
        $this->authorizeEvent($event);
        abort_unless($eventSession->event_id === $event->id, 404);

        $filename = 'event-' . $event->id . '-session-' . $eventSession->id . '-attendance.csv';

        return response()->streamDownload(function () use ($event, $eventSession) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Event', 'Session', 'Session Starts', 'Full Name', 'Email',
                'Phone', 'Position', 'Organisation / Unit', 'Recorded At',
            ]);

            $eventSession->attendances()
                ->orderBy('created_at')
                ->chunk(500, function ($attendances) use ($output, $event, $eventSession) {
                    foreach ($attendances as $attendance) {
                        $row = [
                            $event->name,
                            $eventSession->name,
                            $eventSession->starts_at?->format('Y-m-d H:i:s'),
                            $attendance->full_name,
                            $attendance->email,
                            $attendance->phone,
                            $attendance->position,
                            $attendance->unit,
                            $attendance->created_at?->format('Y-m-d H:i:s'),
                        ];

                        fputcsv($output, array_map(function ($value) {
                            $value = (string) ($value ?? '');

                            return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
                        }, $row));
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function edit(Event $event, EventSession $eventSession): View
    {
        $this->authorizeEvent($event);
        abort_unless($eventSession->event_id === $event->id, 404);

        return view('event-sessions.edit', compact('event', 'eventSession'));
    }

    public function update(

        Request $request,
        Event $event,
        EventSession $eventSession
    ): RedirectResponse {
        abort_unless($eventSession->event_id === $event->id, 404);

        $this->authorizeEvent($event);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $oldValues = $eventSession->toArray();

        $startsAt = Carbon::parse(
            $validated['session_date'] . ' ' . $validated['start_time']
        );

        $endsAt = Carbon::parse(
            $validated['session_date'] . ' ' . $validated['end_time']
        );

        $eventSession->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,

            // Attendance stays open through the end of this calendar day,
            // independently of the physical session end.
            'attendance_opens_at' => $startsAt,
            'attendance_closes_at' => $startsAt->copy()->setTime(23, 59),

            'pin' => null,
            'updated_by' => auth()->id(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'auditable_type' => EventSession::class,
            'auditable_id' => $eventSession->id,
            'description' => 'Event session updated.',
            'old_values' => $oldValues,
            'new_values' => $eventSession->toArray(),
        ]);

        return redirect()
            ->route('events.event-sessions.show', [$event, $eventSession])
            ->with('success', 'Session updated successfully.');
    }

    public function destroy(Event $event, EventSession $eventSession): RedirectResponse
    {
        $this->authorizeEvent($event);
        abort_unless($eventSession->event_id === $event->id, 404);

        $deleted = DB::transaction(function () use ($eventSession): bool {
            $session = EventSession::whereKey($eventSession->id)
                ->lockForUpdate()
                ->firstOrFail();
            $hasAttendance = $session->attendances()->exists();

            if ($hasAttendance) {
                return false;
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'auditable_type' => EventSession::class,
                'auditable_id' => $session->id,
                'description' => 'Event session deleted.',
                'old_values' => $session->toArray(),
            ]);

            $session->delete();

            return true;
        });

        if (!$deleted) {
            return back()->withErrors([
                'session' => 'This session cannot be deleted because attendance records exist.',
            ]);
        }

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Session deleted successfully.');
    }

    private function authorizeEvent(Event $event): void
    {
        if (
            auth()->user()->role !== 'admin' &&
            $event->organizer_id !== auth()->id()
        ) {
            abort(403);
        }
    }
}
