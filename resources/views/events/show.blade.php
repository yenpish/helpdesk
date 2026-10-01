@extends('layouts.app')

@section('content')
    <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>{{ $event->name }}</h1>
                <p class="text-muted mb-0">Event details</p>
            </div>

            <div class="d-flex gap-2">
                <a
                    href="{{ route('events.edit', $event) }}"
                    class="btn btn-primary"
                >
                    Edit Event
                </a>

                <a
                    href="{{ route('events.event-sessions.index', $event) }}"
                    class="btn btn-secondary"
                >
                    Manage Sessions
                </a>

                <a
                    href="{{ route('events.index') }}"
                    class="btn btn-secondary"
                >
                    Back
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header">
                Event Information
            </div>

            <div class="card-body">
                <dl class="row mb-0">

                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9">
                        {{ $event->name }}
                    </dd>

                    <dt class="col-sm-3">Description</dt>
                    <dd class="col-sm-9">
                        {{ $event->description ?: '—' }}
                    </dd>

                    <dt class="col-sm-3">Event Type</dt>
                    <dd class="col-sm-9">
                        {{ $event->eventType?->name ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Location</dt>
                    <dd class="col-sm-9">
                        {{ $event->location?->name ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Organizer</dt>
                    <dd class="col-sm-9">
                        {{ $event->organizer?->name ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9">
                        {{ ucfirst($event->status) }}
                    </dd>

                    <dt class="col-sm-3">Attendance PIN</dt>
                    <dd class="col-sm-9">
                        <strong>{{ $event->pin ?? '—' }}</strong>
                    </dd>

                    <dt class="col-sm-3">Created By</dt>
                    <dd class="col-sm-9">
                        {{ $event->createdBy?->name ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Updated By</dt>
                    <dd class="col-sm-9">
                        {{ $event->updatedBy?->name ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Created At</dt>
                    <dd class="col-sm-9">
                        {{ $event->created_at?->format('d M Y, h:i A') ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Updated At</dt>
                    <dd class="col-sm-9">
                        {{ $event->updated_at?->format('d M Y, h:i A') ?? '—' }}
                    </dd>

                </dl>
            </div>
        </div>

        <div class="card mb-4">

            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Sessions</span>

                <div class="d-flex gap-2">

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
                                <th>Actions</th>
                            </tr>
                            </thead>

                            <tbody>

                            @foreach($event->sessions as $session)

                                <tr>

                                    <td>
                                        <a
                                            href="{{ route('events.event-sessions.show', [$event, $session]) }}"
                                        >
                                            {{ $session->name }}
                                        </a>
                                    </td>

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

                                    <td>
                                        <a
                                            href="{{ route('events.event-sessions.show', [$event, $session]) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            View
                                        </a>

                                        <a
                                            href="{{ route('events.event-sessions.edit', [$event, $session]) }}"
                                            class="btn btn-sm btn-outline-secondary"
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

            <div class="card-header">
                Registrations
            </div>

            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Registrations</span>

                    <a
                        href="{{ route('events.registrations.index', $event) }}"
                        class="btn btn-primary btn-sm"
                    >
                        Manage Registrations
                    </a>
                </div>

                <div class="card-body">

                    @if($event->registrations->count())

                        <p class="mb-0">
                            {{ $event->registrations->count() }} registration(s)
                        </p>

                    @else

                        <p class="text-muted mb-0">
                            No registrations yet.
                        </p>

                    @endif

                </div>

            </div>

        </div>

    </div>
@endsection
