<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\FeedbackInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FeedbackInvitationService
{
    /** @return array{accepted:int,failed:int,skipped:int} */
    public function sendForEvent(Event $event, callable $deliver): array
    {
        return $this->processAttendances($event, $deliver);
    }

    /** @return array{accepted:int,failed:int,skipped:int} */
    public function retryFailed(Event $event, callable $deliver): array
    {
        $summary = $this->emptySummary();
        $invitations = $event->feedbackInvitations()
            ->where('status', 'failed')
            ->whereNull('used_at')
            ->with('attendance')
            ->orderBy('id')
            ->get();

        foreach ($invitations as $invitation) {
            if ($invitation->attendance === null) {
                $summary['skipped']++;
                continue;
            }

            $this->processAttendance($event, $invitation->attendance, $deliver, $summary, true);
        }

        return $summary;
    }

    /** @param array{accepted:int,failed:int,skipped:int} $summary */
    private function processAttendances(Event $event, callable $deliver): array
    {
        $summary = $this->emptySummary();
        $attendances = $event->attendances()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->get()
            ->unique(fn (Attendance $attendance) => $this->normalizeEmail($attendance->email));

        foreach ($attendances as $attendance) {
            $this->processAttendance($event, $attendance, $deliver, $summary, false);
        }

        return $summary;
    }

    /** @param array{accepted:int,failed:int,skipped:int} $summary */
    private function processAttendance(
        Event $event,
        Attendance $attendance,
        callable $deliver,
        array &$summary,
        bool $retryOnly = false
    ): void {
        $email = $this->normalizeEmail($attendance->email);
        if ($email === '') {
            $summary['skipped']++;
            return;
        }

        $rawToken = Str::random(64);

        $prepared = DB::transaction(function () use ($event, $attendance, $email, $rawToken, $retryOnly) {
            // Serialize invitation changes for an event, including concurrent Send clicks.
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            $invitation = FeedbackInvitation::where('event_id', $event->id)
                ->where('attendance_id', $attendance->id)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                $invitation = FeedbackInvitation::where('event_id', $event->id)
                    ->whereRaw('LOWER(TRIM(participant_email)) = ?', [$email])
                    ->lockForUpdate()
                    ->first();
            }

            if ($invitation?->used_at !== null) {
                return null;
            }

            $emailChanged = $invitation !== null
                && $this->normalizeEmail($invitation->participant_email) !== $email;

            if ($emailChanged) {
                $emailConflict = FeedbackInvitation::where('event_id', $event->id)
                    ->whereRaw('LOWER(TRIM(participant_email)) = ?', [$email])
                    ->where('id', '!=', $invitation->id)
                    ->exists();

                if ($emailConflict) {
                    return null;
                }
            }

            $isValidUnused = $invitation !== null && $invitation->expires_at->isFuture();
            if ($retryOnly) {
                if ($invitation === null || $invitation->status !== 'failed') {
                    return null;
                }
            } elseif ($isValidUnused && !$emailChanged) {
                // Do not rotate a valid link or resend it. Failed links have an explicit retry action.
                return null;
            }

            $attributes = [
                'participant_name' => $attendance->full_name,
                'participant_email' => $email,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addDays(7),
                'status' => 'pending',
            ];

            if ($invitation === null) {
                $invitation = FeedbackInvitation::create($attributes + [
                    'event_id' => $event->id,
                    'attendance_id' => $attendance->id,
                ]);
            } else {
                $invitation->update($attributes);
            }

            return [$invitation->id, $rawToken];
        });

        if ($prepared === null) {
            $summary['skipped']++;
            return;
        }

        [$invitationId, $token] = $prepared;
        try {
            $deliver(FeedbackInvitation::findOrFail($invitationId), $token);
            FeedbackInvitation::whereKey($invitationId)
                ->whereNull('used_at')
                ->update(['status' => 'accepted']);
            $summary['accepted']++;
        } catch (Throwable) {
            FeedbackInvitation::whereKey($invitationId)
                ->whereNull('used_at')
                ->update(['status' => 'failed']);
            $summary['failed']++;
        }
    }

    /** @return array{accepted:int,failed:int,skipped:int} */
    private function emptySummary(): array
    {
        return ['accepted' => 0, 'failed' => 0, 'skipped' => 0];
    }

    private function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim($email ?? ''));
    }
}
