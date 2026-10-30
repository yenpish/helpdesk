@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>Create Session</h1>
                <p>Add another session to <strong>{{ $event->name }}</strong>.</p>
            </div>
        </header>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ route('events.event-sessions.store', $event) }}">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">
                    Session Name <span class="text-danger">*</span>
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    value="{{ old('name') }}"
                    placeholder="e.g. Day 2 — Laravel Workshop"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    class="form-control"
                    rows="3"
                >{{ old('description') }}</textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="starts_at" class="form-label">
                        Session Starts <span class="text-danger">*</span>
                    </label>

                    <input
                        type="datetime-local"
                        id="starts_at"
                        name="starts_at"
                        class="form-control"
                        value="{{ old('starts_at', $event->starts_at?->format('Y-m-d\\TH:i')) }}"
                        required
                    >
                </div>

                <div class="col-md-6 mb-3">
                    <label for="ends_at" class="form-label">
                        Session Ends <span class="text-danger">*</span>
                    </label>

                    <input
                        type="datetime-local"
                        id="ends_at"
                        name="ends_at"
                        class="form-control"
                        value="{{ old('ends_at', $event->ends_at?->format('Y-m-d\\TH:i')) }}"
                        required
                    >
                </div>
            </div>

            <div class="alert alert-light border">
                <strong>Attendance</strong>

                <div class="text-muted mt-1">
                    Attendance will automatically follow this session's time
                    for now.
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                Create Session
            </button>

            <a
                href="{{ route('events.show', $event) }}"
                class="btn btn-secondary"
            >
                Cancel
            </a>
        </form>
    </div>
@endsection
