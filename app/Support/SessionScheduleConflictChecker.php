<?php

namespace App\Support;

use App\Models\EventSession;
use Carbon\CarbonInterface;

class SessionScheduleConflictChecker
{
    /**
     * Return a readable conflict message, or null when all proposed intervals are available.
     *
     * Each schedule must contain `name`, `starts_at`, and `ends_at` values.
     */
    public function conflictMessage(
        int $locationId,
        array $schedules,
        ?int $exceptEventId = null,
        ?int $exceptSessionId = null
    ): ?string {
        $accepted = [];

        foreach ($schedules as $schedule) {
            $startsAt = $schedule['starts_at'];
            $endsAt = $schedule['ends_at'];

            if (!$startsAt instanceof CarbonInterface || !$endsAt instanceof CarbonInterface) {
                continue;
            }

            $conflict = EventSession::query()
                ->with('event:id,name,organizer_id')
                ->whereHas('event', fn ($query) => $query->where('location_id', $locationId))
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when($exceptEventId !== null, fn ($query) => $query->where('event_id', '!=', $exceptEventId))
                ->when($exceptSessionId !== null, fn ($query) => $query->where('event_sessions.id', '!=', $exceptSessionId))
                ->first();

            if ($conflict) {
                return $this->messageFor($conflict);
            }

            foreach ($accepted as $acceptedSchedule) {
                if ($acceptedSchedule['starts_at']->lt($endsAt) && $acceptedSchedule['ends_at']->gt($startsAt)) {
                    $eventName = $schedule['event_name'] ?? null;
                    $locationText = $eventName ? ' for “' . $eventName . '”' : '';

                    return sprintf(
                        '%s overlaps %s%s at this location. Choose a different time or location.',
                        $schedule['name'],
                        $acceptedSchedule['name'],
                        $locationText
                    );
                }
            }

            $accepted[] = $schedule;
        }

        return null;
    }

    private function messageFor(EventSession $conflict): string
    {
        $conflictingEvent = $conflict->event;
        $canIdentify = auth()->check() && (
            auth()->user()->role === 'admin'
            || $conflictingEvent?->organizer_id === auth()->id()
        );

        if ($canIdentify) {
            return sprintf(
                'This time overlaps %s in %s. Choose a different time or location.',
                $conflict->name,
                $conflictingEvent?->name ?? 'another event'
            );
        }

        return 'A session at this location already overlaps this time. Choose a different time or location.';
    }
}
