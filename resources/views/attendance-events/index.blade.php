@extends('layouts.app')

@section('title', 'Organizer Dashboard')

@section('container_class', 'wide-box')

@section('content')

    <h1>Organizer Dashboard</h1>

    <p>
        <a class="page-link" href="{{ route('home') }}">Back to Home</a>
        <a class="page-link" href="{{ route('attendance-events.create') }}">Create New Session</a>
    </p>

    <div class="stats">

        <div class="stat">
            <div class="stat-label">Total Sessions</div>
            <div class="stat-value">{{ $totalEvents }}</div>
        </div>

        <div class="stat">
            <div class="stat-label">Total Attendees</div>
            <div class="stat-value">{{ $totalAttendees }}</div>
        </div>

        <div class="stat">
            <div class="stat-label">Active Sessions</div>
            <div class="stat-value">{{ $activeEvents }}</div>
        </div>

    </div>

    <h2>Attendance Sessions</h2>

    <div class="table-container">

        <table>

            <thead>
            <tr>
                <th>Event</th>
                <th>Location</th>
                <th>Start</th>
                <th>End</th>
                <th>Attendees</th>
                <th></th>
            </tr>
            </thead>

            <tbody>

            @forelse ($events as $event)

                @php
                    $status = 'upcoming';

                    if ($event->starts_at && $event->ends_at) {
                        if (now()->between($event->starts_at, $event->ends_at)) {
                            $status = 'active';
                        } elseif (now()->gt($event->ends_at)) {
                            $status = 'ended';
                        }
                    }
                @endphp

                <tr>
                    <td>
                        <span class="status-dot status-{{ $status }}"></span>
                        <strong>{{ $event->name }}</strong>
                    </td>

                    <td>
                        {{ $event->location->name }}
                    </td>

                    <td>
                        {{ $event->starts_at?->format('d/m/Y H:i') ?? 'Not set' }}
                    </td>

                    <td>
                        {{ $event->ends_at?->format('d/m/Y H:i') ?? 'Not set' }}
                    </td>

                    <td>
                        {{ $event->attendances_count }}
                    </td>

                    <td>
                        <a href="{{ route('attendance-events.show', $event) }}">
                            View Attendance
                        </a>
                    </td>
                </tr>

            @empty

                <tr>
                    <td colspan="6">
                        No attendance sessions found.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

@endsection
