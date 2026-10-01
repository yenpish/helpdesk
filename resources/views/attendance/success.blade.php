@extends('layouts.app')

@section('title', 'Attendance Submitted')

@section('content')

    <div class="success-page">

        <div class="success-icon">✓</div>

        <h1>Attendance Submitted</h1>

        <p>
            Your attendance has been recorded successfully.
        </p>

        @if(isset($event) && isset($session))
            <p>
                <strong>Event:</strong>
                {{ $event->name }}
            </p>

            <p>
                <strong>Session:</strong>
                {{ $session->name }}
            </p>
        @endif

        @if(isset($registration))
            <p>
                @if($registration)
                    Your attendance matched an existing registration
                    for this event.
                @else
                    No matching pre-registration was found.
                    Your attendance was still recorded.
                @endif
            </p>
        @endif

        <div class="success-links">
            <a href="{{ route('attendance.pin') }}">
                Back to Attendance
            </a>

            <a href="{{ route('home') }}">
                Back to Home
            </a>
        </div>

    </div>

@endsection
