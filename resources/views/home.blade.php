@extends('layouts.app')

@section('title', 'Dashboard')
@section('section', 'Overview')
@section('container_class', 'dashboard-box')

@section('content')
    <header class="dashboard-header">
        <div>
            <h1>Attendance dashboard</h1>
            <p class="subtitle">Today's sessions, check-ins, and pre-registrations.</p>
        </div>
    </header>

    <section aria-labelledby="today-heading" class="dashboard-today">
        <h2 id="today-heading" class="section-heading">Today</h2>
        <dl class="dashboard-metrics dashboard-metrics-four">
            <div class="dashboard-metric"><dt>Sessions today</dt><dd>{{ $todaySessions }}</dd></div>
            <div class="dashboard-metric"><dt>Sessions in progress</dt><dd>{{ $sessionsInProgress->count() }}</dd></div>
            <div class="dashboard-metric"><dt>Check-ins today</dt><dd>{{ $todayAttendance }}</dd></div>
            <div class="dashboard-metric"><dt>Pending pre-registrations</dt><dd>{{ $pendingPreRegistrations }}</dd></div>
        </dl>
    </section>

    <div class="dashboard-operational-grid">
        <section class="dashboard-operational-panel" aria-labelledby="attention-heading">
            <h2 id="attention-heading" class="section-heading">Needs attention</h2>
            @if($pendingPreRegistrations > 0 || $sessionsInProgress->isNotEmpty() || $sessionsStartingSoon->isNotEmpty())
                <div class="dashboard-operations-list">
                    @if($pendingPreRegistrations > 0)
                        <div class="dashboard-operation">
                            <div>
                                <strong>Pending pre-registrations</strong>
                                <span>{{ $pendingPreRegistrations }} pending across {{ $pendingPreRegistrationEvents->count() }} {{ \Illuminate\Support\Str::plural('event', $pendingPreRegistrationEvents->count()) }}</span>
                            </div>
                            <a class="btn btn-secondary btn-sm" href="{{ route('events.index') }}">View events</a>
                        </div>
                    @endif

                    @if($sessionsInProgress->isNotEmpty())
                        @php
                            $activeSession = $sessionsInProgress->first();
                        @endphp
                        <div class="dashboard-operation">
                            <div><strong>Sessions in progress</strong><span>{{ $sessionsInProgress->count() }} {{ \Illuminate\Support\Str::plural('session', $sessionsInProgress->count()) }} currently running</span></div>
                            <a class="btn btn-secondary btn-sm" href="{{ route('events.event-sessions.show', [$activeSession->event, $activeSession]) }}">Open a session</a>
                        </div>
                    @endif

                    @if($sessionsStartingSoon->isNotEmpty())
                        @php
                            $soonSession = $sessionsStartingSoon->first();
                        @endphp
                        <div class="dashboard-operation">
                            <div><strong>Starting soon</strong><span>{{ $sessionsStartingSoon->count() }} {{ \Illuminate\Support\Str::plural('session', $sessionsStartingSoon->count()) }} within the next 24 hours</span></div>
                            <a class="btn btn-secondary btn-sm" href="{{ route('events.event-sessions.show', [$soonSession->event, $soonSession]) }}">Open a session</a>
                        </div>
                    @endif
                </div>
            @else
                <p class="dashboard-nothing-attention">Nothing requires attention right now.</p>
            @endif
        </section>

        <section class="dashboard-operational-panel" aria-labelledby="attendance-overview-heading">
            <div class="section-heading-row">
                <div>
                    <h2 id="attendance-overview-heading" class="section-heading">Attendance overview</h2>
                    <p>Activity in the last 7 days.</p>
                </div>
            </div>
            <dl class="dashboard-metrics dashboard-metrics-three">
                <div class="dashboard-metric"><dt>Sessions held</dt><dd>{{ $recentSessionsHeld }}</dd></div>
                <div class="dashboard-metric"><dt>Check-ins recorded</dt><dd>{{ $recentCheckIns }}</dd></div>
                <div class="dashboard-metric"><dt>Pre-registrations received</dt><dd>{{ $recentPreRegistrations }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="dashboard-section" aria-labelledby="upcoming-sessions-heading">
        <div class="section-heading-row">
            <div>
                <h2 id="upcoming-sessions-heading" class="section-heading">Upcoming sessions</h2>
                <p>Current and next sessions for your events.</p>
            </div>
        </div>

        <div class="dashboard-list">
            @forelse($recentSessions as $session)
                @php
                    $sessionNow = now();
                    $isInProgress = $session->starts_at && $session->ends_at
                        && $session->starts_at->lte($sessionNow)
                        && $session->ends_at->gte($sessionNow);
                    $startsSoon = $session->starts_at && $session->starts_at->gt($sessionNow)
                        && $session->starts_at->lte($sessionNow->copy()->addDay());
                    $minutesUntil = $session->starts_at && $startsSoon
                        ? max(0, (int) ceil(($session->starts_at->getTimestamp() - $sessionNow->getTimestamp()) / 60))
                        : null;
                    if ($isInProgress) {
                        $sessionStatus = 'In progress';
                    } elseif ($session->starts_at?->isToday() && (!$startsSoon || $minutesUntil > 120)) {
                        $sessionStatus = 'Today, ' . $session->starts_at->format('h:i A');
                    } elseif ($session->starts_at?->isTomorrow() && (!$startsSoon || $minutesUntil > 120)) {
                        $sessionStatus = 'Tomorrow, ' . $session->starts_at->format('h:i A');
                    } elseif ($startsSoon) {
                        $duration = $minutesUntil < 60
                            ? max(1, $minutesUntil) . ' min'
                            : intdiv($minutesUntil, 60) . ' hr' . (intdiv($minutesUntil, 60) === 1 ? '' : 's')
                                . ($minutesUntil % 60 ? ' ' . ($minutesUntil % 60) . ' min' : '');
                        $sessionStatus = 'Starts in ' . $duration;
                    } elseif ($session->starts_at?->isToday()) {
                        $sessionStatus = 'Today, ' . $session->starts_at->format('h:i A');
                    } elseif ($session->starts_at?->isTomorrow()) {
                        $sessionStatus = 'Tomorrow, ' . $session->starts_at->format('h:i A');
                    } else {
                        $sessionStatus = $session->starts_at?->format('d M Y, h:i A') ?? 'To be confirmed';
                    }
                @endphp
                <div class="dashboard-list-item">
                    <div class="dashboard-session-info">
                        <strong>{{ $session->event->name ?? 'Unknown event' }} · {{ $session->name }}</strong>
                        <span>
                            {{ $session->starts_at?->format('d M Y, h:i A') ?? 'Date to be confirmed' }}
                            @if($session->event?->location) · {{ $session->event->location->name }}@endif
                        </span>
                    </div>
                    <div class="dashboard-session-actions">
                        <span class="dashboard-session-status {{ $isInProgress ? 'is-active' : '' }}">{{ $sessionStatus }}</span>
                        <a class="btn btn-secondary btn-sm" href="{{ route('events.event-sessions.show', [$session->event, $session]) }}">Open session</a>
                    </div>
                </div>
            @empty
                <div class="dashboard-list-item"><span>No current or upcoming sessions are available.</span></div>
            @endforelse
        </div>
    </section>

@endsection
