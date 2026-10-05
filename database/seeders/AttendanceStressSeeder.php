<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventType;
use App\Models\Location;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AttendanceStressSeeder extends Seeder
{
    private const EVENT_COUNT = 40;
    private const LARGE_EVENT_INDEX = 40;
    private const LARGE_REGISTRATION_COUNT = 150;

    public function run(): void
    {
        $eventType = EventType::query()->orderBy('id')->first()
            ?? EventType::firstOrCreate(
                ['name' => 'Stress Test Workshop'],
                ['description' => 'Synthetic event type for UI stress testing.']
            );

        $location = Location::query()->orderBy('id')->first()
            ?? Location::firstOrCreate(
                ['name' => 'Stress Test Venue'],
                ['latitude' => 3.1390, 'longitude' => 101.6869, 'allowed_radius' => 100]
            );

        // Reuse an existing account for record metadata. This seeder never creates users.
        $actorId = User::query()
            ->whereIn('role', ['organizer', 'admin'])
            ->orderByRaw("CASE WHEN role = 'organizer' THEN 0 ELSE 1 END")
            ->value('id');

        for ($index = 1; $index <= self::EVENT_COUNT; $index++) {
            $this->seedEvent($index, $eventType, $location, $actorId);
        }
    }

    private function seedEvent(int $index, EventType $eventType, Location $location, ?int $actorId): void
    {
        $isLargeEvent = $index === self::LARGE_EVENT_INDEX;
        $sessionCount = $isLargeEvent ? 3 : (($index % 3) + 1);
        $status = $isLargeEvent ? 'published' : $this->eventStatus($index);
        $eventStart = $this->eventStart($index, $status, $isLargeEvent);
        $lastSessionEnd = $eventStart->copy()->addDays($sessionCount - 1)->setTime(17, 0);
        $baseName = $isLargeEvent
            ? 'Stress Test Event 40 - Large Registration Event'
            : sprintf('Stress Test Event %02d', $index);

        $seedKey = 'ATS-' . sprintf('%02d', $index);
        $description = 'Synthetic data for interface stress testing. Seed key: ' . $seedKey;
        $event = Event::query()->where('description', $description)->first();

        if (!$event) {
            // Do not attach generated children to an unrelated event with a colliding display name.
            $name = $baseName;
            $collision = 0;
            while (Event::query()->where('name', $name)->exists()) {
                $name = $baseName . ' [' . $seedKey . '-' . ++$collision . ']';
            }

            $event = Event::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'starts_at' => $eventStart,
                    'ends_at' => $lastSessionEnd,
                    'event_type_id' => $eventType->id,
                    'location_id' => $location->id,
                    'organizer_id' => $actorId,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                    'status' => $status,
                    'pin' => $this->availableEventPin($index, $name),
                ]
            );
        }

        $sessions = [];
        for ($sessionNumber = 1; $sessionNumber <= $sessionCount; $sessionNumber++) {
            $startsAt = $eventStart->copy()->addDays($sessionNumber - 1)->setTime(9, 0);
            $endsAt = $startsAt->copy()->setTime(17, 0);
            $session = EventSession::firstOrCreate(
                ['event_id' => $event->id, 'name' => 'Session ' . $sessionNumber],
                [
                    'description' => 'Stress test session ' . $sessionNumber . '.',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'attendance_opens_at' => $startsAt,
                    'attendance_closes_at' => $endsAt,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );
            $sessions[] = $session;
        }

        $attendeeCount = $isLargeEvent ? self::LARGE_REGISTRATION_COUNT : 8 + ($index % 8);
        for ($attendeeNumber = 1; $attendeeNumber <= $attendeeCount; $attendeeNumber++) {
            $attendee = $this->attendee($index, $attendeeNumber);

            Registration::firstOrCreate(
                ['event_id' => $event->id, 'guest_email' => $attendee['email']],
                [
                    'user_id' => null,
                    'guest_name' => $attendee['name'],
                    'guest_phone' => $attendee['phone'],
                    'organisation' => $attendee['organisation'],
                    'position' => $attendee['position'],
                    'status' => $this->registrationStatus($attendeeNumber),
                    'registered_at' => $eventStart->copy()->subDays(14)->addMinutes($attendeeNumber),
                ]
            );

            foreach ($sessions as $sessionOffset => $session) {
                if ($this->registeredAttends($attendeeNumber, $sessionOffset, $sessionCount)) {
                    $this->createAttendance($session, $attendee, $location);
                }
            }
        }

        // Walk-ins are represented only by session attendance; they have no Registration row.
        foreach ($sessions as $sessionOffset => $session) {
            $walkInCount = $isLargeEvent ? 6 : (($index + $sessionOffset) % 3 === 0 ? 2 : 1);

            for ($walkInNumber = 1; $walkInNumber <= $walkInCount; $walkInNumber++) {
                $walkInOrdinal = $attendeeCount + (($sessionOffset + 1) * 10) + $walkInNumber;
                $this->createAttendance(
                    $session,
                    $this->attendee($index, $walkInOrdinal, true),
                    $location
                );
            }
        }
    }

    private function eventStatus(int $index): string
    {
        return match ($index % 4) {
            0 => 'completed',
            1 => 'published',
            2 => 'draft',
            default => 'cancelled',
        };
    }

    private function eventStart(int $index, string $status, bool $isLargeEvent): Carbon
    {
        if ($isLargeEvent) {
            return now()->startOfDay()->addDays(10)->setTime(9, 0);
        }

        $date = now()->startOfDay();
        if ($status === 'completed') {
            $date->subDays(14 + $index);
        } elseif ($status === 'cancelled') {
            $date->addDays(($index % 9) - 4);
        } else {
            $date->addDays(2 + ($index % 20));
        }

        return $date->setTime(9, 0);
    }

    private function registrationStatus(int $attendeeNumber): string
    {
        return match ($attendeeNumber % 10) {
            0, 1 => 'pending',
            2 => 'rejected',
            default => 'approved',
        };
    }

    private function registeredAttends(int $attendeeNumber, int $sessionOffset, int $sessionCount): bool
    {
        // Rejected pre-registrations do not check in; pending registrations can still attend.
        if ($attendeeNumber % 10 === 2) {
            return false;
        }

        if ($sessionOffset === 0) {
            return $attendeeNumber % 4 !== 0;
        }

        if ($sessionCount === 2) {
            return $sessionOffset === 1 && $attendeeNumber % 2 === 0;
        }

        return match ($sessionOffset) {
            1 => $attendeeNumber % 4 === 0 || $attendeeNumber % 7 === 0,
            2 => $attendeeNumber % 5 === 0 || $attendeeNumber % 11 === 0,
            default => false,
        };
    }

    private function attendee(int $eventIndex, int $attendeeNumber, bool $walkIn = false): array
    {
        $suffix = sprintf('%02d-%03d', $eventIndex, $attendeeNumber);
        $name = $walkIn ? 'Walk-in Guest ' . $suffix : 'Stress Attendee ' . $suffix;

        return [
            'name' => $name,
            'email' => sprintf('stress-%s%s@example.test', $walkIn ? 'walkin-' : 'guest-', $suffix),
            // Unique per person within an event, canonical Malaysian international format.
            'phone' => '+601' . str_pad((string) (($eventIndex * 100000 + $attendeeNumber) % 100000000), 8, '0', STR_PAD_LEFT),
            'organisation' => $this->organisations()[$attendeeNumber % count($this->organisations())],
            'position' => $this->positions()[$attendeeNumber % count($this->positions())],
        ];
    }

    private function createAttendance(EventSession $session, array $attendee, Location $location): void
    {
        Attendance::firstOrCreate(
            ['session_id' => $session->id, 'email' => $attendee['email']],
            [
                'user_id' => null,
                'full_name' => $attendee['name'],
                'position' => $attendee['position'],
                'unit' => $attendee['organisation'],
                'phone' => $attendee['phone'],
                'verification_method' => 'pin',
                'location_id' => $location->id,
                'clock_in_at' => $session->starts_at->copy()->addMinutes(5 + ($session->id % 55)),
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'accuracy' => 12.5,
                'distance_from_site' => 0,
                'within_site' => true,
            ]
        );
    }

    private function availableEventPin(int $eventIndex, string $eventName): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $candidateNumber = $eventIndex;

        do {
            $number = $candidateNumber++;
            $pin = '';
            for ($digit = 0; $digit < 4; $digit++) {
                $pin .= $alphabet[$number % strlen($alphabet)];
                $number = intdiv($number, strlen($alphabet));
            }
        } while (Event::query()->where('pin', $pin)->where('name', '!=', $eventName)->exists());

        return $pin;
    }

    private function organisations(): array
    {
        return [
            'Kencana Digital Sdn Bhd',
            'Meridian Learning Centre',
            'Northstar Systems Malaysia',
            'Rakyat Innovation Lab',
            'Cendekia Skills Institute',
            'Bumi Tech Services',
            'Pioneer Business Group',
            'Lumen Creative Studio',
        ];
    }

    private function positions(): array
    {
        return ['Software Engineer', 'Project Coordinator', 'Intern', 'Training Executive', 'Analyst', 'Team Lead'];
    }
}
