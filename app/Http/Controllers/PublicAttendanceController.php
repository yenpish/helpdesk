<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicAttendanceController extends Controller
{
    public function enterPin()
    {
        return view('attendance.pin');
    }

    public function verifyPin(Request $request)
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits:4'],
        ]);

        $event = AttendanceEvent::where('pin', $validated['pin'])->first();

        if (!$event) {
            return back()->withErrors([
                'pin' => 'Invalid attendance PIN.',
            ]);
        }

        if (now()->lt($event->starts_at) || now()->gt($event->ends_at)) {
            return back()->withErrors([
                'pin' => 'This attendance session is not currently active.',
            ]);
        }
        session(['attendance_event_id' => $event->id]);

        return redirect()->route('attendance.form', $event);
    }

    public function showForm(AttendanceEvent $event)
    {
        if (session('attendance_event_id') !== $event->id) {
            return redirect()->route('attendance.pin');
        }

        return view('attendance.form', compact('event'));
    }

    //take the submitted form and store it after validating
    public function store(Request $request, AttendanceEvent $event)
    {
        if (session('attendance_event_id') !== $event->id) {
            return redirect()->route('attendance.pin');
        }

        if (now()->lt($event->starts_at) || now()->gt($event->ends_at)) {
            return back()->withErrors([
                'attendance' => 'This attendance session is not currently active.',
            ]);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('attendances', 'email')
                    ->where('attendance_event_id', $event->id),
            ],
            'signature' => ['nullable', 'string'],
        ]);

        $event->attendances()->create($validated);

        return view('attendance.success');
    }
}
