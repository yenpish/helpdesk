@extends('layouts.app')

@section('title', 'Welcome')

@section('content')

    <div class="guest-home">
        <h1>Welcome to Helpdesk</h1>

        <p>
            Internal system portal for support, attendance and other company operations.
        </p>

        <div class="guest-actions">
            <a class="guest-button" href="{{ route('login') }}">
                Login
            </a>

            <a class="guest-button secondary" href="{{ route('attendance.pin') }}">
                Enter Attendance PIN
            </a>
        </div>
    </div>

@endsection
