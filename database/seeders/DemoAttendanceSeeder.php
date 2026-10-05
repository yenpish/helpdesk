<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventType;
use App\Models\Location;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'System Admin', 'role' => 'admin', 'password' => 'password']
        );
        $organizer = User::firstOrCreate(
            ['email' => 'organizer@example.test'],
            ['name' => 'Demo Organizer', 'role' => 'organizer', 'password' => 'password']
        );
        $eventType = EventType::firstOrCreate(
            ['name' => 'Training'],
            ['description' => 'Training and development sessions']
        );
        $location = Location::firstOrCreate(
            ['name' => 'Training Room'],
            ['latitude' => 0, 'longitude' => 0, 'allowed_radius' => 100]
        );

        $startsAt = now()->addDays(3)->setTime(9, 0, 0);
        $endsAt = $startsAt->copy()->setTime(17, 0, 0);
        $event = Event::firstOrCreate(
            ['name' => 'Demo Training Session'],
            [
                'description' => 'Sample event for trying the registration and session workflow.',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'event_type_id' => $eventType->id,
                'location_id' => $location->id,
                'organizer_id' => $organizer->id,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
                'status' => 'published',
                'pin' => 'D3M7',
            ]
        );

        EventSession::firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Session 1'],
            [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'attendance_opens_at' => $startsAt,
                'attendance_closes_at' => $startsAt->copy()->setTime(23, 59),
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        Registration::firstOrCreate(
            ['event_id' => $event->id, 'guest_email' => 'attendee@example.test'],
            [
                'guest_name' => 'Demo Attendee',
                'guest_phone' => '+60123456789',
                'organisation' => 'Example Organisation',
                'position' => 'Staff',
                'status' => 'approved',
                'registered_at' => now(),
            ]
        );
    }
}
