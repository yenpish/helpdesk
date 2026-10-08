@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <header class="page-header mb-3">
            <div class="page-heading">
                <h1>{{ $event->name }}</h1>
                <p class="text-muted mb-0">Attendance overview</p>
            </div>
            <div class="d-flex gap-2 flex-wrap header-actions">
                <a href="{{ route('events.show', $event) }}" class="btn btn-secondary">Back to Event</a>
            </div>
        </header>

        <dl class="event-summary-row mb-3" aria-label="Event attendance summary">
            <div><dt>Pre-registered</dt><dd>{{ $preRegisteredCount }}</dd></div>
            <div><dt>Attended at least one session</dt><dd>{{ $attendedAtLeastOnceCount }}</dd></div>
            <div><dt>Attended all sessions</dt><dd>{{ $attendedAllSessionsCount }}</dd></div>
            <div><dt>Total attendance records</dt><dd>{{ $totalAttendanceRecords }}</dd></div>
        </dl>

        @if($unregisteredAttendanceRecords > 0)
            <p class="text-muted mb-3">Unregistered attendance records: {{ $unregisteredAttendanceRecords }}</p>
        @endif

        <section class="card mb-3" aria-labelledby="session-summary-heading">
            <div class="card-header" id="session-summary-heading">Session summary</div>
            <div class="card-body p-3">
                @if($sessions->isEmpty())
                    <p class="text-muted mb-0">No sessions have been created for this event.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Session</th><th>Schedule</th><th>Attended</th><th>Attendance rate</th></tr>
                            </thead>
                            <tbody>
                                @foreach($sessionSummaries as $session)
                                    <tr>
                                        <td>{{ $session['name'] }}</td>
                                        <td>
                                            {{ $session['starts_at']?->format('d M Y, h:i A') ?? '—' }}
                                            – {{ $session['ends_at']?->format('d M Y, h:i A') ?? '—' }}
                                        </td>
                                        <td>{{ $session['attended_count'] }}</td>
                                        <td>{{ $session['attendance_rate'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        @if($sessions->isNotEmpty())
            <section class="card" aria-labelledby="attendance-matrix-heading">
                <div class="card-header" id="attendance-matrix-heading">Pre-registered attendee attendance</div>
                <div class="card-body p-3">
                    @if($preRegisteredCount === 0)
                        <p class="text-muted mb-0">There are no pre-registered attendees to display.</p>
                    @else
                        <form method="GET" action="{{ route('events.attendance-overview', $event) }}" class="events-filter-form mb-3">
                            <div class="events-search-field">
                                <label for="attendance-overview-search" class="form-label">Search pre-registered attendees</label>
                                <input
                                    id="attendance-overview-search"
                                    type="search"
                                    name="search"
                                    value="{{ $search }}"
                                    class="form-control"
                                    placeholder="Name, email, organisation..."
                                >
                            </div>
                            <div class="events-sort-field d-flex align-items-end">
                                <button type="submit" class="btn btn-secondary">Search</button>
                            </div>
                        </form>

                        @if($attendees->isEmpty())
                            <p class="text-muted mb-0">No pre-registered attendees match this search.</p>
                        @else
                            <div class="table-responsive" aria-label="Attendance matrix by session">
                                <table class="table table-hover mb-0" style="min-width: max-content;">
                                    <thead>
                                        <tr>
                                            <th scope="col">Name</th>
                                            <th scope="col">Organisation</th>
                                            @foreach($sessions as $session)
                                                <th scope="col">
                                                    {{ $session->name }}
                                                    <span class="d-block text-muted small">{{ $session->starts_at?->format('d M Y') ?? 'Date unavailable' }}</span>
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($attendees as $attendee)
                                            <tr>
                                                <td>{{ $attendee['name'] ?: '—' }}</td>
                                                <td>{{ $attendee['organisation'] ?: '—' }}</td>
                                                @foreach($sessions as $session)
                                                    @php($attendance = $attendee['sessions'][$session->id])
                                                    <td>
                                                        @if($attendance['attended'])
                                                            Attended{{ $attendance['time'] ? ' ' . $attendance['time'] : '' }}
                                                        @else
                                                            <span class="text-muted">Not attended</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($attendees->hasPages())
                                <div class="mt-3">
                                    @include('components.pagination-row', ['paginator' => $attendees, 'ariaLabel' => 'Attendance overview pages'])
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
