@extends('layouts.app')

@section('content')
    <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>{{ $eventSession->name }}</h1>

                <p class="text-muted mb-0">
                    Session for {{ $event->name }}
                </p>
            </div>

            <div>
                <a
                    href="{{ route('events.event-sessions.edit', [$event, $eventSession]) }}"
                    class="btn btn-primary"
                >
                    Edit
                </a>

                <a
                    href="{{ route('events.event-sessions.index', $event) }}"
                    class="btn btn-secondary"
                >
                    Back to Sessions
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        {{-- Session Information --}}
        <div class="card mb-4">
            <div class="card-header">
                Session Information
            </div>

            <div class="card-body">

                <p>
                    <strong>Event:</strong>
                    <a href="{{ route('events.show', $event) }}">
                        {{ $event->name }}
                    </a>
                </p>

                <p>
                    <strong>Name:</strong>
                    {{ $eventSession->name }}
                </p>

                <p>
                    <strong>Description:</strong>
                    {{ $eventSession->description ?: '—' }}
                </p>

                <p>
                    <strong>Session Starts:</strong>
                    {{ $eventSession->starts_at?->format('d M Y, h:i A') ?? '—' }}
                </p>

                <p>
                    <strong>Session Ends:</strong>
                    {{ $eventSession->ends_at?->format('d M Y, h:i A') ?? '—' }}
                </p>

                <p>
                    <strong>Attendance Opens:</strong>
                    {{ $eventSession->attendance_opens_at?->format('d M Y, h:i A') ?? '—' }}
                </p>

                <p>
                    <strong>Attendance Closes:</strong>
                    {{ $eventSession->attendance_closes_at?->format('d M Y, h:i A') ?? '—' }}
                </p>

                <p>
                    <strong>Created By:</strong>
                    {{ $eventSession->createdBy?->name ?? '—' }}
                </p>

                <p>
                    <strong>Updated By:</strong>
                    {{ $eventSession->updatedBy?->name ?? '—' }}
                </p>

                <p class="mb-0">
                    <strong>Attendance Records:</strong>
                    {{ $eventSession->attendances->count() }}
                </p>

            </div>
        </div>

        {{-- Attendance Records --}}
        <div class="card">

            <div class="card-header">
                Attendance Records
            </div>

            <div class="card-body">

                @if($eventSession->attendances->count())

                    <div class="table-responsive">

                        <table class="table table-hover">

                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Position</th>
                                <th>Organisation / Unit</th>
                                <th>Verification</th>
                                <th>Recorded At</th>
                            </tr>
                            </thead>

                            <tbody>

                            @foreach($eventSession->attendances as $attendance)

                                <tr>

                                    <td>
                                        {{ $attendance->full_name }}
                                    </td>

                                    <td>
                                        {{ $attendance->email }}
                                    </td>

                                    <td>
                                        {{ $attendance->phone ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendance->position ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendance->unit ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendance->verification_method ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendance->created_at?->format('d M Y, h:i A') ?? '—' }}
                                    </td>

                                </tr>

                            @endforeach

                            </tbody>

                        </table>

                    </div>

                @else

                    <p class="text-muted mb-0">
                        No attendance records for this session yet.
                    </p>

                @endif

            </div>
        </div>

    </div>
@endsection
