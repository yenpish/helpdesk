@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <style>
        .attendance-signature-dialog {
            width: min(640px, calc(100vw - 32px));
            max-width: 640px;
            padding: 20px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
        }

        .attendance-signature-dialog::backdrop {
            background: rgb(15 23 42 / 55%);
        }

        .attendance-signature-image {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 60vh;
            margin-inline: auto;
            object-fit: contain;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: #fff;
        }

        .attendance-signature-action-cell {
            width: 1%;
            padding-inline: .25rem !important;
            text-align: center;
            white-space: nowrap !important;
        }

        .attendance-signature-icon-button {
            display: inline-grid;
            width: 38px;
            height: 38px;
            place-items: center;
            padding: 0;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: var(--surface-raised);
            color: #495057;
            cursor: pointer;
        }

        .attendance-signature-icon-button:hover,
        .attendance-signature-icon-button:focus-visible {
            background: var(--line);
            color: #343a40;
        }

        .attendance-signature-icon-button svg { width: 21px; height: 21px; }
        .attendance-signature-missing { color: var(--muted); }

        .attendance-qr-utility {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px 20px;
            padding: 12px 16px;
        }

        .attendance-qr-utility p {
            margin: 3px 0 0;
            color: var(--muted);
        }

        .attendance-qr-actions,
        .attendance-qr-copy-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .attendance-qr-dialog {
            width: min(500px, calc(100vw - 32px));
            max-width: 500px;
            max-height: calc(100vh - 32px);
            padding: 0;
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
        }

        .attendance-qr-dialog::backdrop {
            background: rgb(15 23 42 / 55%);
        }

        .attendance-qr-dialog-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--line);
            background: var(--surface-raised);
        }

        .attendance-qr-dialog-body {
            padding: 18px;
            text-align: center;
        }

        .attendance-qr-dialog-image {
            display: block;
            width: min(250px, 70vw);
            height: auto;
            margin: 0 auto 14px;
        }

        @media (max-width: 600px) {
            .attendance-qr-utility {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
    <div class="container">
        <div class="page-header mb-4">
            <div class="page-heading">
                <h1>{{ $eventSession->name }}</h1>
                <p>Session for {{ $event->name }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap header-actions">
                <a href="{{ route('events.event-sessions.live-attendance', [$event, $eventSession]) }}" class="btn btn-primary">Live Attendance</a>
                <a href="{{ route('events.event-sessions.edit', [$event, $eventSession]) }}" class="btn btn-secondary">Edit</a>
                <a href="{{ route('events.event-sessions.index', $event) }}" class="btn btn-secondary">Back to Sessions</a>
                <a href="{{ route('events.show', $event) }}" class="btn btn-secondary">Back to Event</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-3 session-information-card">
            <div class="card-header">Session Information</div>
            <div class="card-body p-3">
                <dl class="event-details-grid session-details-grid mb-0">
                    <div class="event-details-pair">
                        <dt>Event</dt><dd>{{ $event->name }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Name</dt><dd>{{ $eventSession->name }}</dd>
                    </div>
                    <div class="event-details-pair">
                        <dt>Description</dt><dd>{{ $eventSession->description ?: '—' }}</dd>
                    </div>
                </dl>

                <div class="event-details-meta session-details-meta">
                    <section class="event-details-meta-group" aria-labelledby="session-schedule-heading">
                        <h3 id="session-schedule-heading">Session schedule</h3>
                        <dl class="mb-0">
                            <div class="event-details-meta-row">
                                <dt>Starts</dt><dd>{{ $eventSession->starts_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                            <div class="event-details-meta-row">
                                <dt>Ends</dt><dd>{{ $eventSession->ends_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="event-details-meta-group" aria-labelledby="session-attendance-window-heading">
                        <h3 id="session-attendance-window-heading">Attendance window</h3>
                        <dl class="mb-0">
                            <div class="event-details-meta-row">
                                <dt>Opens</dt><dd>{{ $eventSession->attendance_opens_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                            <div class="event-details-meta-row">
                                <dt>Closes</dt><dd>{{ $eventSession->attendance_closes_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="event-details-meta-group" aria-labelledby="session-record-heading">
                        <h3 id="session-record-heading">Record history</h3>
                        <dl class="mb-0">
                            <div class="event-details-meta-row">
                                <dt>Created by</dt><dd>{{ $eventSession->createdBy?->name ?? '—' }}</dd>
                            </div>
                            <div class="event-details-meta-row">
                                <dt>Updated by</dt><dd>{{ $eventSession->updatedBy?->name ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>
        </div>

        @if($attendanceUrl)
            <section class="card mb-3 attendance-qr-utility" aria-labelledby="attendance-qr-heading">
                <div>
                    <strong id="attendance-qr-heading">Attendance QR Code</strong>
                    <p>Available during the active attendance window.</p>
                </div>
                <div class="attendance-qr-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-view-attendance-qr aria-haspopup="dialog" aria-controls="attendance-qr-dialog">View QR Code</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-copy-attendance-link="{{ $attendanceUrl }}">Copy Attendance Link</button>
                    <span class="small text-muted" role="status" aria-live="polite" data-copy-attendance-feedback></span>
                </div>
            </section>

            <dialog id="attendance-qr-dialog" class="attendance-qr-dialog" aria-labelledby="attendance-qr-dialog-title">
                <div class="attendance-qr-dialog-header">
                    <h2 id="attendance-qr-dialog-title" class="h5 mb-0">Attendance QR Code</h2>
                    <button type="button" class="btn btn-secondary btn-sm" data-close-attendance-qr>Close</button>
                </div>
                <div class="attendance-qr-dialog-body">
                    <img
                        src="data:image/svg+xml;base64,{{ $attendanceQrSvg }}"
                        data-qr-content="{{ $attendanceUrl }}"
                        alt="QR code for this session's attendance link"
                        width="250"
                        height="250"
                        class="attendance-qr-dialog-image"
                    >
                    <p class="mb-3">Scan this QR code to access the attendance form for this session.</p>
                    <div class="attendance-qr-copy-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-copy-attendance-link="{{ $attendanceUrl }}">Copy Attendance Link</button>
                        <span class="small text-muted" role="status" aria-live="polite" data-copy-attendance-feedback></span>
                    </div>
                </div>
            </dialog>
        @endif

        <section class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Pre-registration &amp; Attendance</span>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('events.event-sessions.attendance.export', [$event, $eventSession]) }}" class="btn btn-secondary btn-sm">Export Session CSV</a>
                    <a href="{{ route('events.registrations.index', $event) }}" class="btn btn-secondary btn-sm">Manage Pre-registrations</a>
                </div>
            </div>
            <div class="card-body p-3">
                <dl class="session-attendance-summary mb-3" aria-label="Session attendance summary">
                    <div><dt>Pre-registered</dt><dd>{{ $registeredCount }}</dd></div>
                    <div><dt>Attended</dt><dd>{{ $attendedCount }}</dd></div>
                    <div><dt>Not attended</dt><dd>{{ $notAttendedCount }}</dd></div>
                    <div><dt>Attendance Rate</dt><dd>{{ $attendanceRate }}%</dd></div>
                </dl>
                <form method="GET" action="{{ route('events.event-sessions.show', [$event, $eventSession]) }}" class="events-filter-form session-attendance-filters mb-3">
                    <div class="events-search-field">
                        <label for="session-attendance-search" class="form-label">Search attendees</label>
                        <input
                            id="session-attendance-search"
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Name, email, phone, organisation..."
                        >
                    </div>
                    <div class="events-sort-field">
                        <label for="session-attendance-status" class="form-label">Status</label>
                        <select id="session-attendance-status" name="status" class="form-select">
                            <option value="" @selected($statusFilter === '')>All statuses</option>
                            <optgroup label="Pre-registration">
                                <option value="pre_registered" @selected($statusFilter === 'pre_registered')>Pre-registered</option>
                                <option value="not_pre_registered" @selected($statusFilter === 'not_pre_registered')>Not pre-registered</option>
                            </optgroup>
                            <optgroup label="Approval">
                                <option value="pending" @selected($statusFilter === 'pending')>Pending</option>
                                <option value="approved" @selected($statusFilter === 'approved')>Approved</option>
                                <option value="rejected" @selected($statusFilter === 'rejected')>Rejected</option>
                                <option value="cancelled" @selected($statusFilter === 'cancelled')>Cancelled</option>
                            </optgroup>
                            <optgroup label="Attendance">
                                <option value="attended" @selected($statusFilter === 'attended')>Attended</option>
                                <option value="not_attended" @selected($statusFilter === 'not_attended')>Not attended</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="events-filter-action session-attendance-filter-actions">
                        <button type="submit" class="btn btn-secondary">Apply</button>
                        @if($search !== '' || $statusFilter !== '')
                            <a href="{{ route('events.event-sessions.show', [$event, $eventSession]) }}" class="btn btn-link">Clear</a>
                        @endif
                    </div>
                </form>

                @if($registrationAttendanceRows->count() > 0)
                    <div class="table-responsive registration-attendance-table-wrap">
                        <table class="table table-hover mb-0 registration-attendance-table">
                            <colgroup>
                                <col style="width: 12%">
                                <col style="width: 19%">
                                <col style="width: 11%">
                                <col style="width: 15%">
                                <col style="width: 23%">
                                <col style="width: 16%">
                                <col style="width: 4%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Organisation</th>
                                    <th>
                                        <span class="d-block mb-1">Status</span>
                                        <span class="registration-attendance-status-headings" aria-hidden="true">
                                            <span>Pre-registration</span>
                                            <span>Attendance</span>
                                        </span>
                                    </th>
                                    <th>Attended At</th>
                                    <th class="attendance-signature-action-cell" aria-label="Signature"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registrationAttendanceRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] ?: '—' }}</td>
                                        <td>{{ $row['email'] ?: '—' }}</td>
                                        <td>{{ $row['phone'] ?: '—' }}</td>
                                        <td>{{ $row['organisation'] ?: '—' }}</td>
                                        <td>
                                            <div class="registration-attendance-status-values">
                                                <span>{{ $row['registration_status'] }}</span>
                                                <span class="{{ $row['attendance_status'] === 'Attended' ? 'text-success' : 'text-muted' }}">{{ $row['attendance_status'] }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $row['attended_at']?->format('d M Y, h:i A') ?? '—' }}</td>
                                        <td class="attendance-signature-action-cell">
                                            @if(!empty($row['signature']))
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
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @include('components.pagination-row', ['paginator' => $registrationAttendanceRows, 'ariaLabel' => 'Attendee results pages'])
                @elseif($search !== '' || $statusFilter !== '')
                    <p class="text-muted mb-0">No records match this search or status.</p>
                @else
                    <p class="text-muted mb-0">No pre-registrations or attendance records yet.</p>
                @endif

                <dialog id="attendance-signature-dialog" class="attendance-signature-dialog" aria-labelledby="attendance-signature-title">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <h2 id="attendance-signature-title" class="h5 mb-0">Attendance Signature</h2>
                        <button type="button" class="btn btn-secondary btn-sm" data-close-signature>Close</button>
                    </div>
                    <p id="attendance-signature-empty" class="text-muted mb-0" hidden>No signature recorded</p>
                    <img id="attendance-signature-image" class="attendance-signature-image" alt="Saved attendee signature" hidden>
                </dialog>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const copyButtons = document.querySelectorAll('[data-copy-attendance-link]');

            const copyWithFallback = async text => {
                if (window.isSecureContext && navigator.clipboard?.writeText) {
                    try {
                        await navigator.clipboard.writeText(text);
                        return true;
                    } catch (error) {
                        // Fall through to the legacy copy method when clipboard access is denied.
                    }
                }

                const temporaryInput = document.createElement('textarea');
                temporaryInput.value = text;
                temporaryInput.setAttribute('readonly', '');
                temporaryInput.setAttribute('aria-hidden', 'true');
                temporaryInput.style.position = 'fixed';
                temporaryInput.style.opacity = '0';
                document.body.appendChild(temporaryInput);
                temporaryInput.focus();
                temporaryInput.select();

                let copied = false;
                try {
                    copied = document.execCommand('copy');
                } catch (error) {
                    copied = false;
                }

                temporaryInput.remove();
                return copied;
            };

            copyButtons.forEach(button => {
                button.addEventListener('click', async () => {
                    const feedback = button.parentElement.querySelector('[data-copy-attendance-feedback]');
                    const copied = await copyWithFallback(button.dataset.copyAttendanceLink || '');
                    if (feedback) feedback.textContent = copied ? 'Attendance link copied.' : 'Could not copy the link. Please try again.';
                });
            });

            const qrDialog = document.getElementById('attendance-qr-dialog');
            document.querySelectorAll('[data-view-attendance-qr]').forEach(button => {
                button.addEventListener('click', () => qrDialog?.showModal());
            });
            qrDialog?.querySelector('[data-close-attendance-qr]')?.addEventListener('click', () => qrDialog.close());
            qrDialog?.addEventListener('click', event => {
                if (event.target === qrDialog) qrDialog.close();
            });

            const dialog = document.getElementById('attendance-signature-dialog');
            const image = document.getElementById('attendance-signature-image');
            const emptyMessage = document.getElementById('attendance-signature-empty');
            if (!dialog || !image || !emptyMessage) return;

            document.querySelectorAll('[data-view-signature]').forEach(button => {
                button.addEventListener('click', () => {
                    const signature = button.dataset.signature || '';
                    const isPngDataUrl = /^data:image\/png;base64,[A-Za-z0-9+/]+={0,2}$/.test(signature);

                    if (isPngDataUrl) {
                        image.src = signature;
                        image.hidden = false;
                        emptyMessage.hidden = true;
                    } else {
                        image.removeAttribute('src');
                        image.hidden = true;
                        emptyMessage.hidden = false;
                    }

                    dialog.showModal();
                });
            });

            dialog.querySelector('[data-close-signature]')?.addEventListener('click', () => dialog.close());
        })();
    </script>
@endsection
