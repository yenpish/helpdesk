@extends('layouts.app')

@section('title', 'Welcome')

@section('content')

    <div class="guest-home">
        <h1>Welcome to Attendance Management</h1>

        <p>
            Attendance management portal for events, sessions, and attendance registration.
        </p>

        <div class="guest-actions">
            <a class="guest-button" href="{{ route('registrations.public-index') }}">
                Browse Events
            </a>

            <a class="guest-button secondary" href="{{ route('attendance.pin') }}">
                Enter Attendance PIN
            </a>
        </div>
    </div>

@endsection
