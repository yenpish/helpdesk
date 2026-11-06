<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventNotificationDelivery;
use App\Models\EventSession;
use App\Models\Feedback;
use App\Models\FeedbackInvitation;
use App\Models\Registration;
use App\Support\SessionScheduleConflictChecker;
use App\Support\EncryptedRouteIdentifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeletedItemsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $ownerScope = static function ($query) use ($user): void {
            if ($user->role !== 'admin') {
                $query->where('organizer_id', $user->id);
            }
        };

        $events = Event::onlyTrashed()
            ->where($ownerScope)
            ->with('organizer:id,name')
            ->withCount([
                'sessions as session_count' => fn ($query) => $query->withTrashed(),
                'attendances as attendance_count',
                'registrations',
                'feedback',
                'feedbackInvitations',
                'notificationDeliveries',
            ])
            ->orderByDesc('deleted_at')
            ->get()
            ->each(function (Event $event): void {
                $event->can_permanently_delete = $event->attendance_count === 0
                    && $event->registrations_count === 0
                    && $event->feedback_count === 0
                    && $event->feedback_invitations_count === 0
                    && $event->notification_deliveries_count === 0;
            });

        $sessions = EventSession::onlyTrashed()
            ->whereHas('event', function ($query) use ($user): void {
                $query->withTrashed();
                if ($user->role !== 'admin') {
                    $query->where('organizer_id', $user->id);
                }
            })
            ->with(['event' => fn ($query) => $query->withTrashed()->select('id', 'name', 'organizer_id', 'deleted_at')])
            ->withCount('attendances')
            ->orderByDesc('deleted_at')
            ->get()
            ->each(function (EventSession $session): void {
                $event = $session->event;
                $remainingSessions = $event?->sessions()->withTrashed()->count() ?? 0;
                $hasRegistrations = $event?->registrations()->exists() ?? false;
                $session->can_permanently_delete = $session->attendances_count === 0
                    && !($remainingSessions <= 1 && $hasRegistrations);
            });

        return view('events.deleted', compact('events', 'sessions'));
    }

    public function restoreEvent(string $eventId): RedirectResponse
    {
        $id = EncryptedRouteIdentifier::decode($eventId, Event::class);
        abort_if($id === null, 404);
        $event = Event::withTrashed()->findOrFail($id);
        $this->authorizeEvent($event);
        abort_unless($event->trashed(), 404);

        $restoreError = DB::transaction(function () use ($event): ?string {
            $lockedEvent = Event::withTrashed()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (!$lockedEvent->trashed()) {
                return 'This event has already been restored.';
            }

            $sessions = EventSession::query()
                ->where('event_id', $lockedEvent->id)
                ->orderBy('starts_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($sessions as $session) {
                if (
                    $lockedEvent->starts_at === null
                    || $lockedEvent->ends_at === null
                    || $session->starts_at === null
                    || $session->ends_at === null
                    || !$session->starts_at->lt($session->ends_at)
                    || $session->starts_at->lt($lockedEvent->starts_at)
                    || $session->ends_at->gt($lockedEvent->ends_at)
                ) {
                    return 'This event has a session outside its schedule. Update the event dates before restoring it.';
                }
            }

            if ($lockedEvent->location_id !== null && $sessions->isNotEmpty()) {
                $conflict = app(SessionScheduleConflictChecker::class)->conflictMessage(
                    (int) $lockedEvent->location_id,
                    $sessions->map(fn (EventSession $session) => [
                        'name' => $session->name,
                        'event_name' => $lockedEvent->name,
                        'starts_at' => $session->starts_at,
                        'ends_at' => $session->ends_at,
                    ])->all(),
                    $lockedEvent->id
                );

                if ($conflict !== null) {
                    return $conflict.' Resolve the conflict before restoring this event.';
                }
            }

            $deletedAt = $lockedEvent->deleted_at?->toISOString();
            $lockedEvent->restore();
            $this->audit('restored', $lockedEvent, 'Event restored.', ['deleted_at' => $deletedAt]);

            return null;
        });

        if ($restoreError !== null) {
            return back()->withErrors(['restore_event' => $restoreError]);
        }

        return back()->with('success', 'Event restored. Its active sessions are available again.');
    }

    public function permanentlyDeleteEvent(string $eventId): RedirectResponse
    {
        $id = EncryptedRouteIdentifier::decode($eventId, Event::class);
        abort_if($id === null, 404);
        $event = Event::withTrashed()->findOrFail($id);
        $this->authorizeEvent($event);
        abort_unless($event->trashed(), 404);

        $deleted = DB::transaction(function () use ($event): bool {
            $lockedEvent = Event::withTrashed()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $sessionIds = EventSession::withTrashed()
                ->where('event_id', $lockedEvent->id)
                ->lockForUpdate()
                ->pluck('id');
            $hasHistory = Attendance::whereIn('session_id', $sessionIds)->exists()
                || Registration::where('event_id', $lockedEvent->id)->exists()
                || Feedback::where('event_id', $lockedEvent->id)->exists()
                || FeedbackInvitation::where('event_id', $lockedEvent->id)->exists()
                || EventNotificationDelivery::where('event_id', $lockedEvent->id)->exists();

            if ($hasHistory) {
                return false;
            }

            $this->audit('permanently_deleted', $lockedEvent, 'Event permanently deleted.', [
                'name' => $lockedEvent->name,
                'status' => $lockedEvent->status,
                'starts_at' => $lockedEvent->starts_at?->toISOString(),
                'ends_at' => $lockedEvent->ends_at?->toISOString(),
                'organizer_id' => $lockedEvent->organizer_id,
            ]);
            $lockedEvent->forceDelete();
            return true;
        });

        if (!$deleted) {
            return back()->withErrors([
                'permanent_delete' => 'This event has saved records and cannot be permanently deleted. Restore it to keep using it.',
            ]);
        }

        return back()->with('success', 'Event permanently deleted. Its audit history remains available.');
    }

    public function restoreSession(string $sessionId): RedirectResponse
    {
        $id = EncryptedRouteIdentifier::decode($sessionId, EventSession::class);
        abort_if($id === null, 404);
        $session = EventSession::withTrashed()->with(['event' => fn ($query) => $query->withTrashed()])->findOrFail($id);
        $event = $session->event;
        abort_if($event === null, 404);
        $this->authorizeEvent($event);
        abort_unless($session->trashed(), 404);

        $restoreError = DB::transaction(function () use ($event, $session): ?string {
            $lockedEvent = Event::withTrashed()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $lockedSession = EventSession::withTrashed()
                ->where('event_id', $lockedEvent->id)
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (!$lockedSession->trashed()) {
                return 'This session has already been restored.';
            }

            $scheduleError = $this->sessionRestoreError($lockedEvent, $lockedSession);
            if ($scheduleError !== null) {
                return $scheduleError;
            }

            $deletedAt = $lockedSession->deleted_at?->toISOString();
            $lockedSession->restore();
            $this->audit('restored', $lockedSession, 'Session restored.', ['deleted_at' => $deletedAt]);

            return null;
        });

        if ($restoreError !== null) {
            return back()->withErrors(['restore_session' => $restoreError]);
        }

        return back()->with('success', 'Session restored.');
    }

    private function sessionRestoreError(Event $event, EventSession $session): ?string
    {
        if (
            $event->starts_at === null
            || $event->ends_at === null
            || $session->starts_at === null
            || $session->ends_at === null
            || !$session->starts_at->lt($session->ends_at)
            || $session->starts_at->lt($event->starts_at)
            || $session->ends_at->gt($event->ends_at)
        ) {
            return 'This session falls outside the event schedule. Update the event dates before restoring it.';
        }

        if ($event->location_id === null) {
            return null;
        }

        $conflict = app(SessionScheduleConflictChecker::class)->conflictMessage(
            (int) $event->location_id,
            [[
                'name' => $session->name,
                'event_name' => $event->name,
                'starts_at' => $session->starts_at,
                'ends_at' => $session->ends_at,
            ]],
            null,
            $session->id
        );

        if ($conflict !== null) {
            return $conflict.' Edit the conflicting session before restoring this one.';
        }

        if ($event->trashed()) {
            $sameEventConflict = EventSession::query()
                ->where('event_id', $event->id)
                ->where('id', '!=', $session->id)
                ->where('starts_at', '<', $session->ends_at)
                ->where('ends_at', '>', $session->starts_at)
                ->orderBy('starts_at')
                ->orderBy('id')
                ->first();

            if ($sameEventConflict !== null) {
                return 'This session overlaps an active session in the archived event. Resolve the schedule conflict before restoring it.';
            }
        }

        return null;
    }

    public function permanentlyDeleteSession(string $sessionId): RedirectResponse
    {
        $id = EncryptedRouteIdentifier::decode($sessionId, EventSession::class);
        abort_if($id === null, 404);
        $session = EventSession::withTrashed()->with(['event' => fn ($query) => $query->withTrashed()])->findOrFail($id);
        $event = $session->event;
        abort_if($event === null, 404);
        $this->authorizeEvent($event);
        abort_unless($session->trashed(), 404);

        $deletionResult = DB::transaction(function () use ($event, $session): string {
            Event::withTrashed()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $lockedSession = EventSession::withTrashed()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $hasAttendance = $lockedSession->attendances()->exists();
            $isFinalSessionWithRegistrations = $event->sessions()->withTrashed()->count() <= 1
                && $event->registrations()->exists();

            if ($hasAttendance) {
                return 'attendance';
            }
            if ($isFinalSessionWithRegistrations) {
                return 'registration';
            }

            $this->audit('permanently_deleted', $lockedSession, 'Session permanently deleted.', [
                'event_id' => $lockedSession->event_id,
                'name' => $lockedSession->name,
                'starts_at' => $lockedSession->starts_at?->toISOString(),
                'ends_at' => $lockedSession->ends_at?->toISOString(),
            ]);
            $lockedSession->forceDelete();
            return 'deleted';
        });

        if ($deletionResult !== 'deleted') {
            return back()->withErrors([
                'permanent_delete' => $deletionResult === 'attendance'
                    ? 'This session has check-in history and cannot be permanently deleted. Restore it to review those records.'
                    : 'This is the event’s last session and pre-registrations still exist. Restore it or add another session first.',
            ]);
        }

        return back()->with('success', 'Session permanently deleted. Its audit history remains available.');
    }

    private function authorizeEvent(Event $event): void
    {
        if (
            auth()->user()->role !== 'admin'
            && (int) $event->organizer_id !== (int) auth()->id()
        ) {
            abort(403);
        }
    }

    private function audit(string $action, Event|EventSession $model, string $description, ?array $oldValues = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $action === 'restored' ? ['deleted_at' => null] : null,
        ]);
    }
}
