<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Location;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index()
    {
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('clock_in_at', today())
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();

        $locations = Location::all();

        return view('attendance.index', [
            'attendance' => $attendance,
            'locations' => $locations,
        ]);
    }

    public function clockIn(Request $request)
    {
        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'accuracy' => ['required', 'numeric'],
        ]);

        $location = Location::findOrFail(
            $validated['location_id']
        );

        $distance = $this->calculateDistance(
            $validated['latitude'],
            $validated['longitude'],
            $location->latitude,
            $location->longitude
        );

        $withinSite = $distance <= $location->allowed_radius;

        /*
         * If this is the office location and the user is outside it, dont create an attendance record yet.
           Location #1 is currently the office.
         */
        if ($location->id === 1 && !$withinSite) {
            return response()->json([
                'message' => 'You are outside the office.',
                'outside_office' => true,
                'location' => $location->name,
                'distance_from_site' => round($distance, 2),
                'allowed_radius' => $location->allowed_radius,
                'accuracy' => $validated['accuracy'],
                'within_site' => false,
            ]);
        }

        /*
          For the selected office/site:
          only record attendance when the user is actually within the configured radius.
         */
        if (!$withinSite) {
            return response()->json([
                'message' => 'You are outside the selected location.',
                'outside_office' => false,
                'location' => $location->name,
                'distance_from_site' => round($distance, 2),
                'allowed_radius' => $location->allowed_radius,
                'accuracy' => $validated['accuracy'],
                'within_site' => false,
            ]);
        }

        $attendance = Attendance::create([
            'user_id' => auth()->id(),
            'clock_in_at' => now(),
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy' => $validated['accuracy'],
            'verification_method' => 'web_location',

            'location_id' => $location->id,
            'distance_from_site' => $distance,
            'within_site' => true,
        ]);

        return response()->json([
            'message' => 'Attendance recorded successfully.',
            'attendance_id' => $attendance->id,
            'location' => $location->name,
            'distance_from_site' => round($distance, 2),
            'allowed_radius' => $location->allowed_radius,
            'accuracy' => $validated['accuracy'],
            'within_site' => true,
        ]);
    }

    public function clockOut()
    {
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('clock_in_at', today())
            ->whereNull('clock_out_at')
            ->latest('clock_in_at')
            ->first();

        if (!$attendance) {
            return response()->json([
                'message' => 'No active attendance found.',
            ], 422);
        }

        $attendance->update([
            'clock_out_at' => now(),
        ]);

        return response()->json([
            'message' => 'Clocked out successfully.',
            'clock_out_at' => $attendance->clock_out_at->format('h:i A'),
        ]);
    }

    private function calculateDistance(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371000;

        $lat1 = deg2rad($latitude1);
        $lat2 = deg2rad($latitude2);

        $deltaLatitude = deg2rad($latitude2 - $latitude1);
        $deltaLongitude = deg2rad($longitude2 - $longitude1);

        $a =
            sin($deltaLatitude / 2) ** 2
            + cos($lat1)
            * cos($lat2)
            * sin($deltaLongitude / 2) ** 2;

        $c = 2 * atan2(
                sqrt($a),
                sqrt(1 - $a)
            );

        return $earthRadius * $c;
    }
}
