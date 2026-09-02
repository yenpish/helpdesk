<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use Illuminate\Http\Request;

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

        return redirect()->route('attendance.form', $event);
    }

    public function showForm(AttendanceEvent $event)
    {
        return view('attendance.form', compact('event'));
    }
}
