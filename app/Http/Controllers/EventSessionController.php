<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
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
        ]);
        $schedule = $this->validatedSessionSchedule($request, $event);
        if ($schedule instanceof RedirectResponse) {
            return $schedule;
        }
        [$startsAt, $endsAt] = $schedule;

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

        $attendanceUrl = null;
        $attendanceQrSvg = null;
        if (
            in_array($event->status, ['published', 'completed'], true)
            && $eventSession->attendance_opens_at !== null
            && $eventSession->attendance_closes_at !== null
            && now()->between($eventSession->attendance_opens_at, $eventSession->attendance_closes_at)
        ) {
            // Shared session links must be reusable for legitimate attendees during this session.
            // The server-side attendance window and duplicate checks remain authoritative.
            $encryptedPayload = Crypt::encryptString(json_encode([
                'version' => 1,
                'event_id' => (int) $event->id,
                'session_id' => (int) $eventSession->id,
                'expires_at' => $eventSession->attendance_closes_at->timestamp,
            ], JSON_THROW_ON_ERROR));
            $token = rtrim(strtr(base64_encode($encryptedPayload), '+/', '-_'), '=');
            $attendanceUrl = route('attendance.encrypted', ['token' => $token]);
            $attendanceQrSvg = base64_encode((new Writer(
                new ImageRenderer(new RendererStyle(420, 4), new SvgImageBackEnd())
            ))->writeString($attendanceUrl));
        }

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
                'signature' => $attendance?->signature,
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
                'signature' => $attendance->signature,
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
            'attendanceUrl',
            'attendanceQrSvg',
            'registrationAttendanceRows',
            'registeredCount',
            'attendedCount',
            'notAttendedCount',
            'attendanceRate',
            'search',
            'statusFilter'
        ));
    }

    public function liveAttendance(Event $event, EventSession $eventSession): View
    {
        $this->authorizeEvent($event);
        abort_unless($eventSession->event_id === $event->id, 404);

        $data = $this->liveAttendancePayload($event, $eventSession);

        return view('event-sessions.live-attendance', [
            'event' => $event,
            'eventSession' => $eventSession,
            'liveData' => $data,
        ]);
    }

    public function liveAttendanceData(Request $request, Event $event, EventSession $eventSession): JsonResponse
    {
        $this->authorizeEvent($event);
        abort_unless($eventSession->event_id === $event->id, 404);

        return response()
            ->json($this->liveAttendancePayload($event, $eventSession))
            ->header('Cache-Control', 'private, no-store');
    }

    private function liveAttendancePayload(Event $event, EventSession $eventSession): array
    {
        $registrations = Registration::with('user:id,name,email')
            ->where('event_id', $event->id)
            ->latest('registered_at')
            ->get();

        $registrationByEmail = [];
        foreach ($registrations as $registration) {
            foreach ([$registration->guest_email, $registration->user?->email] as $email) {
                $email = strtolower(trim($email ?? ''));
                if ($email !== '' && !isset($registrationByEmail[$email])) {
                    $registrationByEmail[$email] = $registration;
                }
            }
        }

        $attendanceEmails = $eventSession->attendances()->get(['email']);
        $attendedRegistrationIds = [];
        $notPreRegisteredCount = 0;
        foreach ($attendanceEmails as $attendance) {
            $email = strtolower(trim($attendance->email ?? ''));
            $registration = $email !== '' ? ($registrationByEmail[$email] ?? null) : null;
            if ($registration) {
                $attendedRegistrationIds[$registration->id] = true;
            } else {
                $notPreRegisteredCount++;
            }
        }

        $recentCheckIns = $eventSession->attendances()
            ->orderByRaw('COALESCE(clock_in_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(function ($attendance) use ($registrationByEmail) {
                $email = strtolower(trim($attendance->email ?? ''));

                return [
                    'name' => $attendance->full_name,
                    'organisation' => $attendance->unit,
                    'checked_in_at' => ($attendance->clock_in_at ?? $attendance->created_at)?->format('d M Y, h:i A'),
                    'pre_registered' => $email !== '' && isset($registrationByEmail[$email]),
                ];
            });

        $now = now();
        if (!$eventSession->starts_at || !$eventSession->ends_at) {
            $sessionStatus = 'Schedule unavailable';
        } elseif ($now->lt($eventSession->starts_at)) {
            $sessionStatus = 'Upcoming';
        } elseif ($now->lte($eventSession->ends_at)) {
            $sessionStatus = 'In progress';
        } else {
            $sessionStatus = 'Ended';
        }

        if (!in_array($event->status, ['published', 'completed'], true)) {
            $attendanceAvailability = 'Unavailable';
        } elseif (!$eventSession->attendance_opens_at || !$eventSession->attendance_closes_at) {
            $attendanceAvailability = 'Unavailable';
        } elseif ($now->lt($eventSession->attendance_opens_at)) {
            $attendanceAvailability = 'Opens ' . $eventSession->attendance_opens_at->format('d M Y, h:i A');
        } elseif ($now->gt($eventSession->attendance_closes_at)) {
            $attendanceAvailability = 'Closed';
        } else {
            $attendanceAvailability = 'Open';
        }

        return [
            'session_status' => $sessionStatus,
            'attendance_availability' => $attendanceAvailability,
            'checked_in' => $attendanceEmails->count(),
            'pre_registered_checked_in' => count($attendedRegistrationIds),
            'not_pre_registered' => $notPreRegisteredCount,
            'check_ins' => $recentCheckIns->values(),
        ];
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
        ]);

        $oldValues = $eventSession->toArray();
        $schedule = $this->validatedSessionSchedule($request, $event);
        if ($schedule instanceof RedirectResponse) {
            return $schedule;
        }
        [$startsAt, $endsAt] = $schedule;

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

    /** @return array{Carbon, Carbon}|RedirectResponse */
    private function validatedSessionSchedule(Request $request, Event $event): array|RedirectResponse
    {
        $schedule = $request->validate([
            'starts_at' => ['required_without:session_date', 'date'],
            'ends_at' => ['required_without:session_date', 'date', 'after:starts_at'],
            'session_date' => ['required_without:starts_at', 'date'],
            'start_time' => ['required_with:session_date', 'date_format:H:i'],
            'end_time' => ['required_with:session_date', 'date_format:H:i', 'after:start_time'],
        ]);

        if (filled($schedule['starts_at'] ?? null)) {
            $startsAt = Carbon::parse($schedule['starts_at']);
            $endsAt = Carbon::parse($schedule['ends_at']);
        } else {
            $startsAt = Carbon::parse($schedule['session_date'] . ' ' . $schedule['start_time']);
            $endsAt = Carbon::parse($schedule['session_date'] . ' ' . $schedule['end_time']);
        }

        if (
            $event->starts_at === null
            || $event->ends_at === null
            || $startsAt->lt($event->starts_at)
            || $endsAt->gt($event->ends_at)
        ) {
            return back()
                ->withInput()
                ->withErrors(['schedule' => 'The session must fall within the parent event schedule.']);
        }

        return [$startsAt, $endsAt];
    }
}
