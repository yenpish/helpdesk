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
use Illuminate\Support\Facades\RateLimiter;
use JsonException;


class PublicAttendanceController extends Controller
{
    private const INVALID_PIN_ATTEMPT_KEY = 'attendance-pin:invalid-attempts';
    private const INVALID_PIN_LOCKOUT_KEY = 'attendance-pin:invalid-lockout';
    private const INVALID_PIN_ATTEMPT_LIMIT = 30;
    private const INVALID_PIN_WINDOW_SECONDS = 60;
    private const INVALID_PIN_LOCKOUT_SECONDS = 60;

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
            if (RateLimiter::tooManyAttempts(self::INVALID_PIN_LOCKOUT_KEY, 1)) {
                abort(429, 'Too many invalid Attendance PIN attempts. Please wait and try again.');
            }

            RateLimiter::hit(self::INVALID_PIN_ATTEMPT_KEY, self::INVALID_PIN_WINDOW_SECONDS);

            if (RateLimiter::tooManyAttempts(self::INVALID_PIN_ATTEMPT_KEY, self::INVALID_PIN_ATTEMPT_LIMIT)) {
                RateLimiter::hit(self::INVALID_PIN_LOCKOUT_KEY, self::INVALID_PIN_LOCKOUT_SECONDS);

                abort(429, 'Too many invalid Attendance PIN attempts. Please wait and try again.');
            }

