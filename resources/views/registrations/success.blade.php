@extends('layouts.app')
@section('section', 'Pre-registration')

@section('content')
    <div class="container">
        <div class="content-panel registration-success-panel">
        <h1>Pre-registration submitted</h1>

        <p>
            Your pre-registration for
            <strong>{{ $registration->event->name }}</strong>
            has been submitted successfully.
        </p>

        <p>
            The event organizer will review your pre-registration.
        </p>

        <a class="btn btn-secondary" href="{{ route('registrations.public-index') }}">Back to Events</a>
        </div>
    </div>
@endsection
