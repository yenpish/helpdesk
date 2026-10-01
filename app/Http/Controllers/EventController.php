<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $events = Event::with(['eventType', 'location', 'organizer'])
            ->latest()
            ->paginate(10);

        return view('events.index', compact('events'));
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

        $sessionNumber = 1;
        $currentDate = $eventStart->copy()->startOfDay();

        while ($currentDate->lte($eventEnd->copy()->startOfDay())) {
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

            /*
             * For additional days, use the Event's default time range.
             * Individual Sessions can be edited afterwards.
             */
            if ($sessionEnd->lte($sessionStart)) {
                $sessionEnd = $sessionStart->copy()->addHour();
            }

            $event->sessions()->create([
                'name' => 'Session ' . $sessionNumber,
                'description' => null,
                'starts_at' => $sessionStart,
                'ends_at' => $sessionEnd,
                'attendance_opens_at' => $sessionStart,
                'attendance_closes_at' => $sessionEnd,
                'pin' => null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $sessionNumber++;
            $currentDate->addDay();
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
        $event->load([
            'eventType',
            'location',
            'organizer',
            'createdBy',
            'updatedBy',
            'sessions',
            'registrations',
        ]);

        return view('events.show', compact('event'));
    }

    public function edit(Event $event): View
    {
        $eventTypes = EventType::orderBy('name')->get();
        $locations = Location::orderBy('name')->get();

        return view('events.edit', compact(
            'event',
            'eventTypes',
            'locations'
        ));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
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
         * The original creator/responsible organizer remains attached.
         */
        $validated['updated_by'] = auth()->id();

        $event->update($validated);

        $this->createAuditLog(
            'updated',
            $event,
            'Event updated.',
            $oldValues,
            $event->only(array_keys($oldValues))
        );

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Event updated successfully.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $oldValues = $event->toArray();

        $this->createAuditLog(
            'deleted',
            $event,
            'Event deleted.',
            $oldValues
        );

        $event->delete();

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
                $pin .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (Event::where('pin', $pin)->exists());

        return $pin;
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
}
