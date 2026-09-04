<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $aaron = User::factory()->create([
            'name' => 'Aaron',
            'email' => 'aaron@test.com',
            'role' => 'organizer',
        ]);

        User::factory()->create([
            'name' => 'Bob',
            'email' => 'bob@test.com',
            'role' => 'user',
        ]);

        User::factory()->create([
            'name' => 'Sarah',
            'email' => 'sarah@test.com',
            'role' => 'technician',
        ]);

        $location = Location::create([
            'name' => 'Training Room',
            'latitude' => 0,
            'longitude' => 0,
            'allowed_radius' => 100,
        ]);

        $event = AttendanceEvent::create([
            'name' => 'Demo Training Session',
            'location_id' => $location->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'pin' => '1234',
        ]);

        $event->attendances()->create([
            'full_name' => 'Demo Attendee',
            'position' => 'Staff',
            'unit' => 'Training Unit',
            'phone' => '0123456789',
            'email' => 'demo@example.com',
            'signature' => null,
        ]);
    }
}
