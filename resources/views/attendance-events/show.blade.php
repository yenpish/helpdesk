@extends('layouts.app')

@section('title', $event->name)

@section('container_class', 'wide-box')

@section('content')

    <div class="event-header">
        <div>
            <h1>{{ $event->name }}</h1>
            <p class="event-subtitle">
                Attendance session details and attendee records.
            </p>
        </div>

        <div class="event-actions">
            <a class="event-back" href="{{ route('attendance-events.index') }}">
                ← Back to Sessions
            </a>

            <a class="event-action" href="{{ route('attendance-events.edit', $event) }}">
                Edit Session
            </a>

            <a class="event-action" href="{{ route('attendance-events.export', $event) }}">
                Export CSV
            </a>
        </div>
    </div>

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

    <div class="event-info">

        <div class="event-meta">
            <p>
                <strong>Location:</strong>
                {{ $event->location->name }}
            </p>

            <p>
                <strong>PIN:</strong>
                {{ $event->pin ?? 'Not set' }}
            </p>

            <p class="status-line">
                <span class="status-dot status-{{ $status }}"></span>
                <strong>{{ ucfirst($status) }}</strong>
            </p>
        </div>

        <div class="event-qr">
            <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode(route('attendance.pin')) }}"
                alt="Attendance QR Code"
                width="120"
                height="120"
            >
            <span>Scan to enter attendance</span>
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

                                @if ($attendance->signature)
                                    <div style="margin-top: 5px;">
                                        <img
                                            src="{{ $attendance->signature }}"
                                            alt="Signature"
                                            style="display: block; max-width: 300px; max-height: 120px; background: white; border: 1px solid #ccc; border-radius: 4px;"
                                        >
                                    </div>
                                @else
                                    Not provided
                                    @endif
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
