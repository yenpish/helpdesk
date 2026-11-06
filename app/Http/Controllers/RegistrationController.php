<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Registration;
use App\Mail\RegistrationDecisionMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Models\AuditLog;
use App\Support\MalaysianPhoneNumber;

class RegistrationController extends Controller
{
    /*
     * Public upcoming events.
     */
    public function publicIndex(): View
{
    // Upcoming published events for pre-registration.
    $events = Event::with(['eventType', 'location'])
        ->where('status', 'published')
        ->whereHas('sessions')
        ->whereNotNull('starts_at')
        ->where('starts_at', '>', now())
        ->orderBy('starts_at')
        ->paginate(10);

    return view(
        'registrations.public-index',
        compact('events')
    );
}

    /*
     * Public Event registration page.
     */
    public function create(Event $event): View|RedirectResponse
    {
        if (!$this->canAcceptAdvanceRegistration($event)) {
            return redirect()
                ->route('registrations.public-index')
                ->withErrors([
                    'event' => 'Pre-registration is open only for published events that have not started.',
                ]);
        }

        return view('registrations.create', compact('event'));
    }

    /*
     * Public guest registration.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        if (!$this->canAcceptAdvanceRegistration($event)) {
            return redirect()
                ->route('registrations.public-index')
                ->withErrors([
                    'event' => 'Pre-registration is open only for published events that have not started.',
                ]);
        }

        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:50', 'regex:/^\\+?[0-9().\\s-]+$/'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
        ]);

        $phoneDigits = MalaysianPhoneNumber::canonicalize($validated['guest_phone']);

        if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
            return back()
                ->withInput()
                ->withErrors([
                    'guest_phone' => 'Enter a phone number containing 7 to 15 digits.',
                ]);
        }

        $email = strtolower(trim($validated['guest_email']));

        $existing = Registration::where('event_id', $event->id)
            ->whereRaw('LOWER(guest_email) = ?', [$email])
            ->first();

        if ($existing) {
            if (
                in_array($existing->status, ['rejected', 'cancelled'], true)
                && MalaysianPhoneNumber::canonicalize($existing->guest_phone) === $phoneDigits
            ) {
                DB::transaction(function () use ($existing, $event, $validated, $email, $phoneDigits): void {
                    $registration = Registration::whereKey($existing->id)->lockForUpdate()->firstOrFail();
                    $previousStatus = $registration->status;
                    if (
                        ! in_array($previousStatus, ['rejected', 'cancelled'], true)
                        || MalaysianPhoneNumber::canonicalize($registration->guest_phone) !== $phoneDigits
                    ) {
                        throw ValidationException::withMessages([
                            'guest_email' => 'This email is already pre-registered for this event.',
                        ]);
                    }
                    $registration->forceFill([
                        'guest_name' => trim($validated['guest_name']),
                        'guest_email' => $email,
                        'guest_phone' => $phoneDigits,
                        'organisation' => $validated['organisation'] ?? null,
                        'position' => $validated['position'] ?? null,
                        'status' => 'pending',
                        'registered_at' => now(),
                        'decision_email_status' => null,
                        'decision_email_for' => null,
                        'decision_email_attempted_at' => null,
                    ])->save();

                    AuditLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'resubmitted',
                        'auditable_type' => Registration::class,
                        'auditable_id' => $registration->id,
                        'description' => 'Pre-registration resubmitted for review.',
                        'old_values' => ['status' => $previousStatus],
                        'new_values' => ['status' => 'pending'],
                    ]);
                });

                return redirect()
                    ->route('registrations.success', $existing)
                    ->with('success', 'Your corrected pre-registration has been sent for review.');
            }

            return back()
                ->withInput()
                ->withErrors([
                    'guest_email' => 'This email is already pre-registered for this event.',
                ]);
        }

        $normalizedPhone = $phoneDigits;
        $duplicatePhone = $event->registrations()
            ->whereNotNull('guest_phone')
            ->get(['guest_phone'])
            ->contains(fn (Registration $existingRegistration) =>
                MalaysianPhoneNumber::canonicalize($existingRegistration->guest_phone) === $normalizedPhone
            );

        if ($duplicatePhone) {
            return back()
                ->withInput()
                ->withErrors([
                    'guest_phone' => 'This phone number is already used for a pre-registration at this event.',
                ]);
        }

        $registration = Registration::create([
            'event_id' => $event->id,
            'user_id' => auth()->id(),
            'guest_name' => trim($validated['guest_name']),
            'guest_email' => $email,
            'guest_phone' => $normalizedPhone,
            'organisation' => $validated['organisation'] ?? null,
            'position' => $validated['position'] ?? null,
            'status' => 'pending',
            'registered_at' => now(),
        ]);

        return redirect()
            ->route('registrations.success', $registration)
            ->with('success', 'Pre-registration submitted successfully.');
    }

    public function success(Registration $registration): View
    {
        $registration->load('event');

        return view('registrations.success', compact('registration'));
    }

    /*
     * Organizer/Admin view of registrations for an Event.
     */
    public function eventRegistrations(Request $request, Event $event): View
    {
        if (
            auth()->user()->role !== 'admin' &&
            $event->organizer_id !== auth()->id()
        ) {
            abort(403);
        }

        $searchValue = $request->query('search', '');
        $search = is_string($searchValue) ? trim(substr($searchValue, 0, 100)) : '';
        $statusValue = $request->query('status', '');
        $status = is_string($statusValue) ? $statusValue : '';
        if (!in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $status = '';
        }

        $query = $event->registrations()->with('user');
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                foreach (['guest_name', 'guest_email', 'guest_phone', 'organisation', 'position'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', '%' . $search . '%');
                }
                $query->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $registrations = $query->latest('registered_at')->paginate(20)->withQueryString();

        return view('registrations.index', compact('event', 'registrations', 'search', 'status'));
    }

    public function updateDetails(Request $request, Event $event, Registration $registration): RedirectResponse
    {
        $this->authorizeRegistrationEvent($event);
        abort_unless((int) $registration->event_id === (int) $event->id, 404);

        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:50', 'regex:/^\\+?[0-9().\\s-]+$/'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($validated['guest_email']));
        $phone = MalaysianPhoneNumber::canonicalize($validated['guest_phone']);
        if (strlen($phone) < 7 || strlen($phone) > 15) {
            throw ValidationException::withMessages([
                'guest_phone' => 'Enter a phone number containing 7 to 15 digits.',
            ]);
        }

        DB::transaction(function () use ($event, $registration, $validated, $email, $phone): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $record = Registration::query()
                ->where('event_id', $event->id)
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $emailExists = $event->registrations()
                ->where('id', '!=', $record->id)
                ->whereRaw('LOWER(guest_email) = ?', [$email])
                ->exists();

            $phoneExists = $event->registrations()
                ->where('id', '!=', $record->id)
                ->whereNotNull('guest_phone')
                ->get(['guest_phone'])
                ->contains(fn (Registration $candidate) => MalaysianPhoneNumber::canonicalize($candidate->guest_phone ?? '') === $phone);
            $duplicateErrors = [];
            if ($emailExists) {
                $duplicateErrors['guest_email'] = 'This email is already pre-registered for this event.';
            }
            if ($phoneExists) {
                $duplicateErrors['guest_phone'] = 'This phone number is already used for a pre-registration at this event.';
            }
            if ($duplicateErrors !== []) {
                throw ValidationException::withMessages($duplicateErrors);
            }

            $record->forceFill([
                'guest_name' => trim($validated['guest_name']),
                'guest_email' => $email,
                'guest_phone' => $phone,
                'organisation' => isset($validated['organisation']) ? trim($validated['organisation']) : null,
                'position' => isset($validated['position']) ? trim($validated['position']) : null,
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
                'auditable_type' => Registration::class,
                'auditable_id' => $record->id,
                'description' => 'Pre-registration details corrected.',
                'new_values' => ['fields_changed' => $changedFields],
            ]);
        });

        return redirect()
            ->route('events.registrations.index', [$event] + $this->registrationReturnParameters($request))
            ->with('success', 'Pre-registration details updated.');
    }

