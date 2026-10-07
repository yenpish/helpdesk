@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">

        <header class="page-header">
            <div class="page-heading">
                <h1>{{ $event->name }}</h1>
                <p class="text-muted mb-0">Event details</p>
            </div>

            <div class="d-flex gap-2 flex-wrap header-actions">
                <a
                    href="{{ route('events.edit', $event) }}"
                    class="btn btn-primary"
                >
                    Edit Event
                </a>

                <a
                    href="{{ route('events.index') }}"
                    class="btn btn-secondary"
                >
                    Back
                </a>
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <dl class="event-summary-row mb-3" aria-label="Event summary">
            <div><dt>Sessions</dt><dd>{{ $event->sessions->count() }}</dd></div>
            <div><dt>Pre-registrations</dt><dd>{{ $event->registrations->count() }}</dd></div>
            <div><dt>Attendance records</dt><dd>{{ $event->attendances_count }}</dd></div>
        </dl>

        <div class="card mb-4">
            <div class="card-header">
                Event Information
            </div>

            <div class="card-body">
                <dl class="event-details-grid mb-0">
                    <div class="event-details-pair">
                        <dt>Name</dt><dd>{{ $event->name }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Description</dt><dd>{{ $event->description ?: '—' }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Event Type</dt><dd>{{ $event->eventType?->name ?? '—' }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Location</dt><dd>{{ $event->location?->name ?? '—' }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Organizer</dt><dd>{{ $event->organizer?->name ?? '—' }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Status</dt><dd>{{ ucfirst($event->status) }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Attendance PIN</dt><dd><strong>{{ $event->pin ?? '—' }}</strong></dd>
                    </div>
                </dl>

                <div class="event-details-meta">
                    <section class="event-details-meta-group" aria-labelledby="event-schedule-heading">
                        <h3 id="event-schedule-heading">Schedule</h3>
                        <dl class="mb-0">
                            <div class="event-details-meta-row">
                                <dt>Starts</dt><dd>{{ $event->starts_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                            <div class="event-details-meta-row">
                                <dt>Ends</dt><dd>{{ $event->ends_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="event-details-meta-group" aria-labelledby="event-record-heading">
                        <h3 id="event-record-heading">Record history</h3>
                        <dl class="mb-0">
                            <div class="event-details-meta-row">
                                <dt>Created</dt>
                                <dd>{{ $event->createdBy?->name ?? '—' }} <span>·</span> {{ $event->created_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                            <div class="event-details-meta-row">
                                <dt>Updated</dt>
                                <dd>{{ $event->updatedBy?->name ?? '—' }} <span>·</span> {{ $event->updated_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>
        </div>

        <div class="card mb-4">

            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Sessions</span>

                <div class="d-flex gap-2 flex-wrap">

                    <a
                        href="{{ route('events.event-sessions.create', $event) }}"
                        class="btn btn-primary btn-sm"
                    >
                        + Add Session
                    </a>

                    <a
                        href="{{ route('events.event-sessions.index', $event) }}"
                        class="btn btn-secondary btn-sm"
                    >
                        Manage Sessions
                    </a>

                    @if($event->attendances_count || $event->registrations->isNotEmpty())
                        <a href="{{ route('events.attendance.export', $event) }}" class="btn btn-secondary btn-sm">Export Attendance</a>
                    @else
                        <button type="button" class="btn btn-secondary btn-sm" disabled title="Pre-registrations and attendance records will appear in the export">Export Attendance</button>
                    @endif

                </div>
            </div>

            <div class="card-body">

                @if($event->sessions->count())

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Starts</th>
                                <th>Ends</th>
                                <th>Attendance Window</th>
                                <th>Attendees</th>
                                <th>Actions</th>
                            </tr>
                            </thead>

                            <tbody>

                            @foreach($event->sessions as $session)

                                <tr>

                                    <td>{{ $session->name }}</td>

                                    <td>
                                        {{ $session->starts_at?->format('d M Y, h:i A') ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $session->ends_at?->format('d M Y, h:i A') ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $session->attendance_opens_at?->format('h:i A') ?? '—' }}
                                        -
                                        {{ $session->attendance_closes_at?->format('h:i A') ?? '—' }}
                                    </td>
                                    <td>{{ $session->attendances_count }}</td>

                                    <td class="table-actions">
                                        <a
                                            href="{{ route('events.event-sessions.show', [$event, $session]) }}"
                                            class="btn btn-secondary btn-sm"
                                        >
                                            View
                                        </a>

                                        <a
                                            href="{{ route('events.event-sessions.edit', [$event, $session]) }}"
                                            class="btn btn-secondary btn-sm"
                                        >
                                            Edit
                                        </a>
                                    </td>

                                </tr>

                            @endforeach

                            </tbody>

                        </table>

                    </div>

                @else

                    <p class="text-muted mb-3">
                        No sessions have been created.
                    </p>

                    <a
                        href="{{ route('events.event-sessions.create', $event) }}"
                        class="btn btn-primary"
                    >
                        + Create First Session
                    </a>

                @endif

            </div>

        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Pre-registrations</span>

                    <a
                        href="{{ route('events.registrations.index', $event) }}"
                        class="btn btn-primary btn-sm"
                    >
                        Manage Pre-registrations
                    </a>
                </div>

            <div class="card-body">

                    @if($event->registrations->count())

                        <p class="mb-0">
                            {{ $event->registrations->count() }} {{ \Illuminate\Support\Str::plural('pre-registration', $event->registrations->count()) }}
                        </p>

                    @else

                        <p class="text-muted mb-0">
                            No pre-registrations yet.
                        </p>

                    @endif

            </div>
        </div>

    </div>
@endsection
