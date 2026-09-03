@extends('layouts.app')

@section('title', 'Attendance Submitted')

@section('content')

    <div class="success-page">

        <div class="success-icon">✓</div>

        <h1>Attendance Submitted</h1>

        <p>
            Your attendance has been recorded successfully.
        </p>

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
