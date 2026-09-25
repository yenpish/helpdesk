<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use App\Models\Location;
use Illuminate\Http\Request;

class AttendanceEventController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'start_desc');

        $sortOptions = [
            'start_desc' => ['starts_at', 'desc'],
            'start_asc' => ['starts_at', 'asc'],
            'end_desc' => ['ends_at', 'desc'],
            'end_asc' => ['ends_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
        ];

        [$sortColumn, $sortDirection] = $sortOptions[$sort] ?? $sortOptions['start_desc'];

        $events = AttendanceEvent::with('location')
            ->withCount('attendances')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->orderBy($sortColumn, $sortDirection)
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
            'activeEvents',
            'sort'
        ));
    }

    public function edit(AttendanceEvent $event)
    {
        $locations = Location::all();

        return view('attendance-events.edit', compact('event', 'locations'));
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

    public function update(Request $request, AttendanceEvent $event)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['required', 'exists:locations,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required'],
            'end_time' => ['required'],
        ]);

        $event->update([
            'name' => $validated['name'],
            'location_id' => $validated['location_id'],
            'starts_at' => $validated['start_date'] . ' ' . $validated['start_time'],
            'ends_at' => $validated['end_date'] . ' ' . $validated['end_time'],
        ]);

        return redirect()->route('attendance-events.show', $event);
    }

    public function export(AttendanceEvent $event)
    {
        $event->load('attendances');

        $filename = 'attendance-' . $event->id . '.csv';

        return response()->streamDownload(function () use ($event) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name',
                'Position',
                'Unit',
                'Phone',
                'Email',
                'Clock In',
                'Clock Out',
            ]);

            foreach ($event->attendances as $attendance) {
                fputcsv($handle, [
                    $attendance->full_name,
                    $attendance->position,
                    $attendance->unit,
                    $attendance->phone,
                    $attendance->email,
                    $attendance->clock_in_at?->format('d/m/Y H:i'),
                    $attendance->clock_out_at?->format('d/m/Y H:i'),
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    public function exportSelected(Request $request)
    {
        $validated = $request->validate([
            'event_ids' => ['required', 'array', 'min:1'],
            'event_ids.*' => ['integer', 'exists:attendance_events,id'],
        ]);

        $events = AttendanceEvent::with('attendances')
            ->whereIn('id', $validated['event_ids'])
            ->orderBy('starts_at', 'desc')
            ->get();

        return response()->streamDownload(function () use ($events) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Event',
                'Event Start',
                'Event End',
                'Name',
                'Position',
                'Unit',
                'Phone',
                'Email',
                'Clock In',
                'Clock Out',
            ]);

            foreach ($events as $event) {
                foreach ($event->attendances as $attendance) {
                    fputcsv($handle, [
                        $event->name,
                        $event->starts_at?->format('d/m/Y H:i'),
                        $event->ends_at?->format('d/m/Y H:i'),
                        $attendance->full_name,
                        $attendance->position,
                        $attendance->unit,
                        $attendance->phone,
                        $attendance->email,
                        $attendance->clock_in_at?->format('d/m/Y H:i'),
                        $attendance->clock_out_at?->format('d/m/Y H:i'),
                    ]);
                }
            }

            fclose($handle);
        }, 'attendance-selected.csv');
    }

    public function destroy(AttendanceEvent $event)
    {
        $event->delete();

        return redirect()->route('attendance-events.index');
    }
}
