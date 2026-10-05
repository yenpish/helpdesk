@extends('layouts.app')
@section('section', 'Attendance')

@section('title', 'Event Attendance')

@section('content')
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

    <form method="POST" action="{{ route('attendance.verify') }}">
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
@endsection
