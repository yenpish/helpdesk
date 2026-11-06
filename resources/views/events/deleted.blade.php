@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>Deleted events &amp; sessions</h1>
                <p>Restore items or permanently remove ones with no saved records.</p>
            </div>
            <a href="{{ route('events.index') }}" class="btn btn-secondary">Back to Events</a>
        </header>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="mb-4" aria-labelledby="deleted-events-heading">
            <h2 id="deleted-events-heading" class="h4 mb-3">Events</h2>
            @if ($events->isEmpty())
                <p class="text-muted">No deleted events.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle deleted-events-table">
                        <thead><tr><th>Event</th><th>Deleted</th><th>Saved records</th><th class="table-actions">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr>
                                    <td><strong>{{ $event->name }}</strong><div class="small text-muted">{{ $event->organizer?->name ?? 'No organizer' }}</div></td>
                                    <td class="table-cell-nowrap">{{ $event->deleted_at?->format('d M Y, h:i A') }}</td>
                                    <td class="small">{{ $event->attendance_count }} check-ins · {{ $event->registrations_count }} pre-registrations · {{ $event->feedback_count }} feedback</td>
                                    <td class="table-actions">
                                        <form method="POST" action="{{ route('events.deleted.restore', $event) }}" class="inline-form">
                                            @csrf
                                            <button class="btn btn-secondary btn-sm" type="submit">Restore</button>
                                        </form>
                                        @if ($event->can_permanently_delete)
                                            <form method="POST" action="{{ route('events.deleted.permanent', $event) }}" class="inline-form" onsubmit="return confirm('Permanently delete event ' + @js($event->name) + '? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-sm" type="submit">Delete permanently</button>
                                            </form>
                                        @else
                                            <span class="small text-muted">History kept</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section aria-labelledby="deleted-sessions-heading">
            <h2 id="deleted-sessions-heading" class="h4 mb-3">Sessions</h2>
            @if ($sessions->isEmpty())
                <p class="text-muted">No individually deleted sessions.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle deleted-sessions-table">
                        <thead><tr><th>Session</th><th>Event</th><th>Deleted</th><th class="table-numeric">Check-ins</th><th class="table-actions">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                <tr>
                                    <td>{{ $session->name }}</td>
                                    <td>{{ $session->event?->name ?? 'Deleted event' }}@if ($session->event?->trashed())<div class="small text-muted">Restore the event first</div>@endif</td>
                                    <td class="table-cell-nowrap">{{ $session->deleted_at?->format('d M Y, h:i A') }}</td>
                                    <td class="table-numeric">{{ $session->attendances_count }}</td>
                                    <td class="table-actions">
                                        @if (!$session->event?->trashed())
                                            <form method="POST" action="{{ route('events.deleted.sessions.restore', $session) }}" class="inline-form">
                                                @csrf
                                                <button class="btn btn-secondary btn-sm" type="submit">Restore</button>
                                            </form>
                                            @if ($session->can_permanently_delete)
                                                <form method="POST" action="{{ route('events.deleted.sessions.permanent', $session) }}" class="inline-form" onsubmit="return confirm('Permanently delete session ' + @js($session->name) + '? This cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-danger btn-sm" type="submit">Delete permanently</button>
                                                </form>
                                            @else
                                                <span class="small text-muted">Check-in history kept</span>
                                            @endif
                                        @else
                                            <span class="small text-muted">Restore the event first</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
