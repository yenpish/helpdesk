@extends('layouts.app')
@section('section', 'Registration')

@section('content')
    <div class="container">
        <div class="content-panel registration-success-panel">
        <h1>Registration Submitted</h1>

        <p>
            Your registration for
            <strong>{{ $registration->event->name }}</strong>
            has been submitted successfully.
        </p>

        <p>
            <strong>Name:</strong>
            {{ $registration->guest_name }}
        </p>

        <p>
            <strong>Email:</strong>
            {{ $registration->guest_email }}
        </p>

        <p>
            <strong>Status:</strong>
            {{ ucfirst($registration->status) }}
        </p>

        <p>
            Your registration is currently pending review.
        </p>

        <a class="btn btn-secondary" href="{{ route('registrations.public-index') }}">Back to Events</a>
        </div>
    </div>
@endsection
