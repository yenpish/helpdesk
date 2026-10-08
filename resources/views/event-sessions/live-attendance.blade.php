@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <div class="page-header mb-3">
            <div class="page-heading">
                <h1>Live Attendance</h1>
                <p>{{ $event->name }} · {{ $eventSession->name }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap header-actions">
                <a href="{{ route('events.event-sessions.show', [$event, $eventSession]) }}" class="btn btn-secondary">Back to Session</a>
            </div>
        </div>

        <section class="card mb-3" aria-label="Session attendance status">
            <div class="card-body p-3">
                <dl class="event-summary-row mb-0">
                    <div><dt>Session</dt><dd id="live-session-status">{{ $liveData['session_status'] }}</dd></div>
                    <div><dt>Attendance</dt><dd id="live-attendance-availability">{{ $liveData['attendance_availability'] }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="card mb-3" aria-label="Live attendance summary">
            <div class="card-body p-3">
                <dl class="event-summary-row mb-0" aria-live="polite">
                    <div><dt>Checked in</dt><dd id="live-checked-in">{{ $liveData['checked_in'] }}</dd></div>
                    <div><dt>Pre-registered checked in</dt><dd id="live-pre-registered-checked-in">{{ $liveData['pre_registered_checked_in'] }}</dd></div>
                    <div><dt>Not pre-registered</dt><dd id="live-not-pre-registered">{{ $liveData['not_pre_registered'] }}</dd></div>
                    <div><dt>Updated</dt><dd id="live-updated-status">Updated just now</dd></div>
                </dl>
            </div>
        </section>

        <section class="card" aria-labelledby="recent-check-ins-heading">
            <div class="card-header" id="recent-check-ins-heading">Recent check-ins</div>
            <div class="card-body p-3" id="live-check-ins-content">
                <p class="text-muted mb-0" id="live-empty-state" @if(count($liveData['check_ins'])) hidden @endif>No check-ins yet.</p>
                <div class="table-responsive registration-attendance-table-wrap" id="live-check-ins-table-wrap" @if(!count($liveData['check_ins'])) hidden @endif>
                    <table class="table table-hover mb-0 registration-attendance-table">
                        <thead><tr><th>Attendee</th><th>Organisation</th><th>Checked in</th><th>Pre-registration</th></tr></thead>
                        <tbody id="live-check-ins">
                            @foreach($liveData['check_ins'] as $checkIn)
                                <tr>
                                    <td>{{ $checkIn['name'] ?: '—' }}</td>
                                    <td>{{ $checkIn['organisation'] ?: '—' }}</td>
                                    <td>{{ $checkIn['checked_in_at'] ?: '—' }}</td>
                                    <td>{{ $checkIn['pre_registered'] ? 'Pre-registered' : 'Not pre-registered' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const endpoint = @json(route('events.event-sessions.live-attendance.data', [$event, $eventSession]));
            let inFlight = false;

            const setText = (id, value) => {
                document.getElementById(id).textContent = value;
            };

            const updateRecentCheckIns = (checkIns) => {
                const rows = document.getElementById('live-check-ins');
                const tableWrap = document.getElementById('live-check-ins-table-wrap');
                const empty = document.getElementById('live-empty-state');
                rows.replaceChildren();
                checkIns.forEach((checkIn) => {
                    const row = rows.insertRow();
                    row.insertCell().textContent = checkIn.name || '—';
                    row.insertCell().textContent = checkIn.organisation || '—';
                    row.insertCell().textContent = checkIn.checked_in_at || '—';
                    row.insertCell().textContent = checkIn.pre_registered ? 'Pre-registered' : 'Not pre-registered';
                });
                tableWrap.hidden = checkIns.length === 0;
                empty.hidden = checkIns.length !== 0;
            };

            const refresh = async () => {
                if (inFlight) return;
                inFlight = true;
                try {
                    const response = await fetch(endpoint, {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store',
                    });
                    if (!response.ok) throw new Error('Refresh failed');
                    const data = await response.json();
                    setText('live-session-status', data.session_status);
                    setText('live-attendance-availability', data.attendance_availability);
                    setText('live-checked-in', data.checked_in);
                    setText('live-pre-registered-checked-in', data.pre_registered_checked_in);
                    setText('live-not-pre-registered', data.not_pre_registered);
                    setText('live-updated-status', 'Updated just now');
                    updateRecentCheckIns(data.check_ins);
                } catch (error) {
                    setText('live-updated-status', 'Update unavailable; retrying');
                } finally {
                    inFlight = false;
                }
            };

            window.setInterval(refresh, 5000);
        })();
    </script>
@endsection
