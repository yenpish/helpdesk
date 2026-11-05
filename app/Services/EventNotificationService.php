<?php

namespace App\Services;

use App\Mail\EventParticipantNotification;
use App\Models\Event;
use App\Models\EventNotificationDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class EventNotificationService
{
    public function sendForEvent(Event $event, string $type, ?string $key = null): int
    {
        $key ??= (string) Str::uuid();
        $created = 0;

        foreach ($this->eligibleRegistrations($event, $type) as $registration) {
            $email = strtolower(trim((string) ($registration->guest_email ?: $registration->user?->email)));
            if ($email === '') {
                continue;
            }

            $delivery = EventNotificationDelivery::firstOrCreate(
                [
                    'event_id' => $event->id,
                    'registration_id' => $registration->id,
                    'type' => $type,
                    'notification_key' => $key,
                ],
                ['recipient_email' => $email, 'status' => 'pending']
            );

            if (!$delivery->wasRecentlyCreated) {
                continue;
            }

            $created++;
            $this->attempt($delivery, $event);
        }

        return $created;
    }

    public function retryFailed(Event $event, string $key): int
    {
        $deliveries = $event->notificationDeliveries()
            ->where('notification_key', $key)
            ->where('status', 'failed')
            ->get();

        foreach ($deliveries as $delivery) {
            $this->attempt($delivery, $event);
        }

        return $deliveries->count();
    }

    public function sendDueReminders(): int
    {
        $now = now();
        $sent = 0;
        Event::query()
            ->where('status', 'published')
            ->where('starts_at', '>', $now)
            ->where(function ($query) use ($now): void {
                $query->where(function ($query) use ($now): void {
                    $query->where('reminder_24h', true)
                        ->where('starts_at', '<=', $now->copy()->addHours(24))
                        ->where('starts_at', '>', $now->copy()->addHours(23));
                })->orWhere(function ($query) use ($now): void {
                    $query->where('reminder_1h', true)
                        ->where('starts_at', '<=', $now->copy()->addHour())
                        ->where('starts_at', '>', $now->copy()->addMinutes(50));
                });
            })
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->each(function (Event $event) use (&$sent): void {
                foreach ([24, 1] as $hours) {
                    if (!$event->{'reminder_' . $hours . 'h'}) {
                        continue;
                    }
                    $dueAt = $event->starts_at->copy()->subHours($hours);
                    if ($dueAt->lte(now()) && $event->starts_at->isFuture()) {
                        $type = 'reminder_' . $hours . 'h';
                        $key = hash('sha256', $type . '|' . $event->starts_at->toIso8601String());
                        $sent += $this->sendForEvent($event, $type, $key);
                    }
                }
            });

        return $sent;
    }

    private function eligibleRegistrations(Event $event, string $type): Collection
    {
        $query = $event->registrations()
            ->with('user')
            ->orderBy('id');

        // Pending requests are registered participants for event notices;
        // reminders are limited to people approved to attend.
        $query->whereIn('status', str_starts_with($type, 'reminder_')
            ? ['approved']
            : ['pending', 'approved']);

        return $query->get();
    }

    private function attempt(EventNotificationDelivery $delivery, Event $event): void
    {
        $delivery->forceFill([
            'status' => 'pending',
            'attempt_count' => $delivery->attempt_count + 1,
            'last_error' => null,
            'last_attempted_at' => now(),
        ])->save();

        try {
            Mail::to($delivery->recipient_email)->send(new EventParticipantNotification($event, $delivery->type));
        } catch (Throwable) {
            // Keep transport details out of the database/UI; they can contain sensitive provider data.
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => 'Mail transport failed. Retry is available.',
            ])->save();
            return;
        }

        // Keep this outside the transport catch: if persisting acceptance fails,
        // leave the outcome pending instead of marking a possibly sent email failed.
        // A successful transport hand-off is not proof of inbox delivery.
        $delivery->forceFill(['status' => 'accepted'])->save();
    }
}
