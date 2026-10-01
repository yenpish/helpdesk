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

            <div class="d-flex gap-2">
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

                <dl class="row mb-0">

                    <dt class="col-sm-3">Event</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route('events.show', $event) }}">
                            {{ $event->name }}
                        </a>
                    </dd>

                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9">
                        {{ $eventSession->name }}
                    </dd>

                    <dt class="col-sm-3">Description</dt>
                    <dd class="col-sm-9">
                        {{ $eventSession->description ?: '—' }}
                    </dd>

                    <dt class="col-sm-3">Session Starts</dt>
                    <dd class="col-sm-9">
                        {{ $eventSession->starts_at?->format('d M Y, h:i A') ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Session Ends</dt>
                    <dd class="col-sm-9">
                        {{ $eventSession->ends_at?->format('d M Y, h:i A') ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Attendance Opens</dt>
                    <dd class="col-sm-9">
                        {{ $eventSession->attendance_opens_at?->format('d M Y, h:i A') ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Attendance Closes</dt>
                    <dd class="col-sm-9">
                        {{ $eventSession->attendance_closes_at?->format('d M Y, h:i A') ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Created By</dt>
                    <dd class="col-sm-9">
                        {{ $eventSession->createdBy?->name ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Updated By</dt>
                    <dd class="col-sm-9">
                        {{ $eventSession->updatedBy?->name ?? '—' }}
                    </dd>

                    <dt class="col-sm-3">Attendance Records</dt>
                    <dd class="col-sm-9">
                        {{ $eventSession->attendances->count() }}
                    </dd>

                </dl>

            </div>
        </div>


        {{-- Registration / Attendance Report --}}
        <div class="card mb-4">

            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Registration & Attendance</span>

                <a
                    href="{{ route('events.registrations.index', $event) }}"
                    class="btn btn-secondary btn-sm"
                >
                    Manage Registrations
                </a>
            </div>

            <div class="card-body">

                @if($registrations->count())

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Organisation</th>
                                <th>Registration</th>
                                <th>Attendance</th>
                                <th>Attended At</th>
                            </tr>
                            </thead>

                            <tbody>

                            @foreach($registrations as $registration)

                                @php
                                    $email = strtolower(trim($registration->guest_email ?? ''));

                                    $attendance = $attendanceEmails->get($email);
                                @endphp

                                <tr>

                                    <td>
                                        {{ $registration->guest_name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $registration->guest_email ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $registration->organisation ?? '—' }}
                                    </td>

                                    <td>
                                        {{ ucfirst($registration->status) }}
                                    </td>

                                    <td>
                                        @if($attendance)
                                            Attended
                                        @else
                                            Missed
                                        @endif
                                    </td>

                                    <td>
                                        {{ $attendance?->created_at?->format('d M Y, h:i A') ?? '—' }}
                                    </td>

                                </tr>

                            @endforeach

                            </tbody>

                        </table>

                    </div>

                @else

                    <p class="text-muted mb-0">
                        No registrations for this event.
                    </p>

                @endif

            </div>
        </div>


        {{-- Unregistered Attendance --}}
        @php
            $registeredEmails = $registrations
                ->filter(fn ($registration) => filled($registration->guest_email))
                ->map(fn ($registration) => strtolower(trim($registration->guest_email)))
                ->flip();

            $unregisteredAttendances = $eventSession->attendances
                ->filter(function ($attendance) use ($registeredEmails) {
                    $email = strtolower(trim($attendance->email ?? ''));

                    return $email !== '' && !$registeredEmails->has($email);
                });
        @endphp

        @if($unregisteredAttendances->count())

            <div class="card">

                <div class="card-header">
                    Unregistered Attendance
                </div>

                <div class="card-body">

                    <p class="text-muted">
                        These people submitted attendance but did not have a matching
                        registration for this event.
                    </p>

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Position</th>
                                <th>Recorded At</th>
                            </tr>
                            </thead>

                            <tbody>

                            @foreach($unregisteredAttendances as $attendance)

                                <tr>

                                    <td>
                                        {{ $attendance->full_name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendance->email ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendance->phone ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendance->position ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendance->created_at?->format('d M Y, h:i A') ?? '—' }}
                                    </td>

                                </tr>

                            @endforeach

                            </tbody>

                        </table>

                    </div>

                </div>
            </div>

        @endif

    </div>
@endsection
