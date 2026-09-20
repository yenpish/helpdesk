<?php

namespace Database\Seeders;

use App\Models\AttendanceEvent;
use App\Models\Location;
use Illuminate\Database\Seeder;

class DemoAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $locationIds = Location::pluck('id');

        for ($i = 0; $i < 20; $i++) {

            $start = now()->subDays(rand(1, 30))->setTime(rand(8, 15), rand(0, 59));
            $end = $start->copy()->addHours(rand(2, 6));

            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            while (AttendanceEvent::where('pin', $pin)->exists()) {
                $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            }

            $event = AttendanceEvent::create([
                'name' => fake()->randomElement([
                    'Laravel Fundamentals Workshop',
                    'PHP Web Development Training',
                    'Software Engineering Seminar',
                    'Web Application Development Workshop',
                    'IT System Training Session',
                    'Technical Skills Workshop',
                    'System Development Briefing',
                    'Developer Knowledge Sharing',
                ]),
                'location_id' => $locationIds->random(),
                'starts_at' => $start,
                'ends_at' => $end,
                'pin' => $pin,
            ]);

            $attendeeCount = rand(5, 8);

            for ($j = 0; $j < $attendeeCount; $j++) {
                $event->attendances()->create([
                    'full_name' => fake()->name(),
                    'position' => fake()->randomElement([
                        'Software Engineer',
                        'System Analyst',
                        'IT Officer',
                        'Developer',
                        'Project Executive',
                        'Technical Officer',
                    ]),
                    'unit' => fake()->randomElement([
                        'Information Technology',
                        'Software Development',
                        'Technical Services',
                        'Research and Development',
                        'Operations',
                    ]),
                    'phone' => '01' . fake()->numerify('########'),
                    'email' => fake()->unique()->safeEmail(),
                ]);
            }
        }
    }
}
