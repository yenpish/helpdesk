<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Feedback;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Public feedback form. Login is not required.
     */
    public function create(Event $event): View
    {
        abort_unless(
            $this->feedbackWindowOpen($event),
            403,
            'Feedback is available only within 7 days after the event ends.'
        );

        return view('feedback.create', compact('event'));
    }

    /**
     * Save feedback from a participant.
     */
    public function store(
        Request $request,
        Event $event
    ): RedirectResponse {
        abort_unless(
            $this->feedbackWindowOpen($event),
            403,
            'Feedback is available only within 7 days after the event ends.'
        );

        $request->merge([
            'participant_name' => trim(
                (string) $request->input('participant_name')
            ),
            'participant_email' => strtolower(
                trim((string) $request->input('participant_email'))
            ),
        ]);

        $validated = $request->validate([
            'participant_name' => [
                'required',
                'string',
                'max:255',
            ],
            'participant_email' => [
                'required',
                'email',
                'max:255',
            ],
            'rating' => [
                'required',
                'integer',
                'min:1',
                'max:5',
            ],
            'comments' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $email = $validated['participant_email'];

        // Check case-insensitively so differently capitalized
        // versions of the same email cannot submit twice.
        $alreadySubmitted = Feedback::where('event_id', $event->id)
            ->whereRaw('LOWER(participant_email) = ?', [$email])
            ->exists();

        if ($alreadySubmitted) {
            return back()
                ->withInput()
                ->withErrors([
                    'participant_email' =>
                        'This email has already submitted feedback for this event.',
                ]);
        }

        try {
            Feedback::create([
                'event_id' => $event->id,
                'participant_name' => $validated['participant_name'],
                'participant_email' => $email,
                'rating' => $validated['rating'],
                'comments' => $validated['comments'] ?? null,
            ]);
        } catch (QueryException $e) {
            // The database unique index also protects against
            // two duplicate submissions arriving simultaneously.
            $alreadySubmitted = DB::table('feedback')
                ->where('event_id', $event->id)
                ->whereRaw('LOWER(participant_email) = ?', [$email])
                ->exists();

            if ($alreadySubmitted) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'participant_email' =>
                            'This email has already submitted feedback for this event.',
                    ]);
            }

            throw $e;
        }

        return redirect()
            ->to(route('registrations.public-index') . '#past-events')
            ->with('success', 'Thank you for your feedback!');
    }

    /**
     * Feedback results for the event's organizer or an admin.
     */
    public function index(Event $event): View
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