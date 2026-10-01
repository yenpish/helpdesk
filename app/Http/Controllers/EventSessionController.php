<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventSession;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventSessionController extends Controller
{
    public function index(Event $event): View
    {
        $event->load('sessions');

        return view('event-sessions.index', compact('event'));
    }

    public function create(Event $event): View
    {
        return view('event-sessions.create', compact('event'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
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
            'attendance_closes_at' => $endsAt,

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

    public function show(Event $event, EventSession $eventSession): View
    {
        abort_unless($eventSession->event_id === $event->id, 404);

        $eventSession->load([
            'event',
            'createdBy',
            'updatedBy',
            'attendances',
        ]);

        return view('event-sessions.show', compact('event', 'eventSession'));
    }

    public function edit(Event $event, EventSession $eventSession): View
    {
        abort_unless($eventSession->event_id === $event->id, 404);

        return view('event-sessions.edit', compact('event', 'eventSession'));
    }

    public function update(
        Request $request,
        Event $event,
        EventSession $eventSession
    ): RedirectResponse {
        abort_unless($eventSession->event_id === $event->id, 404);

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

            // Keep attendance window aligned with the session
            // until we implement the optional advanced window.
            'attendance_opens_at' => $startsAt,
            'attendance_closes_at' => $endsAt,

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
        abort_unless($eventSession->event_id === $event->id, 404);

        $oldValues = $eventSession->toArray();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'auditable_type' => EventSession::class,
            'auditable_id' => $eventSession->id,
            'description' => 'Event session deleted.',
            'old_values' => $oldValues,
        ]);

        $eventSession->delete();

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Session deleted successfully.');
    }
}
