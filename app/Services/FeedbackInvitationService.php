<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FeedbackInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeedbackInvitationService
{
    public function generateForEvent(Event $event, callable $deliver): int
    {
        $created = 0;

        $attendances = $event->attendances()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->get()
            ->unique(fn ($attendance) => strtolower(trim($attendance->email)));

        foreach ($attendances as $attendance) {
            $email = strtolower(trim($attendance->email));

            $existingInvitation = FeedbackInvitation::where('event_id', $event->id)
                ->where(function ($query) use ($attendance, $email) {
                    $query->where('attendance_id', $attendance->id)
                        ->orWhere('participant_email', $email);
                })
                ->first();

            // Do not invite someone who has already submitted feedback.
            if ($existingInvitation?->used_at !== null) {
                continue;
            }

            $rawToken = Str::random(64);

            DB::transaction(function () use (
                $event,
                $attendance,
                $email,
                $rawToken,
                $deliver,
                $existingInvitation
            ) {
                if ($existingInvitation) {
                    // Refresh an existing unused invitation.
                    $existingInvitation->update([
                        'participant_name' => $attendance->full_name,
                        'participant_email' => $email,
                        'token_hash' => hash('sha256', $rawToken),
                        'expires_at' => now()->addDays(7),
                    ]);

                    $invitation = $existingInvitation->fresh();
                } else {
                    $invitation = FeedbackInvitation::create([
                        'event_id' => $event->id,
                        'attendance_id' => $attendance->id,
                        'participant_name' => $attendance->full_name,
                        'participant_email' => $email,
                        'token_hash' => hash('sha256', $rawToken),
                        'expires_at' => now()->addDays(7),
                    ]);
                }

                $deliver($invitation, $rawToken);
            });

            $created++;
        }

        return $created;
    }
}
