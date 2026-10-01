@extends('layouts.app')

@section('title', 'Event Attendance')

@section('content')

    <h1>Event Attendance</h1>

    <p class="subtitle">
        Enter the 4-character event access code.
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
                Event Access Code
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

    <a class="back-link" href="{{ route('home') }}">
        Back to Home
    </a>

@endsection
