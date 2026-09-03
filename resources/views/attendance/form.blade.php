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

    <form method="POST" action="{{ route('attendance.store', $event) }}">

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
                type="text"
                id="phone"
                name="phone"
                value="{{ old('phone') }}"
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

        <div class="field">
            <label for="signature">Signature</label>
            <textarea id="signature" name="signature">{{ old('signature') }}</textarea>
        </div>

        <button type="submit">
            Submit Attendance
        </button>

    </form>

    <a class="back-link" href="{{ route('attendance.pin') }}">
        Back to PIN
    </a>

@endsection
