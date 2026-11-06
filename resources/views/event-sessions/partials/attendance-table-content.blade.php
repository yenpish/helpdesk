@if ($registrationAttendanceRows->count() > 0)
    <div class="table-responsive registration-attendance-table-wrap">
        <table class="table table-hover mb-0 registration-attendance-table">
            <colgroup>
                <col style="width: 14%">
                <col style="width: 19%">
                <col style="width: 10%">
                <col style="width: 16%">
                <col style="width: 15%">
                <col style="width: 14%">
                <col style="width: 12%">
            </colgroup>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Organisation</th>
                    <th>Pre-registration status</th>
                    <th>Attended At</th>
                    <th class="attendance-signature-action-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($registrationAttendanceRows as $row)
                    <tr>
                        <td>{{ $row['name'] ?: '—' }}</td>
                        <td>{{ $row['email'] ?: '—' }}</td>
                        <td>{{ $row['phone'] ?: '—' }}</td>
                        <td>{{ $row['organisation'] ?: '—' }}</td>
                        <td>{{ $row['registration_status'] }}</td>
                        <td class="table-cell-nowrap">{{ $row['attended_at']?->format('d M Y, h:i A') ?? '—' }}</td>
                        <td class="attendance-signature-action-cell">
                            <div class="attendance-row-actions">
                                <a
                                    class="btn btn-secondary btn-sm"
                                    href="{{ route('events.event-sessions.attendance.edit', [$event, $eventSession, $row['id'], 'search' => $search, 'status' => $statusFilter, 'page' => request()->query('page')]) }}"
                                    aria-label="Edit attendance details for {{ $row['name'] }}"
                                >Edit</a>
                            @if (!empty($row['signature']))
                                <button
                                    type="button"
                                    class="attendance-signature-icon-button"
                                    data-view-signature
                                    data-signature="{{ $row['signature'] }}"
                                    aria-label="View signature"
                                    title="View signature"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <path d="M2.5 16.5c1.7-2.6 3-5.1 4.2-5.1 1.5 0 .3 5.8 1.7 5.8 1.2 0 3.1-5.8 4.5-5.8 1.2 0-.2 5.8 1.1 5.8 1.1 0 2.7-3.4 4-3.4.8 0 .8 1.8 1.8 1.8.7 0 1.3-.5 1.8-1.1M2.5 20c5.8-1.3 12.4-2.1 19-2.2" />
                                    </svg>
                                </button>
                            @else
                                <span class="attendance-signature-missing" aria-label="No signature recorded" title="No signature recorded">—</span>
                            @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('components.pagination-row', ['paginator' => $registrationAttendanceRows, 'ariaLabel' => 'Attendee results pages'])
@elseif ($search !== '' || $statusFilter !== '')
    <p class="text-muted mb-0">No records match this search or status.</p>
@else
    <p class="text-muted mb-0">No check-ins recorded for this session yet.</p>
@endif
