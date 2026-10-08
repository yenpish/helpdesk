<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Support\MalaysianPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

        return redirect()->route('attendance.form', $event);
    }

    public function showForm(Event $event): View|RedirectResponse
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

        return view('attendance.form', compact(
            'event',
            'sessions',
            'defaultSession',
            'selectedSession'
        ));
    }

    public function store(
        Request $request,
        Event $event
    ): View|RedirectResponse {
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
            ],
        ]);

        $phoneDigits = MalaysianPhoneNumber::canonicalize($validated['phone']);

        if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
            return back()
                ->withInput()
                ->withErrors([
                    'phone' => 'Enter a phone number containing 7 to 15 digits.',
                ]);
        }

        $session = EventSession::where('event_id', $event->id)
            ->findOrFail($validated['session_id']);

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
            return back()
                ->withInput()
                ->withErrors([
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
            return back()
                ->withInput()
                ->withErrors($duplicateErrors);
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

        Attendance::create([
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

        return view('attendance.success', [
            'event' => $event,
            'session' => $session,
            'registration' => $registration,
        ]);
    }

    private function lookupPreRegistration(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'lookup_email' => ['required', 'email', 'max:255'],
            'lookup_phone' => ['required', 'string', 'max:50', 'regex:/^\\+?[0-9().\\s-]+$/'],
        ]);

        $email = strtolower(trim($validated['lookup_email']));
        $phoneDigits = MalaysianPhoneNumber::canonicalize($validated['lookup_phone']);

        if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
            return back()
                ->withInput()
                ->withErrors([
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

            return redirect()
                ->route('attendance.form', $event)
                ->withInput([
                    'full_name' => $registration->guest_name ?? $registration->user?->name ?? '',
                    'email' => $registrationEmail,
                    'phone' => MalaysianPhoneNumber::canonicalize($registration->guest_phone ?? ''),
                    'position' => $registration->position ?? '',
                    'unit' => $registration->organisation ?? '',
                ])
                ->with('pre_registration_found', true)
                ->with('pre_registration_status', $registration->status);
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
}
