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

        .attendance-sync-status {
            margin: 0 0 16px;
            padding: 13px 15px;
            border-left: 2px solid var(--blue);
            background: var(--surface-raised);
            color: var(--text);
            font-size: 13px;
        }

        .attendance-sync-status[hidden] { display: none; }
        .attendance-sync-status strong { display: block; margin-bottom: 3px; }
        .attendance-sync-status span { display: block; color: var(--muted); line-height: 1.45; }

        .attendance-queue-entries { display: grid; gap: 8px; margin-top: 12px; }
        .attendance-queue-entry { display: flex; justify-content: space-between; align-items: center; gap: 14px; padding-top: 9px; border-top: 1px solid var(--line); }
        .attendance-queue-entry-details { min-width: 0; }
        .attendance-queue-entry-details strong,
        .attendance-queue-entry-details span { display: block; }
        .attendance-queue-entry-details span { margin-top: 2px; overflow-wrap: anywhere; }
        .attendance-queue-entry-actions { display: flex; flex: 0 0 auto; gap: 6px; }

        @media (max-width: 600px) {
            .attendance-queue-entry { align-items: flex-start; flex-direction: column; }
        }

        .attendance-sync-status[data-state="rejected"] {
            border-left-color: var(--red);
        }

        .attendance-lookup-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgb(15 23 42 / 45%);
        }

        .attendance-lookup-modal[hidden] {
            display: none;
        }

        .attendance-lookup-modal-content {
            width: min(100%, 440px);
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 12px 32px rgb(15 23 42 / 18%);
        }

        .attendance-lookup-modal-content h2 {
            margin: 0 0 10px;
        }

        .attendance-lookup-modal-content p {
            margin: 0 0 20px;
            color: var(--muted);
        }

        .attendance-lookup-modal-actions {
            display: flex;
            justify-content: flex-end;
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

            .attendance-lookup-modal-content {
                padding: 20px;
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
        <div class="error" id="attendance-submit-errors" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @else
        <div class="error" id="attendance-submit-errors" role="alert" hidden></div>
    @endif

    <div id="attendance-sync-status" class="attendance-sync-status" role="status" aria-live="polite" hidden>
        <strong data-status-heading></strong>
        <span data-status-message></span>
        <div id="attendance-queue-entries" class="attendance-queue-entries" hidden></div>
    </div>

    @if ($sessions->count())

        <form
            id="attendance-form"
            method="POST"
            action="{{ route('attendance.store', $event) }}"
            class="account-form"
            data-event-id="{{ $event->id }}"
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
                        value="{{ old('full_name') }}"
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
                        value="{{ old('email') }}"
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
                        value="{{ old('phone') }}"
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
                        value="{{ old('position') }}"
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
                        value="{{ old('unit') }}"
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

    <details class="attendance-lookup-shortcut" @if (session('pre_registration_found') || session('pre_registration_not_found') || $errors->has('lookup_email') || $errors->has('lookup_phone')) open @endif>
        <summary>
            Already pre-registered?<span class="lookup-action">Use your pre-registration details</span>
        </summary>
        <div class="lookup-content">
            <form id="attendance-lookup-form" method="POST" action="{{ route('attendance.store', $event) }}" class="account-form attendance-lookup-form" data-event-id="{{ $event->id }}">
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

    @if (session('pre_registration_found') || session('pre_registration_not_found'))
        <div class="attendance-lookup-modal" id="pre-registration-result" role="presentation">
            <section class="attendance-lookup-modal-content" role="dialog" aria-modal="true" aria-labelledby="pre-registration-result-title" aria-describedby="pre-registration-result-message" tabindex="-1">
                @if (session('pre_registration_found'))
                    <h2 id="pre-registration-result-title">Pre-registration found</h2>
                    <p id="pre-registration-result-message">
                        @if (session('pre_registration_status') === 'pending')
                            Your pre-registration is still pending approval. Your details have been filled in; please verify them before checking in.
                        @else
                            Your details have been filled in. Please verify them before checking in.
                        @endif
                    </p>
                    <div class="attendance-lookup-modal-actions">
                        <button type="button" class="btn btn-primary btn-sm" data-close-lookup-modal>Continue</button>
                    </div>
                @else
                    <h2 id="pre-registration-result-title">Pre-registration not found</h2>
                    <p id="pre-registration-result-message">No matching pre-registration was found for this event. You can enter your attendance details manually.</p>
                    <div class="attendance-lookup-modal-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-close-lookup-modal>Close</button>
                    </div>
                @endif
            </section>
        </div>
    @endif

    <a class="back-link btn btn-secondary btn-sm" href="{{ route('attendance.pin') }}">
        Back to Attendance
    </a>

    <script>
        const lookupResultModal = document.getElementById('pre-registration-result');

        if (lookupResultModal) {
            const closeLookupModal = () => {
                lookupResultModal.hidden = true;
                document.querySelector('[data-close-lookup-modal]')?.blur();
            };

            lookupResultModal.querySelector('[data-close-lookup-modal]')
                ?.addEventListener('click', closeLookupModal);

            lookupResultModal.querySelector('.attendance-lookup-modal-content')?.focus();
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !lookupResultModal.hidden) {
                    closeLookupModal();
                }
            });
        }

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

            function getPosition(event) {
                const rect = canvas.getBoundingClientRect();

                return {
                    x: (event.clientX - rect.left)
                        * (canvas.width / rect.width),

                    y: (event.clientY - rect.top)
                        * (canvas.height / rect.height)
                };
            }

            canvas.addEventListener('pointerdown', (event) => {
                canvas.dataset.restoringSignature = 'false';
                drawing = true;

                const position = getPosition(event);

                ctx.beginPath();
                ctx.moveTo(position.x, position.y);
            });

            canvas.addEventListener('pointermove', (event) => {
                if (!drawing) {
                    return;
                }

                const position = getPosition(event);

                ctx.lineTo(position.x, position.y);
                ctx.stroke();
            });

            canvas.addEventListener('pointerup', () => {
                drawing = false;
                signature.value = canvas.toDataURL('image/png');
            });

            canvas.addEventListener('pointerleave', () => {
                drawing = false;
            });

            clearButton.addEventListener('click', () => {
                canvas.dataset.restoringSignature = 'false';
                ctx.clearRect(
                    0,
                    0,
                    canvas.width,
                    canvas.height
                );

                signature.value = '';
            });

            form.addEventListener('submit', () => {
                if (canvas.dataset.restoringSignature !== 'true') {
                    signature.value = canvas.toDataURL('image/png');
                }
            });
        }
    </script>
    <script src="{{ asset('js/attendance-offline-sync.js') }}" defer></script>
    </div>
@endsection
