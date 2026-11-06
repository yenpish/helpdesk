@extends('layouts.app')
@section('section', 'Attendance')

@section('title', $event->name . ' - Attendance')

@section('content')
    <style>
        .attendance-panel {
            width: 100%;
            max-width: 1120px;
            padding: 24px 28px;
        }

        .attendance-panel .account-form {
            max-width: none;
            margin-inline: 0;
        }

        .attendance-panel .attendance-lookup-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
            align-items: stretch;
            column-gap: 14px;
            margin-top: 8px;
            max-width: 850px;
        }

        .attendance-panel h2 {
            margin: 14px 0 8px;
            font-size: 20px;
        }

        .attendance-panel > h1 {
            margin-bottom: 4px;
        }

        .attendance-panel > p {
            margin: 4px 0 10px;
        }

        .attendance-lookup-shortcut {
            margin-top: 16px;
            font-size: 13px;
        }

        .attendance-lookup-shortcut summary {
            display: inline;
            cursor: pointer;
            list-style: none;
            color: var(--muted);
        }

        .attendance-lookup-shortcut summary::-webkit-details-marker {
            display: none;
        }

        .attendance-lookup-shortcut summary::marker {
            content: '';
        }

        .attendance-lookup-shortcut .lookup-action {
            margin-left: 4px;
            color: var(--blue);
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .attendance-lookup-shortcut .lookup-content {
            margin-top: 8px;
        }

        .attendance-panel .attendance-primary-heading {
            margin: 16px 0 10px;
            color: var(--text);
            font-size: 26px;
            font-weight: 700;
        }

        .attendance-session-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(250px, 1fr);
            align-items: end;
            gap: 18px;
            margin-bottom: 8px;
        }

        .attendance-details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 18px;
        }

        .attendance-details-grid > h2,
        .attendance-details-grid > .attendance-organisation-field,
        .attendance-details-grid > .attendance-signature {
            grid-column: 1 / -1;
        }

        .attendance-panel .field {
            min-width: 0;
            margin-bottom: 12px;
        }

        .attendance-panel .field small {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            line-height: 1.35;
        }

        .attendance-lookup-action {
            display: flex;
            align-items: flex-end;
            padding-bottom: 12px;
        }

        .attendance-panel .event-meta {
            margin: 0 0 12px !important;
        }

        .attendance-panel .event-meta p {
            line-height: 1.4;
        }

        .attendance-signature canvas {
            display: block;
        }

        .attendance-panel .attendance-form-actions {
            justify-content: flex-end;
            margin-top: 8px;
        }

        .attendance-request-status {
            margin: 0 0 12px;
        }

        .attendance-lookup-result {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 760px) {
            .attendance-panel {
                padding: 18px;
            }

            .attendance-panel .attendance-lookup-form,
            .attendance-details-grid,
            .attendance-session-layout {
                grid-template-columns: minmax(0, 1fr);
            }

            .attendance-panel .event-meta {
                margin: 0 0 8px !important;
            }

            .attendance-lookup-action {
                justify-self: start;
                padding-bottom: 0;
            }

        }
    </style>
    <div class="content-panel attendance-panel">
    <h1>{{ $event->name }}</h1>

    @if ($event->location)
        <p>
            <strong>Location:</strong>
            {{ $event->location->name }}
        </p>
    @endif

    @if ($errors->any())
        <div class="error" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div id="attendance-request-status" class="error attendance-request-status" role="alert" aria-live="assertive" hidden></div>

    @if ($sessions->count())

        <form
            id="attendance-form"
            method="POST"
            action="{{ route('attendance.store', ['access' => $access]) }}"
            class="account-form"
        >
            @csrf

            <h2 class="attendance-primary-heading">Attendance</h2>

            <div class="attendance-session-layout">
                <div>
                    <div class="field">
                        <label for="session_id">Session</label>
                        <select id="session_id" name="session_id" required>
                            @foreach ($sessions as $session)
                                <option
                                    value="{{ $session->id }}"
                                    data-name="{{ $session->name }}"
                                    data-start="{{ $session->starts_at?->format('d/m/Y H:i') }}"
                                    data-end="{{ $session->ends_at?->format('d/m/Y H:i') }}"
                                    data-opens-at="{{ $session->attendance_opens_at?->toIso8601String() }}"
                                    data-closes-at="{{ $session->attendance_closes_at?->toIso8601String() }}"
                                    @selected($selectedSession && (int) $selectedSession->id === (int) $session->id)
                                    @disabled(!$session->attendance_available)
                                >
                                    {{ $session->name }} — {{ $session->starts_at?->format('d M Y, h:i A') }}
                                    @if (!$session->attendance_available)
                                        (Not available)
                                    @elseif ($session->starts_at && $session->starts_at->isSameDay(now()))
                                        (Today)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

            @if (!$defaultSession)
                <div class="error">Attendance is not currently available for any session.</div>
            @else
                <div class="event-meta d-flex" style="gap:24px; flex-wrap:wrap; margin:12px 0;" aria-live="polite">
                    <p class="mb-0">
                        <strong>Selected session</strong><br>
                        <span id="selected-session-name">{{ $selectedSession?->name }}</span>
                    </p>
                    <p class="mb-0">
                        <strong>Time</strong><br>
                        <span id="selected-session-time">
                            {{ $selectedSession?->starts_at?->format('d/m/Y H:i') }}
                            —
                            {{ $selectedSession?->ends_at?->format('d/m/Y H:i') }}
                        </span>
                    </p>
                </div>
            @endif
            </div>

            @if ($defaultSession)
                <div class="attendance-details-grid">
                    <h2>Attendance Details</h2>

                <div class="field">
                    <label for="full_name">
                        Full Name *
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="{{ old('full_name', $attendancePrefill['full_name'] ?? '') }}"
                        required
                    >
                </div>

                <div class="field">
                    <label for="email">
                        Email *
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $attendancePrefill['email'] ?? '') }}"
                        required
                    >

                    <small>
                        Use the same email used during pre-registration for this event.
                    </small>
                </div>

                <div class="field">
                    <label for="phone">
                        Phone *
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="{{ old('phone', $attendancePrefill['phone'] ?? '') }}"
                        autocomplete="tel"
                        inputmode="tel"
                        required
                    >

                    <small>
                        Use the same phone number used during pre-registration for this event.
                    </small>
                </div>

                <div class="field">
                    <label for="position">
                        Position
                    </label>

                    <input
                        type="text"
                        id="position"
                        name="position"
                        value="{{ old('position', $attendancePrefill['position'] ?? '') }}"
                    >
                </div>

                <div class="field attendance-organisation-field">
                    <label for="unit">
                        Organisation
                    </label>

                    <input
                        type="text"
                        id="unit"
                        name="unit"
                        value="{{ old('unit', $attendancePrefill['unit'] ?? '') }}"
                    >
                </div>

                <div class="attendance-signature">
                    <label for="signature-pad">
                        Signature
                    </label>

                    <canvas
                        id="signature-pad"
                        width="600"
                        height="200"
                        style="width: 100%; height: 180px; border: 1px solid #ccc; border-radius: 6px; background: white;"
                    ></canvas>

                    <input
                        type="hidden"
                        name="signature"
                        id="signature"
                        value="{{ old('signature') }}"
                    >

                    <div class="attendance-form-actions">
                        <button type="button" id="clear-signature" class="btn btn-secondary btn-sm">
                            Clear Signature
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            Submit Attendance
                        </button>
                    </div>
                </div>
                </div>
            @endif

        </form>

    @else

        <p>
            This event has no sessions.
        </p>

    @endif

    <details class="attendance-lookup-shortcut" @if (session('pre_registration_verified') || session('pre_registration_not_verified') || session('pre_registration_awaiting_approval') || session('pre_registration_not_found') || $errors->has('lookup_email') || $errors->has('lookup_phone')) open @endif>
        <summary>
            Already pre-registered?<span class="lookup-action">Check pre-registration</span>
        </summary>
        <div class="lookup-content">
            <form id="attendance-lookup-form" method="POST" action="{{ route('attendance.store', ['access' => $access]) }}" class="account-form attendance-lookup-form">
                @csrf
                <input type="hidden" name="action" value="lookup_pre_registration">
                <div class="field">
                    <label for="lookup_email">Email</label>
                    <input
                        type="email"
                        id="lookup_email"
                        name="lookup_email"
                        value="{{ old('lookup_email') }}"
                        autocomplete="email"
                        required
                    >
                </div>
                <div class="field">
                    <label for="lookup_phone">Phone</label>
                    <input
                        type="tel"
                        id="lookup_phone"
                        name="lookup_phone"
                        value="{{ old('lookup_phone') }}"
                        autocomplete="tel"
                        inputmode="tel"
                        required
                    >
                </div>
                <div class="attendance-lookup-action">
                    <button type="submit" class="btn btn-secondary btn-sm">Continue</button>
                </div>
            </form>

        </div>
    </details>

    @if (session('pre_registration_verified'))
        <p class="attendance-lookup-result" role="status">Your approved registration details are ready. Review them before submitting.</p>
    @elseif (session('pre_registration_awaiting_approval'))
        <p class="attendance-lookup-result" role="status">This registration is awaiting approval. You can still enter your attendance details manually.</p>
    @elseif (session('pre_registration_not_verified'))
        <p class="attendance-lookup-result" role="status">A matching registration was found. Saved details are available to the attendee or event organizer.</p>
    @elseif (session('pre_registration_not_found'))
        <p class="attendance-lookup-result" role="status">No matching pre-registration was found. You can still enter your details manually.</p>
    @endif

    <a class="back-link btn btn-secondary btn-sm" href="{{ route('attendance.pin') }}">
        Back to Attendance
    </a>

    <script>
        const sessionSelect = document.getElementById('session_id');
        const sessionName = document.getElementById('selected-session-name');
        const sessionTime = document.getElementById('selected-session-time');

        if (sessionSelect && sessionName && sessionTime) {
            const updateSessionSummary = () => {
                const option = sessionSelect.selectedOptions[0];
                if (!option) return;

                sessionName.textContent = option.dataset.name || '';
                sessionTime.textContent = `${option.dataset.start || '—'} — ${option.dataset.end || '—'}`;
            };

            sessionSelect.addEventListener('change', updateSessionSummary);
            updateSessionSummary();
        }


        const canvas = document.getElementById('signature-pad');

        if (canvas) {
            const ctx = canvas.getContext('2d');
            const signature = document.getElementById('signature');
            const clearButton = document.getElementById('clear-signature');
            const form = document.getElementById('attendance-form');

            let drawing = false;
            let hasSignature = false;
            let savedImage = null;
            let restoreReady = true;
            let restoredSignature = '';
            let drawingStarted = false;

            function resizeCanvas() {
                const rect = canvas.getBoundingClientRect();
                const dpr = window.devicePixelRatio || 1;

                if (!rect.width || !rect.height) return;

                if (hasSignature) {
                    savedImage = canvas.toDataURL('image/png');
                }

                canvas.width = Math.round(rect.width * dpr);
                canvas.height = Math.round(rect.height * dpr);

                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#000';

                if (savedImage) {
                    const image = new Image();

                    image.onload = () => {
                        ctx.drawImage(image, 0, 0, rect.width, rect.height);
                        signature.value = canvas.toDataURL('image/png');
                    };

                    image.src = savedImage;
                }
            }

            function getPosition(event) {
                const rect = canvas.getBoundingClientRect();

                return {
                    x: event.clientX - rect.left,
                    y: event.clientY - rect.top
                };
            }

            function getSubmissionSignature() {
                if (!hasSignature) return '';
                if (!restoreReady) return restoredSignature;

                const rect = canvas.getBoundingClientRect();
                const scale = Math.min(600 / rect.width, 200 / rect.height);
                const exportCanvas = document.createElement('canvas');
                exportCanvas.width = Math.round(rect.width * scale);
                exportCanvas.height = Math.round(rect.height * scale);
                exportCanvas.getContext('2d').drawImage(
                    canvas,
                    0,
                    0,
                    exportCanvas.width,
                    exportCanvas.height
                );

                return exportCanvas.toDataURL('image/png');
            }

            resizeCanvas();

            restoredSignature = signature.value;
            if (/^data:image\/png;base64,[A-Za-z0-9+/]+={0,2}$/.test(restoredSignature)) {
                hasSignature = true;
                savedImage = restoredSignature;
                restoreReady = false;
                const restoredImage = new Image();
                restoredImage.onload = () => {
                    if (!drawingStarted) {
                        const rect = canvas.getBoundingClientRect();
                        ctx.drawImage(restoredImage, 0, 0, rect.width, rect.height);
                        signature.value = canvas.toDataURL('image/png');
                        savedImage = signature.value;
                    }
                    restoreReady = true;
                };
                restoredImage.onerror = () => { restoreReady = true; };
                restoredImage.src = restoredSignature;
            }

            window.addEventListener('resize', () => {
                if (!drawing) resizeCanvas();
            });

            canvas.addEventListener('pointerdown', (event) => {
                event.preventDefault();
                drawingStarted = true;
                drawing = true;
                hasSignature = true;
                canvas.setPointerCapture(event.pointerId);

                const position = getPosition(event);

                ctx.beginPath();
                ctx.moveTo(position.x, position.y);
            });

            canvas.addEventListener('pointermove', (event) => {
                if (!drawing) return;

                event.preventDefault();

                const position = getPosition(event);

                ctx.lineTo(position.x, position.y);
                ctx.stroke();
            });

            function stopDrawing() {
                if (!drawing) return;

                drawing = false;
                signature.value = getSubmissionSignature();
            }

            canvas.addEventListener('pointerup', stopDrawing);
            canvas.addEventListener('pointercancel', stopDrawing);

            clearButton.addEventListener('click', () => {
                drawingStarted = true;
                ctx.clearRect(
                    0,
                    0,
                    canvas.width / (window.devicePixelRatio || 1),
                    canvas.height / (window.devicePixelRatio || 1)
                );

                hasSignature = false;
                savedImage = null;
                restoredSignature = '';
                restoreReady = true;
                signature.value = '';
            });

            form.addEventListener('submit', () => {
                signature.value = getSubmissionSignature();
            });
        }

        const attendanceForm = document.getElementById('attendance-form');
        const requestStatus = document.getElementById('attendance-request-status');

        if (attendanceForm && requestStatus) {
            let submitting = false;
            const submitButton = attendanceForm.querySelector('button[type="submit"]');

            attendanceForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (submitting || !attendanceForm.reportValidity()) return;

                submitting = true;
                requestStatus.hidden = true;
                if (submitButton) submitButton.disabled = true;

                try {
                    const response = await fetch(attendanceForm.action, {
                        method: 'POST',
                        body: new FormData(attendanceForm),
                        credentials: 'same-origin',
                        headers: { Accept: 'text/html' },
                    });

                    if (response.ok && response.headers.get('content-type')?.includes('text/html')) {
                        document.open();
                        document.write(await response.text());
                        document.close();
                        return;
                    }

                    requestStatus.textContent = response.status === 419
                        ? 'This page expired before attendance could be confirmed. Reload it and try again.'
                        : 'The server could not confirm your attendance. Your entries are still here; try again in a moment. Duplicate check-in protection remains active.';
                } catch (error) {
                    requestStatus.textContent = 'A server connection is required to submit attendance. Your attendance was not confirmed; reconnect and try again. Duplicate check-in protection remains active.';
                }

                requestStatus.hidden = false;
                submitting = false;
                if (submitButton) submitButton.disabled = false;
            });
        }

    </script>
    <script src="{{ asset('js/attendance-service-worker-cleanup.js') }}" defer></script>
    </div>
@endsection
