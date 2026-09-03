<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use App\Models\Location;
use Illuminate\Http\Request;

class AttendanceEventController extends Controller
{
    public function index()
    {
        $events = AttendanceEvent::with('location')
            ->withCount('attendances')
            ->latest()
            ->get();

        $totalEvents = $events->count();

        $totalAttendees = $events->sum('attendances_count');

        $activeEvents = $events->filter(function ($event) {
            return $event->starts_at
                && $event->ends_at
                && now()->between($event->starts_at, $event->ends_at);
        })->count();

        return view('attendance-events.index', compact(
            'events',
            'totalEvents',
            'totalAttendees',
            'activeEvents'
        ));
    }
    public function show(AttendanceEvent $event)
    {
        $event->load('attendances');

        return view('attendance-events.show', compact('event'));
    }

    public function create()
    {
        $locations = Location::all();

        return view('attendance-events.create', compact('locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['required', 'exists:locations,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required'],
            'end_time' => ['required'],
        ]);

        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        while (AttendanceEvent::where('pin', $pin)->exists()) {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        AttendanceEvent::create([
            'name' => $validated['name'],
            'location_id' => $validated['location_id'],
            'starts_at' => $validated['start_date'] . ' ' . $validated['start_time'],
            'ends_at' => $validated['end_date'] . ' ' . $validated['end_time'],
            'pin' => $pin,
        ]);

        return redirect()->route('attendance-events.create')
            ->with('success', "Attendance session created. PIN: {$pin}");
    }
}
