<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventType;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $sortOptions = [
            'created_desc' => ['created_at', 'desc'],
            'start_asc' => ['starts_at', 'asc'],
            'start_desc' => ['starts_at', 'desc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
        ];
        $searchValue = $request->query('search', '');
        $search = is_string($searchValue) ? trim(substr($searchValue, 0, 100)) : '';
        $sortValue = $request->query('sort', 'created_desc');
        $sort = is_string($sortValue) ? $sortValue : 'created_desc';
        if (!array_key_exists($sort, $sortOptions)) {
            $sort = 'created_desc';
        }

        $query = Event::with([
            'eventType',
            'location',
            'organizer',
        ])->withCount(['registrations', 'attendances as attendees_count']);

        if (auth()->user()->role !== 'admin') {
            $query->where('organizer_id', auth()->id());
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('eventType', fn ($related) => $related->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('location', fn ($related) => $related->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('organizer', fn ($related) => $related->where('name', 'like', '%' . $search . '%'));
            });
        }

        [$sortColumn, $sortDirection] = $sortOptions[$sort];
        $events = $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('events.index', compact('events', 'search', 'sort'));
    }

    public function exportAttendance(Event $event): StreamedResponse
    {
        $this->authorizeEvent($event);

        $filename = 'event-' . $event->id . '-attendance.csv';

        return response()->streamDownload(function () use ($event) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Event', 'Session', 'Session Starts', 'Name', 'Email', 'Phone',
                'Position', 'Organisation / Unit', 'Pre-registration status',
                'Attendance Status', 'Registered At', 'Attended At',
            ]);

            $sessions = $event->sessions()->orderBy('starts_at')->get();
            $registrations = $event->registrations()->with('user')->orderBy('registered_at')->get();
            $attendances = $event->attendances()
                ->with('session')
                ->orderBy('attendances.created_at')
                ->get();
            $attendanceGroups = $attendances->groupBy(fn ($attendance) =>
                $attendance->session_id . '|' . strtolower(trim($attendance->email ?? ''))
            );
            $matchedAttendanceIds = [];

            $writeRow = function (array $row) use ($output) {
                fputcsv($output, array_map(function ($value) {
                    $value = (string) ($value ?? '');

                    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
                }, $row));
            };

            foreach ($registrations as $registration) {
                $email = strtolower(trim($registration->guest_email ?? $registration->user?->email ?? ''));
                $registrationName = $registration->guest_name ?? $registration->user?->name;
                $sessionsToReport = $sessions->isNotEmpty() ? $sessions : collect([null]);

                foreach ($sessionsToReport as $session) {
                    $matches = $session && $email !== ''
                        ? $attendanceGroups->get($session->id . '|' . $email, collect())
                        : collect();
                    $attendanceRecords = $matches->isNotEmpty() ? $matches : collect([null]);

                    foreach ($attendanceRecords as $attendance) {
                        if ($attendance) {
                            $matchedAttendanceIds[$attendance->id] = true;
                        }

                        $writeRow([
                            $event->name,
                            $session?->name,
                            $session?->starts_at?->format('Y-m-d H:i:s'),
                            $registrationName,
                            $email,
                            $attendance?->phone ?? $registration->guest_phone,
                            $attendance?->position ?? $registration->position,
                            $registration->organisation ?? $attendance?->unit,
                            ucfirst($registration->status),
                            $attendance ? 'Attended' : 'Not attended',
                            $registration->registered_at?->format('Y-m-d H:i:s'),
                            $attendance?->created_at?->format('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            foreach ($attendances as $attendance) {
                if (isset($matchedAttendanceIds[$attendance->id])) {
                    continue;
                }

                $writeRow([
                    $event->name,
                    $attendance->session?->name,
                    $attendance->session?->starts_at?->format('Y-m-d H:i:s'),
                    $attendance->full_name,
                    $attendance->email,
                    $attendance->phone,
                    $attendance->position,
                    $attendance->unit,
                    'Not pre-registered',
                    'Attended',
                    null,
                    $attendance->created_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): View
    {
        $eventTypes = EventType::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        return view('events.create', compact(
            'eventTypes',
            'locations'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],

            'event_type_id' => ['nullable', 'exists:event_types,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
        ]);

        /*
         * The person creating the Event automatically becomes
         * its Organizer.
         *
         * Status starts as Draft. The Organizer/Admin can publish
         * the Event later.
         */
        $validated['organizer_id'] = auth()->id();
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();
        $validated['status'] = 'draft';
        $validated['pin'] = $this->generateEventPin();

        $event = Event::create($validated);

        /*
         * Automatically create one Session per calendar day.
         *
         * One-day Event:
         *   Session 1
         *
         * Multi-day Event:
         *   Session 1
         *   Session 2
         *   Session 3
         *   ...
         */
        $eventStart = Carbon::parse($validated['starts_at']);
        $eventEnd = Carbon::parse($validated['ends_at']);

        foreach ($this->sessionScheduleFor($eventStart, $eventEnd) as $index => $schedule) {
            $event->sessions()->create([
                'name' => 'Session ' . ($index + 1),
                'description' => null,
                'starts_at' => $schedule['starts_at'],
                'ends_at' => $schedule['ends_at'],
                'attendance_opens_at' => $schedule['starts_at'],
                'attendance_closes_at' => $schedule['attendance_closes_at'],
                'pin' => null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }

        $this->createAuditLog(
            'created',
            $event,
            'Event created.',
            null,
            $event->toArray()
        );

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Event created successfully.');
    }

    public function show(Event $event): View
    {
        $this->authorizeEvent($event);

        $event->load([
            'eventType',
            'location',
            'organizer',
            'createdBy',
            'updatedBy',
            'sessions',
            'registrations',
        ]);
        $event->loadCount('attendances');
        $event->sessions->loadCount('attendances');

        return view('events.show', compact('event'));
    }

    public function edit(Event $event): View
    {
        $this->authorizeEvent($event);
        $event->loadCount('sessions');

        $eventTypes = EventType::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        return view('events.edit', compact(
            'event',
            'eventTypes',
            'locations'
        ));
    }

    public function update(
        Request $request,
        Event $event
    ): RedirectResponse {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],

            'event_type_id' => ['nullable', 'exists:event_types,id'],
            'location_id' => ['nullable', 'exists:locations,id'],

            'status' => [
                'required',
                'in:draft,published,cancelled,completed',
            ],
        ]);

        $newStartsAt = Carbon::parse($validated['starts_at']);
        $newEndsAt = Carbon::parse($validated['ends_at']);

        $scheduleChanged = $event->starts_at?->format('Y-m-d H:i') !== $newStartsAt->format('Y-m-d H:i')
            || $event->ends_at?->format('Y-m-d H:i') !== $newEndsAt->format('Y-m-d H:i');

        if ($scheduleChanged && !$request->boolean('sync_sessions')) {
            return back()->withInput()->withErrors([
                'schedule' => 'Confirm the session schedule update before saving these event dates.',
            ]);
        }

        if ($validated['status'] === 'completed' && $newEndsAt->isFuture()) {
            return back()->withInput()->withErrors([
                'status' => 'This event cannot be marked completed before its scheduled end time. Change the event end time first if the schedule has changed.',
            ]);
        }

        $oldValues = $event->only([
            'name',
            'description',
            'starts_at',
            'ends_at',
            'event_type_id',
            'location_id',
            'organizer_id',
            'status',
        ]);

        /*
         * Organizer is NOT changed through the normal Event edit form.
         * The original responsible organizer remains attached.
         */
        $validated['updated_by'] = auth()->id();

        $updated = DB::transaction(function () use (
            $event,
            $request,
            $validated,
            $oldValues,
            $scheduleChanged,
            $newStartsAt,
            $newEndsAt
        ): string {
            $sessions = $event->sessions()
                ->orderBy('starts_at')
                ->orderBy('id')
                ->withCount('attendances')
                ->lockForUpdate()
                ->get();
            if (
                $scheduleChanged &&
                (int) $request->input('expected_sessions', -1) !== $sessions->count()
            ) {
                return 'sessions_changed';
            }
            $sessionsToRemove = collect();
            $sessionsToUpdate = collect();
            $offsetSeconds = $scheduleChanged
                ? ($event->starts_at ? $newStartsAt->getTimestamp() - $event->starts_at->getTimestamp() : 0)
                : 0;
            $firstEventDate = $newStartsAt->toDateString();
            $lastEventDate = $newEndsAt->toDateString();

            if ($scheduleChanged) {
                foreach ($sessions as $session) {
                    $shiftedStart = $session->starts_at?->copy()->addSeconds($offsetSeconds);
                    $outsideRange = $shiftedStart && (
                        $shiftedStart->toDateString() < $firstEventDate ||
                        $shiftedStart->toDateString() > $lastEventDate
                    );

                    if ($outsideRange && $session->attendances_count > 0) {
                        return 'attendance_conflict';
                    }

                    if ($outsideRange) {
                        $sessionsToRemove->push($session);
                    } else {
                        $sessionsToUpdate->push($session);
                    }
                }
            } else {
                $sessionsToUpdate = $sessions;
            }

            $event->update($validated);

            if ($scheduleChanged) {
                foreach ($sessionsToUpdate as $session) {
                    $oldSessionValues = $session->toArray();
                    $values = ['updated_by' => auth()->id()];

                    foreach ([
                        'starts_at',
                        'ends_at',
                        'attendance_opens_at',
                    ] as $column) {
                        $values[$column] = $session->{$column}?->copy()->addSeconds($offsetSeconds);
                    }
                    $values['attendance_closes_at'] = $session->starts_at
                        ? $session->starts_at->copy()->addSeconds($offsetSeconds)->setTime(23, 59)
                        : null;

                    $session->update($values);

                    AuditLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'updated',
                        'auditable_type' => EventSession::class,
                        'auditable_id' => $session->id,
                        'description' => 'Session rescheduled with the event.',
                        'old_values' => $oldSessionValues,
                        'new_values' => $session->toArray(),
                    ]);
                }

                $existingSessionDates = $sessionsToUpdate
                    ->map(fn (EventSession $session) => $session->starts_at?->toDateString())
                    ->filter()
                    ->unique()
                    ->all();

                foreach ($this->sessionScheduleFor($newStartsAt, $newEndsAt) as $index => $schedule) {
                    if (in_array($schedule['starts_at']->toDateString(), $existingSessionDates, true)) {
                        continue;
                    }

                    $newSession = $event->sessions()->create([
                        'name' => 'Session ' . ($index + 1),
                        'description' => null,
                        'starts_at' => $schedule['starts_at'],
                        'ends_at' => $schedule['ends_at'],
                        'attendance_opens_at' => $schedule['starts_at'],
                        'attendance_closes_at' => $schedule['attendance_closes_at'],
                        'pin' => null,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);

                    AuditLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'created',
                        'auditable_type' => EventSession::class,
                        'auditable_id' => $newSession->id,
                        'description' => 'Session created for an added event date.',
                        'new_values' => $newSession->toArray(),
                    ]);
                }

                foreach ($sessionsToRemove as $session) {
                    AuditLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'deleted',
                        'auditable_type' => EventSession::class,
                        'auditable_id' => $session->id,
                        'description' => 'Empty session removed outside the updated event date range.',
                        'old_values' => $session->toArray(),
                    ]);
                    $session->delete();
                }
            }

            $this->createAuditLog(
                'updated',
                $event,
                $scheduleChanged ? 'Event and session schedule updated.' : 'Event updated.',
                $oldValues,
                $event->only(array_keys($oldValues))
            );

            return 'updated';
        });

        if ($updated === 'sessions_changed') {
            return back()->withInput()->withErrors([
                'schedule' => 'The session list changed while this form was open. Reload the event and confirm the new session changes.',
            ]);
        }

        if ($updated === 'attendance_conflict') {
            return back()->withInput()->withErrors([
                'schedule' => 'The date range cannot be shortened because a session outside the new range has attendance records.',
            ]);
        }

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Event updated successfully.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorizeEvent($event);

        $deleted = DB::transaction(function () use ($event): bool {
            $lockedEvent = Event::whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $sessions = $lockedEvent->sessions()
                ->select('event_sessions.id')
                ->withCount('attendances')
                ->lockForUpdate()
                ->get();

            foreach ($sessions as $session) {
                if ((int) $session->attendances_count > 0) {
                    return false;
                }
            }

            $oldValues = $lockedEvent->toArray();
            $this->createAuditLog(
                'deleted',
                $lockedEvent,
                'Event deleted.',
                $oldValues
            );

            $lockedEvent->delete();

            return true;
        });

        if (!$deleted) {
            return back()->withErrors([
                'event' => 'This event cannot be deleted because attendance records exist in one or more sessions.',
            ]);
        }

        return redirect()
            ->route('events.index')
            ->with('success', 'Event deleted successfully.');
    }

    private function generateEventPin(): string
    {
        /*
         * Human-friendly characters.
         *
         * Excluded:
         * 0 / O
         * 1 / I / L
         */
        $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $pin = '';

            for ($i = 0; $i < 4; $i++) {
                $pin .= $characters[
                random_int(0, strlen($characters) - 1)
                ];
            }
        } while (Event::where('pin', $pin)->exists());

        return $pin;
    }

    private function sessionScheduleFor(Carbon $eventStart, Carbon $eventEnd): array
    {
        $eventStart = $eventStart->copy();
        $eventEnd = $eventEnd->copy();
        $schedule = [];
        $currentDate = $eventStart->copy()->startOfDay();
        $lastSessionDate = $eventEnd->copy()->startOfDay();

        while ($currentDate->lte($lastSessionDate)) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();
            $sessionStart = $currentDate->copy()->setTime(
                $eventStart->hour,
                $eventStart->minute,
                $eventStart->second
            );
            $sessionEnd = $currentDate->copy()->setTime(
                $eventEnd->hour,
                $eventEnd->minute,
                $eventEnd->second
            );

            if ($sessionEnd->lte($sessionStart)) {
                if ($currentDate->isSameDay($lastSessionDate)) {
                    $sessionEnd = $eventEnd->copy();
                    $sessionStart = $eventEnd->copy()->subHour();

                    if ($sessionStart->lt($dayStart)) {
                        $sessionStart = $dayStart;
                    }
                } else {
                    $sessionEnd = $sessionStart->copy()->addHour();

                    if ($sessionEnd->gt($dayEnd)) {
                        $sessionEnd = $dayEnd;
                    }

                    if ($sessionEnd->gt($eventEnd)) {
                        $sessionEnd = $eventEnd->copy();
                    }
                }
            } else {
                if ($sessionEnd->gt($dayEnd)) {
                    $sessionEnd = $dayEnd;
                }

                if ($sessionEnd->gt($eventEnd)) {
                    $sessionEnd = $eventEnd->copy();
                }
            }

            $schedule[] = [
                'starts_at' => $sessionStart,
                'ends_at' => $sessionEnd,
                'attendance_closes_at' => $currentDate->copy()->setTime(23, 59),
            ];

            $currentDate->addDay();
        }

        return $schedule;
    }

    private function createAuditLog(
        string $action,
        Event $event,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => Event::class,
            'auditable_id' => $event->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
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
