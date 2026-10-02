<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $events = Event::with(['eventType', 'location'])
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('starts_at')
            ->paginate(10);

        return view('registrations.public-index', compact('events'));
    }

    /*
     * Public Event registration page.
     */
    public function create(Event $event): View|RedirectResponse
    {
        if ($event->status !== 'published') {
            return redirect()
                ->route('registrations.public-index')
                ->withErrors([
                    'event' => 'This event is not currently open for registration.',
                ]);
        }

        return view('registrations.create', compact('event'));
    }

    /*
     * Public guest registration.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        if ($event->status !== 'published') {
            return back()->withErrors([
                'event' => 'This event is not currently open for registration.',
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
            return back()
                ->withInput()
                ->withErrors([
                    'guest_email' => 'This email is already registered for this event.',
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
                    'guest_phone' => 'This phone number is already used for a registration at this event.',
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
            ->with('success', 'Registration submitted successfully.');
    }

    public function success(Registration $registration): View
    {
        $registration->load('event');

        return view('registrations.success', compact('registration'));
    }

    /*
     * Organizer/Admin view of registrations for an Event.
     */
    public function eventRegistrations(Event $event): View
    {
        if (
            auth()->user()->role !== 'admin' &&
            $event->organizer_id !== auth()->id()
        ) {
            abort(403);
        }

        $registrations = $event->registrations()
            ->with('user')
            ->latest('registered_at')
            ->paginate(20);

        return view('registrations.index', compact(
            'event',
            'registrations'
        ));
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
        ]);

        $oldValues = $registration->only([
            'status',
        ]);

        $registration->update([
            'status' => $validated['status'],
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'auditable_type' => Registration::class,
            'auditable_id' => $registration->id,
            'description' => 'Registration status updated.',
            'old_values' => $oldValues,
            'new_values' => [
                'status' => $registration->status,
            ],
        ]);

        return back()->with(
            'success',
            'Registration status updated successfully.'
        );
    }

}
