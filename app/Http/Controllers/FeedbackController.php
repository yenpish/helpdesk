<?php

namespace App\Http\Controllers;

use App\Mail\FeedbackInvitationMail;
use App\Models\Event;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Services\FeedbackInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    /**
     * Feedback is accepted from the event's end time
     * until 7 days after it ends.
     */
    private function feedbackWindowOpen(Event $event): bool
    {
        return $event->ends_at !== null
            && $event->ends_at->lte(now())
            && $event->ends_at->gte(now()->subDays(7));
    }

    public function sendInvitations(
        Event $event,
        FeedbackInvitationService $service
    ): RedirectResponse {
        $this->authorizeEventAccess($event);

        abort_unless(
            $this->feedbackWindowOpen($event),
            403,
            'Invitations can only be sent within 7 days after the event ends.'
        );

        $summary = $service->sendForEvent(
            $event,
            function ($invitation, $token) {
                Mail::to($invitation->participant_email)->send(
                    new FeedbackInvitationMail($invitation, $token)
                );
            }
        );
        $this->auditInvitationAction($event, 'sent', $summary);

        return back()->with(
            'success',
            $this->invitationSummaryMessage($summary, 'send')
        );
    }

    public function retryFailedInvitations(
        Event $event,
        FeedbackInvitationService $service
    ): RedirectResponse {
        $this->authorizeEventAccess($event);

        abort_unless(
            $this->feedbackWindowOpen($event),
            403,
            'Invitations can only be retried within 7 days after the event ends.'
        );

        $summary = $service->retryFailed(
            $event,
            function ($invitation, $token) {
                Mail::to($invitation->participant_email)->send(
                    new FeedbackInvitationMail($invitation, $token)
                );
            }
        );
        $this->auditInvitationAction($event, 'retried', $summary);

        return back()->with('success', $this->invitationSummaryMessage($summary, 'retry'));
    }

    private function authorizeEventAccess(Event $event): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null
            && (
                $user->role === 'admin'
                || (int) $event->organizer_id === (int) $user->id
            ),
            403
        );
    }

    /** @param array{accepted:int,failed:int,skipped:int} $summary */
    private function invitationSummaryMessage(array $summary, string $action): string
    {
        $parts = [];
        if ($summary['accepted'] > 0) {
            $parts[] = $summary['accepted'].' sent';
        }
        if ($summary['failed'] > 0) {
            $parts[] = $summary['failed'].' failed';
        }
        if ($summary['skipped'] > 0) {
            $parts[] = $summary['skipped'].' left unchanged';
        }

        if ($parts === []) {
            return $action === 'retry'
                ? 'There are no failed invitations to retry.'
                : 'No new invitations were needed; valid invitations were left unchanged.';
        }

        return 'Feedback emails: '.implode(', ', $parts).'.';
    }

    /** One action-level audit record; never include recipients, tokens or transport errors. */
    private function auditInvitationAction(Event $event, string $action, array $summary): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => Event::class,
            'auditable_id' => $event->id,
            'description' => $action === 'sent'
                ? 'Feedback invitations sent.'
                : 'Failed feedback invitations retried.',
            'old_values' => null,
            'new_values' => [
                'accepted' => $summary['accepted'],
                'failed' => $summary['failed'],
                'skipped' => $summary['skipped'],
            ],
        ]);
    }

    public function invitationCreate(
        Event $event,
        string $token
    ): View {
        $invitation = FeedbackInvitation::where('token_hash', hash('sha256', $token))
            ->where('event_id', $event->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return view('feedback.create', compact('event', 'invitation', 'token'));
    }

    public function invitationStore(
        Request $request,
        Event $event,
        string $token
    ): \Illuminate\Http\Response {
        $invitation = FeedbackInvitation::where('token_hash', hash('sha256', $token))
            ->where('event_id', $event->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($invitation, $validated) {
            $lockedInvitation = FeedbackInvitation::whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $lockedInvitation->used_at !== null
                || $lockedInvitation->expires_at->lte(now()),
                403,
                'This feedback invitation has expired or has already been used.'
            );

            Feedback::create([
                'event_id' => $lockedInvitation->event_id,
                'participant_name' => $lockedInvitation->participant_name,
                'participant_email' => $lockedInvitation->participant_email,
                'rating' => $validated['rating'],
                'comments' => $validated['comments'] ?? null,
            ]);

            $lockedInvitation->update([
                'used_at' => now(),
            ]);
        });

        return response()->view('feedback.thank-you', compact('event'));
    }

    /**
     * Feedback results for the event's organizer or an admin.
     */
    public function index(Event $event): View
    {
        $this->authorizeEventAccess($event);

        $baseQuery = Feedback::where('event_id', $event->id);

        $totalFeedback = (clone $baseQuery)->count();

        $averageRating = (clone $baseQuery)->avg('rating');

        $ratingCounts = (clone $baseQuery)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $feedback = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('feedback.index', compact(
            'event',
            'feedback',
            'totalFeedback',
            'averageRating',
            'ratingCounts'
        ));
    }
}
