@extends('layouts.app')

@section('title', 'Attendance')

@section('content')

    <h1>Attendance</h1>

    <p class="subtitle">
        Enter the attendance PIN to continue
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
            <label for="pin">Attendance PIN</label>

            <input
                type="text"
                id="pin"
                name="pin"
                maxlength="4"
                inputmode="numeric"
                placeholder="Enter 4-digit PIN"
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
