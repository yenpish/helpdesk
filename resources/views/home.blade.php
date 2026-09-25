@extends('layouts.app')

@section('title', 'Dashboard')

@section('container_class', 'dashboard-box')

@section('content')

    <div class="dashboard-header">
        <div>
            <h1>Attendance Dashboard</h1>
            <p class="subtitle">
                Overview of attendance activity and the rest of the system.
            </p>
        </div>
    </div>

    <section>
        <h2>Attendance Overview</h2>

        <div class="dashboard-stats">

            <div class="dashboard-stat">
                <div class="dashboard-stat-label">Active Sessions</div>
                <div class="dashboard-stat-value">{{ $activeSessions }}</div>
            </div>

            <div class="dashboard-stat">
                <div class="dashboard-stat-label">Today's Sessions</div>
                <div class="dashboard-stat-value">{{ $todaySessions }}</div>
            </div>

            <div class="dashboard-stat">
                <div class="dashboard-stat-label">Today's Attendance</div>
                <div class="dashboard-stat-value">{{ $todayAttendance }}</div>
            </div>

        </div>
    </section>

    <section>
        <h2>Recent Attendance Sessions</h2>
        <div class="dashboard-list">

            @forelse ($recentSessions as $session)
                <div class="dashboard-list-item">
                    <div>
                        <strong>{{ $session->name }}</strong>
                        <span>
                    {{ $session->starts_at->format('d M Y, h:i A') }}
                    -
                    {{ $session->ends_at->format('h:i A') }}
                </span>
                    </div>

                    @auth
                        @if (auth()->user()->role === 'organizer')
                            <a href="{{ route('attendance-events.show', $session) }}">
                                View
                            </a>
                        @endif
                    @endauth
                </div>
            @empty
                <div class="dashboard-list-item">
                    <span>No attendance sessions found.</span>
                </div>
            @endforelse

        </div>
    </section>

    <section>
        <h2>Other System Activity</h2>

        <div class="dashboard-stats">

            <div class="dashboard-stat">
                <div class="dashboard-stat-label">Open Tickets</div>
                <div class="dashboard-stat-value">{{ $openTickets }}</div>
            </div>

            @auth
                @if (auth()->user()->role === 'organizer')
                    <div class="dashboard-stat">
                        <div class="dashboard-stat-label">Total Users</div>
                        <div class="dashboard-stat-value">{{ $totalUsers }}</div>
                    </div>
                @endif
            @endauth

        </div>
    </section>

@endsection
