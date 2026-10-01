<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(): View
    {
        $locations = Location::latest()->paginate(10);

        return view('locations.index', compact('locations'));
    }

    public function create(): View
    {
        return view('locations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'allowed_radius' => ['required', 'numeric', 'min:0'],
        ]);

        $location = Location::create($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'auditable_type' => Location::class,
            'auditable_id' => $location->id,
            'description' => 'Location created.',
            'new_values' => $location->toArray(),
        ]);

        return redirect()
            ->route('locations.index')
            ->with('success', 'Location created successfully.');
    }

    public function show(Location $location): View
    {
        $location->load('events');

        return view('locations.show', compact('location'));
    }

    public function edit(Location $location): View
    {
        return view('locations.edit', compact('location'));
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'allowed_radius' => ['required', 'numeric', 'min:0'],
        ]);

        $oldValues = $location->toArray();

        $location->update($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'auditable_type' => Location::class,
            'auditable_id' => $location->id,
            'description' => 'Location updated.',
            'old_values' => $oldValues,
            'new_values' => $location->toArray(),
        ]);

        return redirect()
            ->route('locations.show', $location)
            ->with('success', 'Location updated successfully.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $oldValues = $location->toArray();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'auditable_type' => Location::class,
            'auditable_id' => $location->id,
            'description' => 'Location deleted.',
            'old_values' => $oldValues,
        ]);

        $location->delete();

        return redirect()
            ->route('locations.index')
            ->with('success', 'Location deleted successfully.');
    }
}
