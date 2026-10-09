@extends('layouts.app')
@section('section', 'Attendance')

@section('title', 'Event Attendance')

@section('content')
    <style>
        .attendance-pin-status { margin: 0 0 14px; padding: 13px 15px; border-left: 2px solid var(--blue); background: var(--surface-raised); color: var(--text); font-size: 13px; }
        .attendance-pin-status[hidden] { display: none; }
        .attendance-pin-status strong, .attendance-pin-status span { display: block; }
        .attendance-pin-status strong { margin-bottom: 3px; }
        .attendance-pin-status span { color: var(--muted); line-height: 1.45; }
    </style>
    <div class="content-panel attendance-pin-panel">
    <h1>Attendance PIN</h1>

    <p class="subtitle">
        Enter the 4-character Attendance PIN.
    </p>

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div id="attendance-pin-status" class="attendance-pin-status" role="status" aria-live="polite" hidden>
        <strong data-status-heading></strong>
        <span data-status-message></span>
    </div>

    <form id="attendance-pin-form" method="POST" action="{{ route('attendance.verify') }}">
        @csrf

        <div class="field">
            <label for="pin">
                Attendance PIN
            </label>

            <input
                type="text"
                id="pin"
                name="pin"
                maxlength="4"
                autocomplete="off"
                autocapitalize="characters"
                placeholder="e.g. K7X4"
                required
            >
        </div>

        <button type="submit">
            Continue
        </button>
    </form>

    <a class="back-link btn btn-secondary btn-sm" href="{{ route('home') }}">
        Back to Home
    </a>
    </div>
    <script src="{{ asset('js/attendance-pin.js') }}" defer></script>
@endsection
