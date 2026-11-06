<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Support\SessionScheduleConflictChecker;
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
use Illuminate\Validation\ValidationException;
use App\Support\MalaysianPhoneNumber;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventSessionController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorizeEvent($event);
        $event->load(['sessions' => fn ($query) => $query->withCount('attendances')]);

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

    public function show(Request $request, Event $event, EventSession $eventSession): View|JsonResponse
    {
        $this->authorizeEvent($event);
        abort_unless($eventSession->event_id === $event->id, 404);

        $attendanceRefresh = $request->expectsJson() && $request->boolean('attendance_refresh');

        $eventSession->load([
            'event',
            'createdBy',
            'updatedBy',
            'attendances',
        ]);

        $attendanceUrl = null;
        $attendanceQrSvg = null;
        if (!$attendanceRefresh &&
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

        $registrationByEmail = [];
        foreach ($registrations as $registration) {
            foreach ([$registration->guest_email, $registration->user?->email] as $email) {
                $email = strtolower(trim($email ?? ''));
                if ($email !== '' && !isset($registrationByEmail[$email])) {
                    $registrationByEmail[$email] = $registration;
                }
            }
        }

        // Pre-registration is event-level, so only actual check-ins belong in
        // this session report. No session reservation exists to identify no-shows.
        $registrationAttendanceRows = $eventSession->attendances
            ->sortByDesc(fn ($attendance) => $attendance->clock_in_at ?? $attendance->created_at)
            ->map(function ($attendance) use ($registrationByEmail) {
                $email = strtolower(trim($attendance->email ?? ''));
                $registration = $email !== '' ? ($registrationByEmail[$email] ?? null) : null;

                return [
                    'id' => $attendance->id,
                    'route_key' => $attendance->getRouteKey(),
                    'name' => $attendance->full_name,
                    'email' => $attendance->email,
                    'phone' => $attendance->phone,
                    'position' => $attendance->position,
                    'organisation' => $attendance->unit,
                    'registration_status' => $registration ? ucfirst($registration->status) : 'Not pre-registered',
                    'attended_at' => $attendance->clock_in_at ?? $attendance->created_at,
                    'signature' => $attendance->signature,
                ];
            })
            ->values();

        $registeredCount = $registrations->count();
        $attendedCount = $registrationAttendanceRows->count();
        $preRegisteredAttendedCount = $registrationAttendanceRows
            ->filter(fn (array $row) => $row['registration_status'] !== 'Not pre-registered')
            ->count();
        $notPreRegisteredCount = $attendedCount - $preRegisteredAttendedCount;

        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', '');
        $validStatusFilters = [
            'pre_registered', 'not_pre_registered', 'pending', 'approved', 'cancelled',
            'rejected',
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
                'query' => $request->except('attendance_refresh'),
            ]
        );

        if ($attendanceRefresh) {
            return response()->json([
                'html' => view('event-sessions.partials.attendance-table-content', [
                    'event' => $event,
                    'eventSession' => $eventSession,
                    'registrationAttendanceRows' => $registrationAttendanceRows,
                    'search' => $search,
                    'statusFilter' => $statusFilter,
                ])->render(),
                'counts' => [
                    'attended' => $attendedCount,
                    'pre_registered' => $preRegisteredAttendedCount,
                    'not_pre_registered' => $notPreRegisteredCount,
                ],
            ])->header('Cache-Control', 'private, no-store');
        }

        return view('event-sessions.show', compact(
            'event',
            'eventSession',
            'attendanceUrl',
            'attendanceQrSvg',
            'registrationAttendanceRows',
            'registeredCount',
            'attendedCount',
            'preRegisteredAttendedCount',
            'notPreRegisteredCount',
            'search',
            'statusFilter'
        ));
    }

    public function updateAttendanceDetails(
        Request $request,
        Event $event,
        EventSession $eventSession,
        Attendance $attendance
    ): RedirectResponse {
        $this->authorizeEvent($event);
        abort_unless($eventSession->event_id === $event->id, 404);
        abort_unless((int) $attendance->session_id === (int) $eventSession->id, 404);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'regex:/^\\+?[0-9().\\s-]+$/'],
            'position' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $phone = MalaysianPhoneNumber::canonicalize($validated['phone']);
        if (strlen($phone) < 7 || strlen($phone) > 15) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a phone number containing 7 to 15 digits.',
            ]);
        }

        DB::transaction(function () use ($event, $eventSession, $attendance, $validated, $email, $phone): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $record = Attendance::query()
                ->where('session_id', $eventSession->id)
                ->whereKey($attendance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $emailExists = Attendance::query()
                ->where('session_id', $eventSession->id)
                ->where('id', '!=', $record->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->exists();

            $eventAttendances = $event->sessions()
                ->with('attendances:id,session_id,phone,email')
                ->get()
                ->flatMap(fn (EventSession $session) => $session->attendances)
                ->reject(fn (Attendance $candidate) => (int) $candidate->id === (int) $record->id)
                ->filter(fn (Attendance $candidate) => MalaysianPhoneNumber::canonicalize($candidate->phone ?? '') === $phone);

            $phoneUsedByDifferentEmail = $eventAttendances->contains(
                fn (Attendance $candidate) => strtolower(trim($candidate->email ?? '')) !== $email
            );
            $phoneUsedInThisSession = $eventAttendances->contains(
                fn (Attendance $candidate) => (int) $candidate->session_id === (int) $eventSession->id
            );
            $duplicateErrors = [];
            if ($emailExists) {
                $duplicateErrors['email'] = 'This email is already used for this session.';
            }
            if ($phoneUsedByDifferentEmail || $phoneUsedInThisSession) {
                $duplicateErrors['phone'] = $phoneUsedByDifferentEmail
                    ? 'This phone number is already linked to a different email for this event.'
                    : 'This phone number has already been used for this session.';
            }
            if ($duplicateErrors !== []) {
                throw ValidationException::withMessages($duplicateErrors);
            }

            $record->forceFill([
                'full_name' => trim($validated['full_name']),
                'email' => $email,
                'phone' => $phone,
                'position' => isset($validated['position']) ? trim($validated['position']) : null,
                'unit' => isset($validated['unit']) ? trim($validated['unit']) : null,
            ]);
            $changedFields = array_keys($record->getDirty());
            if ($changedFields === []) {
                return;
            }
            sort($changedFields);

            $record->save();
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'updated',
                'auditable_type' => Attendance::class,
                'auditable_id' => $record->id,
                'description' => 'Attendance details corrected.',
                'new_values' => ['fields_changed' => $changedFields],
            ]);
        });

        return redirect()
            ->route('events.event-sessions.show', [$event, $eventSession] + $this->attendanceReturnParameters($request))
            ->with('success', 'Attendance details updated.');
    }

    public function editAttendanceDetails(Request $request, Event $event, EventSession $eventSession, Attendance $attendance): View
    {
        $this->authorizeEvent($event);
        abort_unless((int) $eventSession->event_id === (int) $event->id, 404);
        abort_unless((int) $attendance->session_id === (int) $eventSession->id, 404);

        $returnParameters = $this->attendanceReturnParameters($request);

        return view('event-sessions.attendance-edit', compact('event', 'eventSession', 'attendance', 'returnParameters'));
    }

    private function attendanceReturnParameters(Request $request): array
    {
        $search = $request->input('_return_search', $request->query('search', ''));
        $search = is_string($search) ? trim(substr($search, 0, 100)) : '';
        $status = $request->input('_return_status', $request->query('status', ''));
        $allowedStatuses = ['pre_registered', 'not_pre_registered', 'pending', 'approved', 'cancelled', 'rejected'];
        $status = is_string($status) && in_array($status, $allowedStatuses, true) ? $status : '';
        $page = filter_var($request->input('_return_page', $request->query('page')), FILTER_VALIDATE_INT);

        return array_filter([
            'search' => $search !== '' ? $search : null,
            'status' => $status !== '' ? $status : null,
            'page' => $page !== false && $page > 1 ? $page : null,
        ], fn ($value) => $value !== null);
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
        $schedule = $this->validatedSessionSchedule($request, $event, $eventSession);
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

        DB::transaction(function () use ($event, $eventSession): void {
            $lockedEvent = Event::whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $session = EventSession::whereKey($eventSession->id)
                ->lockForUpdate()
                ->firstOrFail();
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'auditable_type' => EventSession::class,
                'auditable_id' => $session->id,
                'description' => 'Event session deleted.',
                'old_values' => $session->toArray(),
            ]);

            $session->delete();
        });

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Session deleted. Its check-ins are preserved and available if you restore it.');
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
    private function validatedSessionSchedule(
        Request $request,
        Event $event,
        ?EventSession $exceptSession = null
    ): array|RedirectResponse
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

        if ($event->starts_at === null || $event->ends_at === null) {
            return back()
                ->withInput()
                ->withErrors(['starts_at' => 'The parent event must have a valid start and end schedule before sessions can be added.']);
        }

        if ($startsAt->lt($event->starts_at)) {
            return back()
                ->withInput()
                ->withErrors(['starts_at' => 'The session must start at or after the parent event starts.']);
        }

        if ($endsAt->gt($event->ends_at)) {
            return back()
                ->withInput()
                ->withErrors(['ends_at' => 'The session must end at or before the parent event ends.']);
        }

        if ($event->location_id !== null) {
            $conflictMessage = app(SessionScheduleConflictChecker::class)->conflictMessage(
                (int) $event->location_id,
                [[
                    'name' => $request->string('name')->toString() ?: 'This session',
                    'event_name' => $event->name,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]],
                null,
                $exceptSession?->id
            );

            if ($conflictMessage !== null) {
                return back()->withInput()->withErrors(['starts_at' => $conflictMessage]);
            }
        }

        return [$startsAt, $endsAt];
    }
}
