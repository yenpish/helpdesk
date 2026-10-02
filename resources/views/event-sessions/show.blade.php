@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h1>{{ $eventSession->name }}</h1>
                <p class="text-muted mb-0">Session for {{ $event->name }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('events.event-sessions.edit', [$event, $eventSession]) }}" class="btn btn-primary">Edit</a>
                <a href="{{ route('events.event-sessions.index', $event) }}" class="btn btn-secondary">Back to Sessions</a>
                <a href="{{ route('events.show', $event) }}" class="btn btn-secondary">Back to Event</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-header">Session Information</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Event</dt>
                    <dd class="col-sm-9"><a href="{{ route('events.show', $event) }}">{{ $event->name }}</a></dd>
                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9">{{ $eventSession->name }}</dd>
                    <dt class="col-sm-3">Description</dt>
                    <dd class="col-sm-9">{{ $eventSession->description ?: '—' }}</dd>
                    <dt class="col-sm-3">Session Starts</dt>
                    <dd class="col-sm-9">{{ $eventSession->starts_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                    <dt class="col-sm-3">Session Ends</dt>
                    <dd class="col-sm-9">{{ $eventSession->ends_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                    <dt class="col-sm-3">Attendance Opens</dt>
                    <dd class="col-sm-9">{{ $eventSession->attendance_opens_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                    <dt class="col-sm-3">Attendance Closes</dt>
                    <dd class="col-sm-9">{{ $eventSession->attendance_closes_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                    <dt class="col-sm-3">Created By</dt>
                    <dd class="col-sm-9">{{ $eventSession->createdBy?->name ?? '—' }}</dd>
                    <dt class="col-sm-3">Updated By</dt>
                    <dd class="col-sm-9">{{ $eventSession->updatedBy?->name ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <section class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Registration &amp; Attendance</span>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('events.event-sessions.attendance.export', [$event, $eventSession]) }}" class="btn btn-secondary btn-sm">Export Session CSV</a>
                    <a href="{{ route('events.registrations.index', $event) }}" class="btn btn-secondary btn-sm">Manage Registrations</a>
                </div>
            </div>
            <div class="card-body p-0">
                @if($registrationAttendanceRows->isNotEmpty())
                    <div class="table-responsive registration-attendance-table-wrap">
                        <table class="table table-hover mb-0 registration-attendance-table">
                            <colgroup>
                                <col style="width: 15%">
                                <col style="width: 22%">
                                <col style="width: 13%">
                                <col style="width: 17%">
                                <col style="width: 18%">
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
                                            <span>Registration</span>
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
                @else
                    <p class="text-muted p-3 mb-0">No registrations or attendance records yet.</p>
                @endif
            </div>
        </section>
    </div>
@endsection
