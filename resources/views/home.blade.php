@extends('layouts.app')

@section('title', 'Dashboard')

@section('container_class', 'dashboard-box')

@section('content')

    <div class="dashboard-header">
        <div>
            <h1>Attendance Management Dashboard</h1>
            <p class="subtitle">
                Overview of events, sessions, and attendance activity.
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

            @auth
                @if (in_array(auth()->user()->role, ['admin', 'organizer']))
                    <div class="dashboard-stat">
                        <div class="dashboard-stat-label">Total Users</div>
                        <div class="dashboard-stat-value">{{ $totalUsers }}</div>
                    </div>
                @endif
            @endauth

        </div>
    </section>

    <section>
        <h2>Recent Sessions</h2>

        <div class="dashboard-list">

            @forelse ($recentSessions as $session)
                <div class="dashboard-list-item">
                    <div>
                        <strong>
                            {{ $session->event->name ?? 'Unknown Event' }}
                        </strong>

                        <span>
                            {{ $session->name }}
                            —
                            {{ $session->starts_at->format('d M Y, h:i A') }}
                            -
                            {{ $session->ends_at->format('h:i A') }}
                        </span>
                    </div>

                    @auth
                        @if (in_array(auth()->user()->role, ['admin', 'organizer']))
                            <a href="{{ route('events.event-sessions.show', [$session->event, $session]) }}">
                                View
                            </a>
                        @endif
                    @endauth
                </div>
            @empty
                <div class="dashboard-list-item">
                    <span>No sessions found.</span>
                </div>
            @endforelse

        </div>
    </section>

    <section>
        <h2>Quick Access</h2>

        <div class="dashboard-stats">

            @auth
                @if (in_array(auth()->user()->role, ['admin', 'organizer']))
                    <div class="dashboard-stat">
                        <div class="dashboard-stat-label">Events</div>
                        <a href="{{ route('events.index') }}">Manage Events</a>
                    </div>
                @endif

                @if (auth()->user()->role === 'admin')
                    <div class="dashboard-stat">
                        <div class="dashboard-stat-label">Accounts</div>
                        <a href="{{ route('accounts.index') }}">Manage Accounts</a>
                    </div>

                    <div class="dashboard-stat">
                        <div class="dashboard-stat-label">Audit Log</div>
                        <a href="{{ route('audit-logs.index') }}">View Audit Log</a>
                    </div>

                    <div class="dashboard-stat">
                        <div class="dashboard-stat-label">Event Types</div>
                        <a href="{{ route('event-types.index') }}">Manage Event Types</a>
                    </div>

                    <div class="dashboard-stat">
                        <div class="dashboard-stat-label">Locations</div>
                        <a href="{{ route('locations.index') }}">Manage Locations</a>
                    </div>
                @endif
            @endauth

        </div>
    </section>

@endsection
