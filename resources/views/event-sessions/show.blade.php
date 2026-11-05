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

        .attendance-refresh-status {
            display: inline-block;
            min-width: 9ch;
            text-align: right;
            line-height: 1.2;
        }

        .session-checkins-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px 16px;
        }

        .session-checkins-header-title {
            margin: 0;
            line-height: 1.2;
        }

        .session-checkins-header-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
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

        .attendance-row-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .attendance-edit-dialog {
            width: min(560px, calc(100vw - 32px));
            max-width: 560px;
            max-height: calc(100vh - 32px);
            padding: 0;
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
        }

        .attendance-edit-dialog::backdrop { background: rgb(15 23 42 / 55%); }
        .attendance-edit-dialog-header,
        .attendance-edit-dialog-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
        }
        .attendance-edit-dialog-header {
            border-bottom: 1px solid var(--line);
            background: var(--surface-raised);
        }
        .attendance-edit-dialog-header h2 { margin: 0; line-height: 1.2; }
        .attendance-edit-dialog-body { padding: 16px 18px; }
        .attendance-edit-dialog-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 16px;
        }
        .attendance-edit-dialog-fields label { margin-bottom: 5px; }
        .attendance-edit-dialog-fields .form-control { min-width: 0; }
        .attendance-edit-dialog-footer {
            justify-content: flex-end;
            border-top: 1px solid var(--line);
        }

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

            .attendance-edit-dialog-fields { grid-template-columns: 1fr; }
        }
    </style>
    <div class="container">
        <div class="page-header mb-4">
            <div class="page-heading">
                <h1>{{ $eventSession->name }}</h1>
                <p>Session for {{ $event->name }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap header-actions">
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
            <div class="card-header session-checkins-header">
                <span class="session-checkins-header-title">Session check-ins</span>
                <div class="session-checkins-header-actions">
                    <span class="small text-muted attendance-refresh-status" role="status" aria-live="polite" data-attendance-refresh-status></span>
                    <button
                        type="button"
                        class="btn btn-secondary btn-sm"
                        data-attendance-refresh
                        data-auto-refresh="{{ $eventSession->starts_at && $eventSession->ends_at && now()->between($eventSession->starts_at, $eventSession->ends_at) ? 'true' : 'false' }}"
                        data-session-end="{{ $eventSession->ends_at?->timestamp ?? 0 }}"
                    >Refresh</button>
                    <a href="{{ route('events.event-sessions.attendance.export', [$event, $eventSession]) }}" class="btn btn-secondary btn-sm">Export Session CSV</a>
                    <a href="{{ route('events.registrations.index', $event) }}" class="btn btn-secondary btn-sm">Manage Pre-registrations</a>
                </div>
            </div>
            <div class="card-body p-3">
                <p class="small text-muted mb-2">Event pre-registrations: <span data-attendance-count="registered">{{ $registeredCount }}</span></p>
                <dl class="session-attendance-summary mb-3" aria-label="Session check-ins">
                    <div><dt>Check-ins</dt><dd data-attendance-count="attended">{{ $attendedCount }}</dd></div>
                    <div><dt>Pre-registered</dt><dd data-attendance-count="pre_registered">{{ $preRegisteredAttendedCount }}</dd></div>
                    <div><dt>Other attendees</dt><dd data-attendance-count="not_pre_registered">{{ $notPreRegisteredCount }}</dd></div>
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
                        <label for="session-attendance-status" class="form-label">Pre-registration</label>
                        <select id="session-attendance-status" name="status" class="form-select">
                            <option value="" @selected($statusFilter === '')>All check-ins</option>
                            <optgroup label="Pre-registration">
                                <option value="pre_registered" @selected($statusFilter === 'pre_registered')>Pre-registered</option>
                                <option value="not_pre_registered" @selected($statusFilter === 'not_pre_registered')>Not pre-registered</option>
                            </optgroup>
                            <option value="pending" @selected($statusFilter === 'pending')>Pending pre-registration</option>
                            <option value="approved" @selected($statusFilter === 'approved')>Approved pre-registration</option>
                            <option value="rejected" @selected($statusFilter === 'rejected')>Rejected pre-registration</option>
                            <option value="cancelled" @selected($statusFilter === 'cancelled')>Cancelled pre-registration</option>
                        </select>
                    </div>
                    <div class="events-filter-action session-attendance-filter-actions">
                        <button type="submit" class="btn btn-secondary">Apply</button>
                        @if($search !== '' || $statusFilter !== '')
                            <a href="{{ route('events.event-sessions.show', [$event, $eventSession]) }}" class="btn btn-link">Clear</a>
                        @endif
                    </div>
                </form>

                <div id="session-attendance-content" aria-live="polite">
                    @include('event-sessions.partials.attendance-table-content', [
                        'registrationAttendanceRows' => $registrationAttendanceRows,
                        'search' => $search,
                        'statusFilter' => $statusFilter,
                    ])
                </div>

                <dialog id="attendance-signature-dialog" class="attendance-signature-dialog" aria-labelledby="attendance-signature-title">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <h2 id="attendance-signature-title" class="h5 mb-0">Attendance Signature</h2>
                        <button type="button" class="btn btn-secondary btn-sm" data-close-signature>Close</button>
                    </div>
                    <p id="attendance-signature-empty" class="text-muted mb-0" hidden>No signature recorded</p>
                    <img id="attendance-signature-image" class="attendance-signature-image" alt="Saved attendee signature" hidden>
                </dialog>

                <dialog
                    id="attendance-edit-dialog"
                    class="attendance-edit-dialog"
                    aria-labelledby="attendance-edit-title"
                    @if($errors->any() && old('attendance_id')) open @endif
                >
                    <form
                        id="attendance-edit-form"
                        method="POST"
                        action="{{ old('attendance_id') ? route('events.event-sessions.attendance.update', [$event, $eventSession, old('attendance_id')]) : '' }}"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="attendance_id" value="{{ old('attendance_id') }}">
                        <div class="attendance-edit-dialog-header">
                            <h2 id="attendance-edit-title" class="h5">Edit attendance details</h2>
                            <button type="button" class="btn btn-secondary btn-sm" data-close-attendance-edit>Cancel</button>
                        </div>
                        <div class="attendance-edit-dialog-body">
                            <div class="attendance-edit-dialog-fields">
                                <div>
                                    <label for="attendance-edit-name">Full name</label>
                                    <input id="attendance-edit-name" name="full_name" class="form-control" required maxlength="255" value="{{ old('full_name') }}">
                                    @error('full_name')<small class="text-danger">{{ $message }}</small>@enderror
                                </div>
                                <div>
                                    <label for="attendance-edit-email">Email</label>
                                    <input id="attendance-edit-email" type="email" name="email" class="form-control" required maxlength="255" value="{{ old('email') }}">
                                    @error('email')<small class="text-danger">{{ $message }}</small>@enderror
                                </div>
                                <div>
                                    <label for="attendance-edit-phone">Phone</label>
                                    <input id="attendance-edit-phone" type="tel" name="phone" class="form-control" required maxlength="50" value="{{ old('phone') }}">
                                    @error('phone')<small class="text-danger">{{ $message }}</small>@enderror
                                </div>
                                <div>
                                    <label for="attendance-edit-position">Position</label>
                                    <input id="attendance-edit-position" name="position" class="form-control" maxlength="255" value="{{ old('position') }}">
                                    @error('position')<small class="text-danger">{{ $message }}</small>@enderror
                                </div>
                                <div>
                                    <label for="attendance-edit-organisation">Organisation</label>
                                    <input id="attendance-edit-organisation" name="unit" class="form-control" maxlength="255" value="{{ old('unit') }}">
                                    @error('unit')<small class="text-danger">{{ $message }}</small>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="attendance-edit-dialog-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </dialog>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const refreshButton = document.querySelector('[data-attendance-refresh]');
            const refreshStatus = document.querySelector('[data-attendance-refresh-status]');
            let refreshInFlight = false;

            const filtersChangedSinceApply = () => {
                const params = new URLSearchParams(window.location.search);
                const search = document.getElementById('session-attendance-search')?.value.trim() || '';
                const status = document.getElementById('session-attendance-status')?.value || '';
                return search !== (params.get('search') || '') || status !== (params.get('status') || '');
            };

            const refreshAttendance = async (manual = false) => {
                if (!refreshButton || refreshInFlight || document.hidden) return;
                if (filtersChangedSinceApply()) {
                    if (manual && refreshStatus) refreshStatus.textContent = 'Apply filters first';
                    return;
                }

                refreshInFlight = true;
                refreshButton.disabled = true;
                const pageUrl = window.location.href;

                try {
                    const url = new URL(pageUrl);
                    url.searchParams.set('attendance_refresh', '1');
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    });
                    if (!response.ok) throw new Error('Refresh failed');
                    const data = await response.json();

                    if (window.location.href !== pageUrl || filtersChangedSinceApply()) return;

                    document.getElementById('session-attendance-content').innerHTML = data.html;
                    Object.entries(data.counts).forEach(([key, value]) => {
                        const count = document.querySelector(`[data-attendance-count="${key}"]`);
                        if (count) count.textContent = value;
                    });
                    if (manual && refreshStatus) refreshStatus.textContent = 'Updated';
                } catch (error) {
                    if (manual && refreshStatus) refreshStatus.textContent = 'Refresh failed';
                } finally {
                    refreshInFlight = false;
                    refreshButton.disabled = false;
                }
            };

            refreshButton?.addEventListener('click', () => refreshAttendance(true));
            if (refreshButton?.dataset.autoRefresh === 'true') {
                window.setInterval(() => {
                    const sessionEnd = Number(refreshButton.dataset.sessionEnd || 0);
                    if (sessionEnd > 0 && Date.now() / 1000 <= sessionEnd) refreshAttendance();
                }, 15000);
            }

            const copyButtons = document.querySelectorAll('[data-copy-attendance-link]');

            const editDialog = document.getElementById('attendance-edit-dialog');
            const editForm = document.getElementById('attendance-edit-form');
            document.addEventListener('click', event => {
                const button = event.target.closest('[data-edit-attendance]');
                if (!button || !editDialog || !editForm) return;

                editForm.action = button.dataset.editAction;
                editForm.elements.namedItem('attendance_id').value = button.dataset.attendanceId;
                editForm.elements.namedItem('full_name').value = button.dataset.fullName || '';
                editForm.elements.namedItem('email').value = button.dataset.email || '';
                editForm.elements.namedItem('phone').value = button.dataset.phone || '';
                editForm.elements.namedItem('position').value = button.dataset.position || '';
                editForm.elements.namedItem('unit').value = button.dataset.unit || '';
                editDialog.showModal();
            });
            document.querySelector('[data-close-attendance-edit]')?.addEventListener('click', () => editDialog?.close());
            if (editDialog?.hasAttribute('open')) {
                editDialog.removeAttribute('open');
                editDialog.showModal();
            }

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

            document.addEventListener('click', event => {
                const button = event.target.closest('[data-view-signature]');
                if (!button) return;

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

            dialog.querySelector('[data-close-signature]')?.addEventListener('click', () => dialog.close());
        })();
    </script>
@endsection
