@extends('layouts.app')

@section('title', $event->name)

@section('container_class', 'wide-box')

@section('content')

    <h1>{{ $event->name }}</h1>

    <p>
        <a class="page-link" href="{{ route('home') }}">Back to Home</a>
        <a class="page-link" href="{{ route('attendance-events.index') }}">Back to Sessions</a>
    </p>

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

    <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">

        <div class="event-meta" style="margin: 0;">
            <p><strong>Location:</strong> {{ $event->location->name }}</p>
            <p><strong>PIN:</strong> {{ $event->pin ?? 'Not set' }}</p>
            <p class="status-line">
                <span class="status-dot status-{{ $status }}"></span>
                <strong>{{ ucfirst($status) }}</strong>
            </p>
        </div>

        <div style="text-align: center;">
            <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode(route('attendance.pin')) }}"
                alt="Attendance QR Code"
                width="120"
                height="120"
            >
        </div>

    </div>

    <div class="stats">

        <div class="stat">
            <div class="stat-label">Total Attendees</div>
            <div class="stat-value">{{ $event->attendances->count() }}</div>
        </div>

        <div class="stat">
            <div class="stat-label">Start</div>
            <div class="stat-value">
                {{ $event->starts_at?->format('d/m/Y H:i') ?? 'Not set' }}
            </div>
        </div>

        <div class="stat">
            <div class="stat-label">End</div>
            <div class="stat-value">
                {{ $event->ends_at?->format('d/m/Y H:i') ?? 'Not set' }}
            </div>
        </div>

    </div>

    <h2>Attendance Records</h2>

    <div class="table-container">

        <table>

            <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Position</th>
                <th>Unit / Organization</th>
                <th>Submitted</th>
                <th>Details</th>
            </tr>
            </thead>

            <tbody>

            @forelse ($event->attendances as $attendance)

                <tr>
                    <td>
                        {{ $loop->iteration }}
                    </td>

                    <td>
                        <strong>{{ $attendance->full_name }}</strong>
                    </td>

                    <td>
                        {{ $attendance->position ?: 'Not provided' }}
                    </td>

                    <td>
                        {{ $attendance->unit ?: 'Not provided' }}
                    </td>

                    <td>
                        {{ $attendance->created_at->format('d/m/Y H:i:s') }}
                    </td>

                    <td>
                        <details>
                            <summary>View Details</summary>

                            <div class="attendee-details">
                                <p>
                                    <strong>Phone:</strong>
                                    {{ $attendance->phone ?: 'Not provided' }}
                                </p>

                                <p>
                                    <strong>Email:</strong>
                                    {{ $attendance->email ?: 'Not provided' }}
                                </p>

                                <p>
                                    <strong>Signature:</strong>
                                    {{ $attendance->signature ?: 'Not provided' }}
                                </p>
                            </div>

                        </details>
                    </td>
                </tr>

            @empty

                <tr>
                    <td colspan="6">
                        No attendance records yet.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

@endsection
