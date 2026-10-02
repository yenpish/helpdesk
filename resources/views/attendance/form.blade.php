@extends('layouts.app')
@section('section', 'Attendance')

@section('title', $event->name . ' - Attendance')

@section('content')
    <div class="content-panel attendance-panel">
    <h1>{{ $event->name }}</h1>

    @if ($event->location)
        <p>
            <strong>Location:</strong>
            {{ $event->location->name }}
        </p>
    @endif

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <h2>Select Session</h2>

    @if ($sessions->count())

        <form
            id="attendance-form"
            method="POST"
            action="{{ route('attendance.store', $event) }}"
            class="account-form"
        >
            @csrf

            <div class="field">
                <label for="session_id">
                    Session
                </label>

                <select
                    id="session_id"
                    name="session_id"
                    required
                >
                    @foreach ($sessions as $session)

                        <option
                            value="{{ $session->id }}"
                            @selected(
                                $defaultSession &&
                                $defaultSession->id === $session->id
                            )
                            @disabled(!$session->attendance_available)
                        >
                            {{ $session->name }}
                            —
                            {{ $session->starts_at?->format('d M Y, h:i A') }}

                            @if (!$session->attendance_available)
                                (Not available)
                            @elseif (
                                $session->starts_at &&
                                $session->starts_at->isSameDay(now())
                            )
                                (Today)
                            @endif
                        </option>

                    @endforeach
                </select>
            </div>

            @if (!$defaultSession)
                <div class="error">
                    Attendance is not currently available for any session.
                </div>
            @else

                <div class="event-meta">
                    <p>
                        <strong>Time</strong><br>
                        {{ $defaultSession->starts_at?->format('d/m/Y H:i') }}
                        —
                        {{ $defaultSession->ends_at?->format('d/m/Y H:i') }}
                    </p>
                </div>

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
                        Use the same email used during registration if you
                        pre-enrolled for this event.
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

                <div class="field">
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

                <div>
                    <label for="signature-pad">
                        Signature
                    </label>

                    <canvas
                        id="signature-pad"
                        width="600"
                        height="200"
                        style="width: 100%; height: 200px; border: 1px solid #ccc; border-radius: 6px; background: white;"
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

            @endif

        </form>

    @else

        <p>
            This event has no sessions.
        </p>

    @endif

    <a class="back-link btn btn-secondary btn-sm" href="{{ route('attendance.pin') }}">
        Back to Attendance
    </a>

    <script>
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
                ctx.clearRect(
                    0,
                    0,
                    canvas.width,
                    canvas.height
                );

                signature.value = '';
            });

            form.addEventListener('submit', () => {
                signature.value = canvas.toDataURL('image/png');
            });
        }
    </script>
    </div>
@endsection
