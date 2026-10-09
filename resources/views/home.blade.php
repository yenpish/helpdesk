@extends('layouts.app')

@section('title', 'Dashboard')
@section('section', 'Overview')
@section('container_class', 'dashboard-box')

@section('content')
    <style>
        .dashboard-trend { padding: 16px 18px 10px; border: 1px solid var(--line); border-radius: 6px; background: var(--surface); }
        .dashboard-trend-heading { margin-bottom: 10px; }
        .dashboard-trend-heading .section-heading { margin: 0 0 4px; }
        .dashboard-trend-heading p { margin: 0; color: var(--muted); font-size: 13px; }
        .dashboard-trend-presets { display: flex; flex-wrap: wrap; gap: 6px; }
        .dashboard-trend-presets a { padding: 5px 9px; border: 1px solid var(--line); border-radius: 4px; color: var(--text); font-size: 12px; text-decoration: none; }
        .dashboard-trend-presets a[aria-current="true"] { border-color: var(--text); font-weight: 600; }
        .dashboard-trend-dates { display: flex; align-items: end; gap: 8px; margin: 0 0 4px; }
        .dashboard-trend-dates > div { display: grid; gap: 3px; }
        .dashboard-trend-dates label { margin: 0; color: var(--muted); font-size: 11px; font-weight: 500; }
        .dashboard-trend-dates input { width: auto; min-width: 145px; padding: 5px 7px; font-size: 12px; }
        .dashboard-trend-dates button { min-height: 31px; }
        .dashboard-trend-error, .dashboard-trend-empty { margin: 6px 0; color: var(--muted); font-size: 12px; }
        .dashboard-trend-error { color: #9B3533; }
        .dashboard-trend-chart svg { display: block; width: 100%; height: auto; max-height: 215px; overflow: visible; }
        .dashboard-trend-gridline { stroke: var(--line); stroke-width: 1; }
        .dashboard-trend-axis-label { fill: var(--muted); font-size: 11px; }
        .dashboard-trend-line { fill: none; stroke: var(--blue); stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
        .dashboard-trend-point { fill: var(--surface); stroke: var(--blue); stroke-width: 2; }
        @media (max-width: 600px) {
            .dashboard-trend-heading { align-items: flex-start; flex-direction: column; }
            .dashboard-trend-dates { align-items: stretch; flex-wrap: wrap; }
            .dashboard-trend-dates > div { flex: 1 1 130px; }
            .dashboard-trend-dates input { width: 100%; min-width: 0; }
        }
    </style>

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

    <section class="dashboard-section dashboard-trend" aria-labelledby="attendance-trend-heading">
        <div class="section-heading-row dashboard-trend-heading">
            <div>
                <h2 id="attendance-trend-heading" class="section-heading">Attendance over time</h2>
                <p>{{ number_format($attendanceTrendTotal) }} {{ \Illuminate\Support\Str::plural('check-in', $attendanceTrendTotal) }} · {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</p>
            </div>
            <nav class="dashboard-trend-presets" aria-label="Attendance chart date range">
                @foreach(['7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $preset => $label)
                    <a href="{{ route('home', ['range' => $preset]) }}" {{ $range === $preset ? 'aria-current=true' : '' }}>{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        <form class="dashboard-trend-dates" method="GET" action="{{ route('home') }}">
            <input type="hidden" name="range" value="custom">
            <div>
                <label for="attendance-from">From</label>
                <input id="attendance-from" type="date" name="from" value="{{ $from->format('Y-m-d') }}" required>
            </div>
            <div>
                <label for="attendance-to">To</label>
                <input id="attendance-to" type="date" name="to" value="{{ $to->format('Y-m-d') }}" required>
            </div>
            <button class="btn btn-secondary btn-sm" type="submit">Apply</button>
        </form>
        @if($rangeError)
            <p class="dashboard-trend-error" role="alert">{{ $rangeError }}</p>
        @endif

        @if($attendanceTrendTotal === 0)
            <p class="dashboard-trend-empty">No check-ins in this date range.</p>
        @endif

        <div class="dashboard-trend-chart">
            <svg viewBox="0 0 760 200" role="img" aria-label="Daily attendance check-ins from {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}">
                @foreach($attendanceTrendYTicks as $tick)
                    @php
                        $tickY = $plot['bottom'] - ($tick / $maxTrendCount) * ($plot['bottom'] - $plot['top']);
                    @endphp
                    <line x1="{{ $plot['left'] }}" y1="{{ $tickY }}" x2="{{ $plot['right'] }}" y2="{{ $tickY }}" class="dashboard-trend-gridline" />
                    <text x="{{ $plot['left'] - 10 }}" y="{{ $tickY + 4 }}" text-anchor="end" class="dashboard-trend-axis-label">{{ $tick }}</text>
                @endforeach
                <polyline points="{{ collect($attendanceTrendPoints)->map(fn ($point) => $point['x'] . ',' . $point['y'])->implode(' ') }}" class="dashboard-trend-line" />
                @foreach($attendanceTrendPoints as $point)
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" class="dashboard-trend-point">
                        <title>{{ $point['date'] }}: {{ $point['count'] }} check-ins</title>
                    </circle>
                @endforeach
                @foreach($attendanceTrendLabels as $label)
                    <text x="{{ $label['x'] }}" y="184" text-anchor="middle" class="dashboard-trend-axis-label">{{ $label['label'] }}</text>
                @endforeach
            </svg>
        </div>
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
                <div class="dashboard-metric"><dt>Sessions ended</dt><dd>{{ $recentSessionsEnded }}</dd></div>
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