    public function editDetails(Request $request, Event $event, Registration $registration): View
    {
        $this->authorizeRegistrationEvent($event);
        abort_unless((int) $registration->event_id === (int) $event->id, 404);

        $returnParameters = $this->registrationReturnParameters($request);

        return view('registrations.edit', compact('event', 'registration', 'returnParameters'));
    }

    private function registrationReturnParameters(Request $request): array
    {
        $search = $request->input('_return_search', $request->query('search', ''));
        $search = is_string($search) ? trim(substr($search, 0, 100)) : '';
        $status = $request->input('_return_status', $request->query('status', ''));
        $allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];
        $status = is_string($status) && in_array($status, $allowedStatuses, true) ? $status : '';
        $page = filter_var($request->input('_return_page', $request->query('page')), FILTER_VALIDATE_INT);

        return array_filter([
            'search' => $search !== '' ? $search : null,
            'status' => $status !== '' ? $status : null,
            'page' => $page !== false && $page > 1 ? $page : null,
        ], fn ($value) => $value !== null);
    }

    private function authorizeRegistrationEvent(Event $event): void
    {
        if (auth()->user()->role !== 'admin' && (int) $event->organizer_id !== (int) auth()->id()) {
            abort(403);
        }
    }

    /*
     * Approve / reject a registration.
     */
    public function updateStatus(
        Request $request,
        Event $event,
        Registration $registration
    ): RedirectResponse {

        if (
            auth()->user()->role !== 'admin' &&
            $event->organizer_id !== auth()->id()
        ) {
            abort(403);
        }

        abort_unless($registration->event_id === $event->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,cancelled'],
            'retry_decision_email' => ['sometimes', 'boolean'],
        ]);
        $retry = (bool) ($validated['retry_decision_email'] ?? false);

        $decision = DB::transaction(function () use ($event, $registration, $validated, $retry): array {
            $locked = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->event_id === $event->id, 404);
            $oldStatus = $locked->status;
            $newStatus = $validated['status'];
            $changed = $oldStatus !== $newStatus;

            if ($retry) {
                if (
                    $changed
                    || ! in_array($oldStatus, ['approved', 'rejected'], true)
                    || $locked->decision_email_status !== 'failed'
                    || $locked->decision_email_for !== $oldStatus
                ) {
                    abort(422, 'Only a failed decision email for the current status can be retried.');
                }
                $locked->forceFill([
                    'decision_email_status' => 'pending',
                    'decision_email_attempted_at' => now(),
                ])->save();
            } elseif ($changed) {
                $locked->status = $newStatus;
                if (in_array($newStatus, ['approved', 'rejected'], true)) {
                    $locked->forceFill([
                        'decision_email_status' => 'pending',
                        'decision_email_for' => $newStatus,
                        'decision_email_attempted_at' => now(),
                    ]);
                } else {
                    $locked->forceFill([
                        'decision_email_status' => null,
                        'decision_email_for' => null,
                        'decision_email_attempted_at' => null,
                    ]);
                }
                $locked->save();

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'updated',
                    'auditable_type' => Registration::class,
                    'auditable_id' => $locked->id,
                    'description' => 'Pre-registration status updated.',
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => $newStatus],
                ]);
            } elseif (! $retry) {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'updated',
                    'auditable_type' => Registration::class,
                    'auditable_id' => $locked->id,
                    'description' => 'Pre-registration status unchanged.',
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => $oldStatus],
                ]);
            }

            return [
                'registration' => $locked->loadMissing(['event', 'user']),
                'changed' => $changed,
                'retry' => $retry,
                'notify' => $retry || ($changed && in_array($newStatus, ['approved', 'rejected'], true)),
            ];
        });

        if (! $decision['notify']) {
            return back()->with('success', $decision['changed']
                ? 'Pre-registration status updated.'
                : 'No status change was needed; no email was sent.');
        }

        $mailStatus = $this->sendDecisionEmail($decision['registration'], $decision['registration']->status);
        if ($decision['retry']) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'retried',
                'auditable_type' => Registration::class,
                'auditable_id' => $decision['registration']->id,
                'description' => 'Pre-registration decision email retried.',
                'old_values' => ['decision_email_status' => 'failed'],
                'new_values' => ['decision_email_status' => $mailStatus],
            ]);
        }

        if ($mailStatus === 'failed') {
            return back()->with('success', 'Registration '.($decision['registration']->status === 'approved' ? 'approved' : 'rejected').'. Email could not be sent.');
        }

        return back()->with('success', 'Registration '.($decision['registration']->status === 'approved' ? 'approved' : 'rejected').'. Email sent.');
    }

    private function sendDecisionEmail(Registration $registration, string $decision): string
    {
        $registration->loadMissing(['event', 'user']);
        $recipient = trim((string) ($registration->guest_email ?: $registration->user?->email));

        try {
            if ($recipient === '') {
                throw new \RuntimeException('No recipient address.');
            }

            Mail::to($recipient)->send(new RegistrationDecisionMail(
                $registration->event,
                $registration,
                $decision
            ));
        } catch (\Throwable) {
            $registration->forceFill([
                'decision_email_status' => 'failed',
                'decision_email_for' => $decision,
                'decision_email_attempted_at' => now(),
            ])->save();

            return 'failed';
        }

        $registration->forceFill([
            'decision_email_status' => 'accepted',
            'decision_email_for' => $decision,
            'decision_email_attempted_at' => now(),
        ])->save();

        return 'accepted';
    }

    private function canAcceptAdvanceRegistration(Event $event): bool
    {
        return $event->status === 'published'
            && $event->starts_at !== null
            && $event->starts_at->isFuture()
            && $event->sessions()->exists();
    }

}
