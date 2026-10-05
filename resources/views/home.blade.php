@extends('layouts.app')

@section('title', 'Dashboard')
@section('section', 'Overview')
@section('container_class', 'dashboard-box')

@section('content')
    <header class="dashboard-header">
        <div>
            <h1>Attendance overview</h1>
            <p class="subtitle">Overview of today's sessions and attendance.</p>
        </div>
    </header>

    <section aria-labelledby="overview-heading">
        <h2 id="overview-heading" class="section-heading">Today</h2>
        <div class="dashboard-stats">
            <article class="dashboard-stat">
                <div class="dashboard-stat-label">Sessions in progress</div>
                <div class="dashboard-stat-value">{{ $activeSessions }}</div>
            </article>
            <article class="dashboard-stat">
                <div class="dashboard-stat-label">Sessions scheduled</div>
                <div class="dashboard-stat-value">{{ $todaySessions }}</div>
            </article>
            <article class="dashboard-stat">
                <div class="dashboard-stat-label">Attendance recorded</div>
                <div class="dashboard-stat-value">{{ $todayAttendance }}</div>
            </article>
            @auth
                @if (auth()->user()->role === 'admin')
                    <article class="dashboard-stat">
                        <div class="dashboard-stat-label">System accounts</div>
                        <div class="dashboard-stat-value">{{ $totalUsers }}</div>
                    </article>
                @endif
            @endauth
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="recent-sessions-heading">
        <div class="section-heading-row">
            <div>
                <h2 id="recent-sessions-heading" class="section-heading">Upcoming sessions</h2>
                <p>Current and upcoming sessions for your events.</p>
            </div>
            @auth
                @if (in_array(auth()->user()->role, ['admin', 'organizer']))
                    <a class="btn btn-secondary btn-sm" href="{{ route('events.index') }}">View events</a>
                @endif
            @endauth
        </div>

        <div class="dashboard-list">
            @forelse ($recentSessions as $session)
                <div class="dashboard-list-item">
                    <div>
                        <strong>{{ $session->event->name ?? 'Unknown Event' }}</strong>
                        <span>{{ $session->name }} · {{ $session->starts_at->format('d M Y, h:i A') }}–{{ $session->ends_at->format('h:i A') }}</span>
                    </div>
                    @auth
                        @if (in_array(auth()->user()->role, ['admin', 'organizer']))
                            <a class="btn btn-secondary btn-sm" href="{{ route('events.event-sessions.show', [$session->event, $session]) }}">Open session</a>
                        @endif
                    @endauth
                </div>
            @empty
                <div class="dashboard-list-item">
                    <span>
                        @if(auth()->user()->role === 'organizer')
                            No upcoming sessions are available for your events.
                        @else
                            No sessions are available yet.
                        @endif
                    </span>
                </div>
            @endforelse
        </div>
    </section>

    @auth
        @if (in_array(auth()->user()->role, ['admin', 'organizer']))
            <section class="dashboard-section" aria-labelledby="quick-access-heading">
                <h2 id="quick-access-heading" class="section-heading">Quick access</h2>
                <div class="quick-links">
                    <a href="{{ route('events.index') }}"><strong>Events</strong><span>Manage events, sessions, and registrations</span></a>
                    @if (auth()->user()->role === 'admin')
                        <a href="{{ route('accounts.index') }}"><strong>User accounts</strong><span>Manage system access and roles</span></a>
                        <a href="{{ route('event-types.index') }}"><strong>Event types</strong><span>Manage event types</span></a>
                        <a href="{{ route('locations.index') }}"><strong>Locations</strong><span>Manage locations</span></a>
                        <a href="{{ route('audit-logs.index') }}"><strong>Audit log</strong><span>View audit history</span></a>
                    @endif
                </div>
            </section>
        @endif
    @endauth
@endsection
