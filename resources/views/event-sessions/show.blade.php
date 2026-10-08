@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <div class="page-header mb-4">
            <div class="page-heading">
                <h1>{{ $eventSession->name }}</h1>
                <p>Session for {{ $event->name }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap header-actions">
                <a href="{{ route('events.event-sessions.live-attendance', [$event, $eventSession]) }}" class="btn btn-primary">Live Attendance</a>
                <a href="{{ route('events.event-sessions.edit', [$event, $eventSession]) }}" class="btn btn-secondary">Edit</a>
                <a href="{{ route('events.event-sessions.index', $event) }}" class="btn btn-secondary">Back to Sessions</a>
                <a href="{{ route('events.show', $event) }}" class="btn btn-secondary">Back to Event</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-3 session-information-card">
            <div class="card-header">Session Information</div>
            <div class="card-body p-3">
                <dl class="event-details-grid session-details-grid mb-0">
                    <div class="event-details-pair">
                        <dt>Event</dt><dd>{{ $event->name }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Name</dt><dd>{{ $eventSession->name }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Description</dt><dd>{{ $eventSession->description ?: '—' }}</dd>
                    </div>
                </dl>

                <div class="event-details-meta session-details-meta">
                    <section class="event-details-meta-group" aria-labelledby="session-schedule-heading">
                        <h3 id="session-schedule-heading">Session schedule</h3>
                        <dl class="mb-0">
                            <div class="event-details-meta-row">
                                <dt>Starts</dt><dd>{{ $eventSession->starts_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                            <div class="event-details-meta-row">
                                <dt>Ends</dt><dd>{{ $eventSession->ends_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="event-details-meta-group" aria-labelledby="session-attendance-window-heading">
                        <h3 id="session-attendance-window-heading">Attendance window</h3>
                        <dl class="mb-0">
                            <div class="event-details-meta-row">
                                <dt>Opens</dt><dd>{{ $eventSession->attendance_opens_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                            <div class="event-details-meta-row">
                                <dt>Closes</dt><dd>{{ $eventSession->attendance_closes_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="event-details-meta-group" aria-labelledby="session-record-heading">
                        <h3 id="session-record-heading">Record history</h3>
                        <dl class="mb-0">
                            <div class="event-details-meta-row">
                                <dt>Created by</dt><dd>{{ $eventSession->createdBy?->name ?? '—' }}</dd>
                            </div>
                            <div class="event-details-meta-row">
                                <dt>Updated by</dt><dd>{{ $eventSession->updatedBy?->name ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>
        </div>

        <section class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Pre-registration &amp; Attendance</span>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('events.event-sessions.attendance.export', [$event, $eventSession]) }}" class="btn btn-secondary btn-sm">Export Session CSV</a>
                    <a href="{{ route('events.registrations.index', $event) }}" class="btn btn-secondary btn-sm">Manage Pre-registrations</a>
                </div>
            </div>
            <div class="card-body p-3">
                <dl class="session-attendance-summary mb-3" aria-label="Session attendance summary">
                    <div><dt>Pre-registered</dt><dd>{{ $registeredCount }}</dd></div>
                    <div><dt>Attended</dt><dd>{{ $attendedCount }}</dd></div>
                    <div><dt>Not attended</dt><dd>{{ $notAttendedCount }}</dd></div>
                    <div><dt>Attendance Rate</dt><dd>{{ $attendanceRate }}%</dd></div>
                </dl>
                <form method="GET" action="{{ route('events.event-sessions.show', [$event, $eventSession]) }}" class="events-filter-form session-attendance-filters mb-3">
                    <div class="events-search-field">
                        <label for="session-attendance-search" class="form-label">Search attendees</label>
                        <input
                            id="session-attendance-search"
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Name, email, phone, organisation..."
                        >
                    </div>
                    <div class="events-sort-field">
                        <label for="session-attendance-status" class="form-label">Status</label>
                        <select id="session-attendance-status" name="status" class="form-select">
                            <option value="" @selected($statusFilter === '')>All statuses</option>
                            <optgroup label="Pre-registration">
                                <option value="pre_registered" @selected($statusFilter === 'pre_registered')>Pre-registered</option>
                                <option value="not_pre_registered" @selected($statusFilter === 'not_pre_registered')>Not pre-registered</option>
                            </optgroup>
                            <optgroup label="Approval">
                                <option value="pending" @selected($statusFilter === 'pending')>Pending</option>
                                <option value="approved" @selected($statusFilter === 'approved')>Approved</option>
                                <option value="rejected" @selected($statusFilter === 'rejected')>Rejected</option>
                                <option value="cancelled" @selected($statusFilter === 'cancelled')>Cancelled</option>
                            </optgroup>
                            <optgroup label="Attendance">
                                <option value="attended" @selected($statusFilter === 'attended')>Attended</option>
                                <option value="not_attended" @selected($statusFilter === 'not_attended')>Not attended</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="events-filter-action session-attendance-filter-actions">
                        <button type="submit" class="btn btn-secondary">Apply</button>
                        @if($search !== '' || $statusFilter !== '')
                            <a href="{{ route('events.event-sessions.show', [$event, $eventSession]) }}" class="btn btn-link">Clear</a>
                        @endif
                    </div>
                </form>

                @if($registrationAttendanceRows->count() > 0)
                    <div class="table-responsive registration-attendance-table-wrap">
                        <table class="table table-hover mb-0 registration-attendance-table">
                            <colgroup>
                                <col style="width: 13%">
                                <col style="width: 20%">
                                <col style="width: 12%">
                                <col style="width: 16%">
                                <col style="width: 24%">
                                <col style="width: 15%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Organisation</th>
                                    <th>
                                        <span class="d-block mb-1">Status</span>
                                        <span class="registration-attendance-status-headings" aria-hidden="true">
                                            <span>Pre-registration</span>
                                            <span>Attendance</span>
                                        </span>
                                    </th>
                                    <th>Attended At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registrationAttendanceRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] ?: '—' }}</td>
                                        <td>{{ $row['email'] ?: '—' }}</td>
                                        <td>{{ $row['phone'] ?: '—' }}</td>
                                        <td>{{ $row['organisation'] ?: '—' }}</td>
                                        <td>
                                            <div class="registration-attendance-status-values">
                                                <span>{{ $row['registration_status'] }}</span>
                                                <span class="{{ $row['attendance_status'] === 'Attended' ? 'text-success' : 'text-muted' }}">{{ $row['attendance_status'] }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $row['attended_at']?->format('d M Y, h:i A') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @include('components.pagination-row', ['paginator' => $registrationAttendanceRows, 'ariaLabel' => 'Attendee results pages'])
                @elseif($search !== '' || $statusFilter !== '')
                    <p class="text-muted mb-0">No records match this search or status.</p>
                @else
                    <p class="text-muted mb-0">No pre-registrations or attendance records yet.</p>
                @endif
            </div>
        </section>
    </div>
@endsection
