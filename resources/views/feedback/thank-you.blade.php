@extends('layouts.app')

@section('section', 'Feedback Submitted')

@section('content')
    <div class="container">
        <div class="text-center py-5">
            <h2>Thank You!</h2>

            <p class="mt-3">
                Your feedback for <strong>{{ $event->name }}</strong>
                has been submitted successfully.
            </p>

            <p>
                We appreciate you taking the time to share your experience.
            </p>
        </div>
    </div>
@endsection
