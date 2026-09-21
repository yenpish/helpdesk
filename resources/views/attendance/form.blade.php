@extends('layouts.app')

@section('title', $event->name . ' - Attendance')

@section('content')

    <h1>{{ $event->name }}</h1>

    <div class="event-meta">
        <p>
            <strong>Location</strong><br>
            {{ $event->location->name }}
        </p>

        <p>
            <strong>Date & Time</strong><br>
            {{ $event->starts_at->format('d/m/Y H:i') }}
            —
            {{ $event->ends_at->format('d/m/Y H:i') }}
        </p>
    </div>

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <h2>Attendance Details</h2>

    <form id="attendance-form" method="POST" action="{{ route('attendance.store', $event) }}">

        @csrf

        <div class="field">
            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                value="{{ old('full_name') }}"
                required
            >
        </div>

        <div class="field">
            <label for="position">Position</label>
            <input
                type="text"
                id="position"
                name="position"
                value="{{ old('position') }}"
            >
        </div>

        <div class="field">
            <label for="unit">Unit / Organization</label>
            <input
                type="text"
                id="unit"
                name="unit"
                value="{{ old('unit') }}"
            >
        </div>

        <div class="field">
            <label for="phone">Phone</label>
            <input
                type="tel"
                name="phone"
                id="phone"
                value="{{ old('phone') }}"
                required
            >
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
            >
        </div>

        <div>
            <label for="signature-pad">Signature</label>

            <canvas id="signature-pad" width="600" height="200"
                    style="width: 100%; max-width: 600px; border: 1px solid #ccc; border-radius: 6px; background: white;">
            </canvas>

            <input type="hidden" name="signature" id="signature">

            <button type="button" id="clear-signature">
                Clear Signature
            </button>
        </div>

        <button type="submit">
            Submit Attendance
        </button>

    </form>

    <a class="back-link" href="{{ route('attendance.pin') }}">
        Back to PIN
    </a>

    <script>
        const canvas = document.getElementById('signature-pad');
        const ctx = canvas.getContext('2d');
        const signature = document.getElementById('signature');
        const clearButton = document.getElementById('clear-signature');
        const form = document.getElementById('attendance-form');

        let drawing = false;

        function getPosition(event) {
            const rect = canvas.getBoundingClientRect();

            return {
                x: (event.clientX - rect.left) * (canvas.width / rect.width),
                y: (event.clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        canvas.addEventListener('pointerdown', (event) => {
            drawing = true;
            const position = getPosition(event);

            ctx.beginPath();
            ctx.moveTo(position.x, position.y);
        });

        canvas.addEventListener('pointermove', (event) => {
            if (!drawing) return;

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
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            signature.value = '';
        });

        form.addEventListener('submit', () => {
            signature.value = canvas.toDataURL('image/png');
        });
    </script>

@endsection
