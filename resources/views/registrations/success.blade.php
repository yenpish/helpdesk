@extends('layouts.app')

@section('content')
    <div class="container">
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

        <a href="{{ route('registrations.public-index') }}">
            Back to Events
        </a>
    </div>
@endsection
