<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventTypeController extends Controller
{
    public function index(): View
    {
        $eventTypes = EventType::latest()->paginate(10);

        return view('event-types.index', compact('eventTypes'));
    }

    public function create(): View
    {
        return view('event-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $eventType = EventType::create($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'auditable_type' => EventType::class,
            'auditable_id' => $eventType->id,
            'description' => 'Event type created.',
            'new_values' => $eventType->toArray(),
        ]);

        return redirect()
            ->route('event-types.index')
            ->with('success', 'Event type created successfully.');
    }

    public function show(EventType $eventType): View
    {
        $eventType->load('events');

        return view('event-types.show', compact('eventType'));
    }

    public function edit(EventType $eventType): View
    {
        return view('event-types.edit', compact('eventType'));
    }

    public function update(Request $request, EventType $eventType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $oldValues = $eventType->toArray();

        $eventType->update($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'auditable_type' => EventType::class,
            'auditable_id' => $eventType->id,
            'description' => 'Event type updated.',
            'old_values' => $oldValues,
            'new_values' => $eventType->toArray(),
        ]);

        return redirect()
            ->route('event-types.show', $eventType)
            ->with('success', 'Event type updated successfully.');
    }

    public function destroy(EventType $eventType): RedirectResponse
    {
        $oldValues = $eventType->toArray();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'auditable_type' => EventType::class,
            'auditable_id' => $eventType->id,
            'description' => 'Event type deleted.',
            'old_values' => $oldValues,
        ]);

        $eventType->delete();

        return redirect()
            ->route('event-types.index')
            ->with('success', 'Event type deleted successfully.');
    }
}
