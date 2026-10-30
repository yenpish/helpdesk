<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Support\MalaysianPhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;


class PublicAttendanceController extends Controller
{
    public function enterPin(): View
    {
        return view('attendance.pin');
    }

    public function verifyPin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => [
                'required',
                'string',
                'size:4',
                'regex:/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{4}$/i',
            ],
        ]);

        $pin = strtoupper(trim($validated['pin']));

        $event = Event::where('pin', $pin)->first();

        if (!$event) {
            return back()->withErrors([
                'pin' => 'Invalid Attendance PIN.',
            ]);
        }

        if (!$this->eventAllowsAttendance($event)) {
            return back()->withErrors([
                'pin' => 'Attendance is unavailable for this event.',
            ]);
        }

        session([
            'attendance_event_id' => $event->id,
        ]);
        session()->forget('attendance_session_id');

        return redirect()->route('attendance.form', $event);
    }

    public function showForm(Event $event): Response|RedirectResponse
    {
        if (session('attendance_event_id') !== $event->id) {
            return redirect()->route('attendance.pin');
        }

        if (!$this->eventAllowsAttendance($event)) {
            return redirect()->route('attendance.pin')->withErrors([
                'pin' => 'Attendance is unavailable for this event.',
            ]);
        }

        $event->load('location');

        $sessions = $event->sessions()
            ->orderBy('starts_at')
            ->get();

        $boundSessionId = session('attendance_session_id');
        if ($boundSessionId !== null) {
            $sessions = $sessions
                ->where('id', (int) $boundSessionId)
                ->values();

            if ($sessions->isEmpty()) {
                session()->forget('attendance_session_id');

                return redirect()->route('attendance.pin')->withErrors([
                    'pin' => 'This attendance link is no longer valid.',
                ]);
            }
        }

        $now = now();

        $sessions->each(function (EventSession $session) use ($now) {
            $session->attendance_available =
                $session->attendance_opens_at !== null
                && $session->attendance_closes_at !== null
                && $now->between(
                    $session->attendance_opens_at,
                    $session->attendance_closes_at
                );
        });

        /*
         * Default to Session 1 if it is currently available.
         * Otherwise default to the first currently available session.
         */
        $defaultSession = $sessions->firstWhere('attendance_available', true);
        $selectedSession = $sessions->firstWhere('id', (int) old('session_id'))
            ?? $defaultSession;

        $response = response()->view('attendance.form', compact(
            'event',
            'sessions',
            'defaultSession',
            'selectedSession'
        ));

        return $response;
    }

    public function store(
        Request $request,
        Event $event
    ): Response|RedirectResponse|JsonResponse {
        if (session('attendance_event_id') !== $event->id) {
            return redirect()->route('attendance.pin');
        }

        if (!$this->eventAllowsAttendance($event)) {
            return redirect()->route('attendance.pin')->withErrors([
                'pin' => 'Attendance is unavailable for this event.',
            ]);
        }

        if ($request->input('action') === 'lookup_pre_registration') {
            return $this->lookupPreRegistration($request, $event);
        }

        $validated = $request->validate([
            'session_id' => [
                'required',
                'integer',
                Rule::exists('event_sessions', 'id')
                    ->where(fn ($query) => $query->where('event_id', $event->id)),
            ],

            'full_name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:50',
                'regex:/^\\+?[0-9().\\s-]+$/',
            ],

            'position' => [
                'nullable',
                'string',
                'max:255',
            ],

            'unit' => [
                'nullable',
                'string',
                'max:255',
            ],

            'signature' => [
                'nullable',
                'string',
                'max:60000',
            ],

        ]);

        $phoneDigits = MalaysianPhoneNumber::canonicalize($validated['phone']);

        if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
            return $this->attendanceValidationError($request, [
                'phone' => 'Enter a phone number containing 7 to 15 digits.',
            ]);
        }

        $session = EventSession::where('event_id', $event->id)
            ->findOrFail($validated['session_id']);

        if (
            session('attendance_session_id') !== null
            && (int) session('attendance_session_id') !== (int) $session->id
        ) {
            return $this->attendanceValidationError($request, [
                'session_id' => 'This attendance link is for a different session.',
            ]);
        }

        /*
         * The selected Session must actually be open right now.
         * This prevents someone submitting for an old/future session.
         */
        if (
            !$session->attendance_opens_at ||
            !$session->attendance_closes_at ||
            !now()->between(
                $session->attendance_opens_at,
                $session->attendance_closes_at
            )
        ) {
            return $this->attendanceValidationError($request, [
                'session_id' => 'Attendance is not currently available for this session.',
            ]);
        }

        $email = strtolower(trim($validated['email']));

        /*
         * Prevent duplicate attendance for the same person/session.
         */
        $existingAttendance = Attendance::where(
            'session_id',
            $session->id
        )
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first(['clock_in_at', 'created_at']);

        // A phone cannot identify different people in one Event. The same
        // email/phone pair may check in once in each Session.
        $eventAttendances = $event->sessions()
            ->with('attendances:id,session_id,phone,email')
            ->get()
            ->flatMap(fn (EventSession $eventSession) => $eventSession->attendances);

        $matchingPhoneAttendances = $eventAttendances->filter(
            fn (Attendance $attendance) => MalaysianPhoneNumber::canonicalize($attendance->phone ?? '') === $phoneDigits
        );

        $phoneUsedByDifferentEmail = $matchingPhoneAttendances->contains(
            fn (Attendance $attendance) => strtolower(trim($attendance->email ?? '')) !== $email
        );

        $phoneAlreadyUsed = $phoneUsedByDifferentEmail || $matchingPhoneAttendances->contains(
            fn (Attendance $attendance) => (int) $attendance->session_id === (int) $session->id
        );

        $duplicateErrors = [];

        if ($existingAttendance) {
            $message = 'You have already checked in for this session';
            $checkedInAt = $existingAttendance->clock_in_at ?? $existingAttendance->created_at;
            if ($checkedInAt) {
                $message .= ' at ' . $checkedInAt->format('d M Y, h:i A');
            }
            $duplicateErrors['email'] = $message . '.';
        }

        if ($phoneAlreadyUsed) {
            $duplicateErrors['phone'] = $phoneUsedByDifferentEmail
                ? 'This phone number is already linked to a different email for this event.'
                : 'This phone number has already been used for this session.';
        }

        if ($duplicateErrors !== []) {
            return $this->attendanceValidationError($request, $duplicateErrors);
        }

        /*
         * Find an existing registration for this Event.
         *
         * For guest registrations, email is the practical matching
         * identifier.
         *
         * For registered users, also support matching through the
         * linked user account.
         */
        $registration = Registration::with('user')
            ->where('event_id', $event->id)
            ->where(function ($query) use ($email) {
                $query->whereRaw(
                    'LOWER(guest_email) = ?',
                    [$email]
                );

                $query->orWhereHas('user', function ($userQuery) use ($email) {
                    $userQuery->whereRaw(
                        'LOWER(email) = ?',
                        [$email]
                    );
                });
            })
            ->first();

        /*
         * If the registration belongs to an actual system account,
         * attach that user to the attendance record.
         *
         * Guest registrations remain user_id = NULL.
         */
        $userId = $registration?->user_id;

        $attendance = Attendance::create([
            'user_id' => $userId,
            'full_name' => trim($validated['full_name']),
            'position' => $validated['position'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'phone' => $phoneDigits,
            'email' => $email,
            'signature' => $validated['signature'] ?? null,
            'session_id' => $session->id,
            'verification_method' => 'pin',
        ]);

        return $this->attendanceSuccessResponse($event, $session, $registration);
    }

    private function attendanceSuccessResponse(
        Event $event,
        EventSession $session,
        ?Registration $registration
    ): Response {
        return response()->view('attendance.success', compact(
            'event',
            'session',
            'registration'
        ));
    }

    private function attendanceValidationError(
        Request $request,
        array $errors,
        int $status = 422
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => collect($errors)->flatten()->first(),
                'errors' => $errors,
            ], $status);
        }

        return back()
            ->withInput()
            ->withErrors($errors);
    }

    private function lookupPreRegistration(Request $request, Event $event): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'lookup_email' => ['required', 'email', 'max:255'],
            'lookup_phone' => ['required', 'string', 'max:50', 'regex:/^\\+?[0-9().\\s-]+$/'],
        ]);

        $email = strtolower(trim($validated['lookup_email']));
        $phoneDigits = MalaysianPhoneNumber::canonicalize($validated['lookup_phone']);

        if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
            return $this->attendanceValidationError($request, [
                'lookup_phone' => 'Enter a phone number containing 7 to 15 digits.',
            ]);
        }

        $registration = Registration::with('user')
            ->where('event_id', $event->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereNotNull('guest_phone')
            ->where(function ($query) use ($email) {
                $query->whereRaw('LOWER(TRIM(guest_email)) = ?', [$email])
                    ->orWhereHas('user', function ($userQuery) use ($email) {
                        $userQuery->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
                    });
            })
            ->get()
            ->first(fn (Registration $candidate) =>
                MalaysianPhoneNumber::canonicalize($candidate->guest_phone ?? '') === $phoneDigits
            );

        if ($registration) {
            $registrationEmail = strtolower(trim(
                $registration->guest_email ?? $registration->user?->email ?? ''
            ));

            if ($registrationEmail !== $email) {
                $registrationEmail = strtolower(trim($registration->user?->email ?? ''));
            }

            $details = [
                'full_name' => $registration->guest_name ?? $registration->user?->name ?? '',
                'email' => $registrationEmail,
                'phone' => MalaysianPhoneNumber::canonicalize($registration->guest_phone ?? ''),
                'position' => $registration->position ?? '',
                'unit' => $registration->organisation ?? '',
            ];

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'found',
                    'registration_status' => $registration->status,
                    'details' => $details,
                ]);
            }

            return redirect()
                ->route('attendance.form', $event)
                ->withInput($details)
                ->with('pre_registration_found', true)
                ->with('pre_registration_status', $registration->status);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'not_found']);
        }

        $attendanceInput = $request->only([
            'session_id',
            'full_name',
            'email',
            'phone',
            'position',
            'unit',
            'signature',
        ]);

        return redirect()
            ->route('attendance.form', $event)
            ->withInput($attendanceInput + [
                'lookup_email' => $email,
                'lookup_phone' => $validated['lookup_phone'],
            ])
            ->with('pre_registration_not_found', true);
    }

    private function eventAllowsAttendance(Event $event): bool
    {
        if ($event->status === 'published') {
            return true;
        }

        if ($event->status !== 'completed') {
            return false;
        }

        $now = now();

        return $event->sessions()
            ->whereNotNull('attendance_opens_at')
            ->whereNotNull('attendance_closes_at')
            ->where('attendance_opens_at', '<=', $now)
            ->where('attendance_closes_at', '>=', $now)
            ->exists();
    }
    public function openEncryptedLink(string $token): RedirectResponse
    {
        if ($token === '' || strlen($token) > 4096 || !preg_match('/\A[A-Za-z0-9_-]+\z/', $token)) {
            abort(404);
        }

        $encodedCiphertext = strtr($token, '-_', '+/');
        $encodedCiphertext .= str_repeat('=', (4 - strlen($encodedCiphertext) % 4) % 4);
        $ciphertext = base64_decode($encodedCiphertext, true);
        if ($ciphertext === false) {
            abort(404);
        }

        try {
            $payload = json_decode(
                Crypt::decryptString($ciphertext),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (DecryptException|JsonException $exception) {
            abort(404);
        }

        if (
            !is_array($payload)
            || ($payload['version'] ?? null) !== 1
            || !is_int($payload['event_id'] ?? null)
            || $payload['event_id'] < 1
            || !is_int($payload['session_id'] ?? null)
            || $payload['session_id'] < 1
            || !is_int($payload['expires_at'] ?? null)
            || $payload['expires_at'] < 1
        ) {
            abort(404);
        }

        if (now()->timestamp > $payload['expires_at']) {
            abort(403, 'This attendance link has expired.');
        }

        $event = Event::findOrFail($payload['event_id']);
        $eventSession = EventSession::where('event_id', $event->id)
            ->findOrFail($payload['session_id']);

        if (!$this->eventAllowsAttendance($event)) {
            abort(403, 'Attendance is unavailable for this event.');
        }

        if (
            $eventSession->attendance_opens_at === null
            || $eventSession->attendance_closes_at === null
            || !now()->between($eventSession->attendance_opens_at, $eventSession->attendance_closes_at)
        ) {
            abort(403, 'Attendance is unavailable for this session.');
        }

        session([
            'attendance_event_id' => $event->id,
            'attendance_session_id' => $eventSession->id,
        ]);

        return redirect()->route('attendance.form', $event);
    }
}