            return back()->withErrors([
                'pin' => 'Invalid Attendance PIN.',
            ]);
        }

        if (!$this->eventAllowsAttendance($event)) {
            return back()->withErrors([
                'pin' => 'Attendance is unavailable for this event.',
            ]);
        }

        $access = $this->createAttendanceAccess($event);

        return redirect()->route('attendance.form', ['access' => $access]);
    }

    public function showForm(string $access): Response|RedirectResponse
    {
        $grant = $this->resolveAttendanceAccess($access);
        if ($grant === null) {
            return redirect()->route('attendance.pin');
        }

        /** @var Event $event */
        $event = $grant['event'];
        /** @var EventSession|null $boundSession */
        $boundSession = $grant['session'];

        if (!$this->eventAllowsAttendance($event)) {
            return redirect()->route('attendance.pin')->withErrors([
                'pin' => 'Attendance is unavailable for this event.',
            ]);
        }

        $event->load('location');

        $sessions = $event->sessions()
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        if ($boundSession !== null) {
            $sessions = $sessions
                ->where('id', (int) $boundSession->id)
                ->values();

            if ($sessions->isEmpty()) {
                $this->forgetAttendanceAccess($access);

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

        $attendancePrefill = null;

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
            'selectedSession',
            'access',
            'attendancePrefill'
        ));

        return $response;
    }

    public function store(
        Request $request,
        string $access
    ): Response|RedirectResponse|JsonResponse {
        $grant = $this->resolveAttendanceAccess($access);
        if ($grant === null) {
            return redirect()->route('attendance.pin');
        }

        /** @var Event $event */
        $event = $grant['event'];
        /** @var EventSession|null $boundSession */
        $boundSession = $grant['session'];

        if (!$this->eventAllowsAttendance($event)) {
            return redirect()->route('attendance.pin')->withErrors([
                'pin' => 'Attendance is unavailable for this event.',
            ]);
        }

        if ($request->input('action') === 'lookup_pre_registration') {
            return $this->lookupPreRegistration($request, $event, $access);
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

        if ($boundSession !== null && (int) $boundSession->id !== (int) $session->id) {
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

    private function lookupPreRegistration(Request $request, Event $event, string $access): RedirectResponse|JsonResponse
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

        $registration = Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereNotNull('guest_phone')
            ->whereRaw('LOWER(TRIM(guest_email)) = ?', [$email])
            ->get()
            ->first(fn (Registration $candidate) =>
                MalaysianPhoneNumber::canonicalize($candidate->guest_phone ?? '') === $phoneDigits
            );

        if ($request->expectsJson()) {
            return response()->json(['status' => $registration ? 'found' : 'not_found']);
        }

        $input = [
            'lookup_email' => $validated['lookup_email'],
            'lookup_phone' => $validated['lookup_phone'],
            'email' => $email,
            'phone' => $phoneDigits,
        ];
        $detailsAuthorized = $registration !== null
            && $registration->status === 'approved'
            && $this->mayAutofillRegistration($request, $event, $registration);

        if ($detailsAuthorized) {
            $input = array_merge($input, [
                'full_name' => $registration->guest_name ?? '',
                'email' => $registration->guest_email ?? $email,
                'phone' => $registration->guest_phone ?? $phoneDigits,
                'position' => $registration->position ?? '',
                'unit' => $registration->organisation ?? '',
            ]);
        }

        $redirect = redirect()
            ->route('attendance.form', ['access' => $access])
            ->withInput($input);

        if ($registration === null) {
            return $redirect->with('pre_registration_not_found', true);
        }

        if ($registration->status !== 'approved') {
            return $redirect->with('pre_registration_awaiting_approval', true);
        }

        return $redirect->with($detailsAuthorized
            ? 'pre_registration_verified'
            : 'pre_registration_not_verified', true);
    }

    private function mayAutofillRegistration(Request $request, Event $event, Registration $registration): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        // Match the existing event-record access boundary: admins, or its organizer.
        if (
            $user->role === 'admin'
            || ($user->role === 'organizer' && (int) $event->organizer_id === (int) $user->id)
        ) {
            return true;
        }

        // A registration explicitly linked to this account is an established association.
        if ((int) ($registration->user_id ?? 0) === (int) $user->id) {
            return true;
        }

        // Otherwise require an already verified account email matching the registration.
        return $user->email_verified_at !== null
            && strtolower(trim((string) $user->email)) === strtolower(trim((string) $registration->guest_email));
    }

    private function eventAllowsAttendance(Event $event): bool
    {
        if ($event->status === 'published') {
            return $event->sessions()->exists();
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

        $access = $this->createAttendanceAccess(
            $event,
            $eventSession,
            $payload['expires_at']
        );

        return redirect()->route('attendance.form', ['access' => $access]);
    }

    /**
     * Create a cryptographically random URL handle scoped to this browser session.
     * QR links establish a fresh handle per browser after their encrypted claim is validated.
     */
    private function createAttendanceAccess(
        Event $event,
        ?EventSession $eventSession = null,
        ?int $expiresAt = null
    ): string {
        $access = bin2hex(random_bytes(32));
        $grants = session('attendance_accesses', []);
        $grants[$access] = [
            'event_id' => (int) $event->id,
            'session_id' => $eventSession ? (int) $eventSession->id : null,
            'expires_at' => $expiresAt,
        ];

        // Bound session growth while allowing several event/session tabs.
        session(['attendance_accesses' => array_slice($grants, -10, null, true)]);

        return $access;
    }

    /**
     * Resolve a submitted opaque handle only from the current browser session,
     * and re-check event/session eligibility on every form and submission request.
     *
     * @return array{event: Event, session: EventSession|null}|null
     */
    private function resolveAttendanceAccess(string $access): ?array
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $access)) {
            return null;
        }

        $grant = session("attendance_accesses.{$access}");
        if (!is_array($grant) || !is_int($grant['event_id'] ?? null)) {
            return null;
        }

        if (is_int($grant['expires_at'] ?? null) && now()->timestamp > $grant['expires_at']) {
            $this->forgetAttendanceAccess($access);
            return null;
        }

        $event = Event::find($grant['event_id']);
        if (!$event || !$this->eventAllowsAttendance($event)) {
            $this->forgetAttendanceAccess($access);
            return null;
        }

        $eventSession = null;
        if (isset($grant['session_id'])) {
            if (!is_int($grant['session_id'])) {
                $this->forgetAttendanceAccess($access);
                return null;
            }

            $eventSession = EventSession::where('event_id', $event->id)
                ->find($grant['session_id']);

            if (
                !$eventSession
                || $eventSession->attendance_opens_at === null
                || $eventSession->attendance_closes_at === null
                || !now()->between($eventSession->attendance_opens_at, $eventSession->attendance_closes_at)
            ) {
                $this->forgetAttendanceAccess($access);
                return null;
            }
        }

        return ['event' => $event, 'session' => $eventSession];
    }

    private function forgetAttendanceAccess(string $access): void
    {
        $grants = session('attendance_accesses', []);
        unset($grants[$access]);
        session(['attendance_accesses' => $grants]);
    }
}
