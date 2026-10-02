@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <h1>Edit Session</h1>

        <p class="text-muted">
            Update <strong>{{ $eventSession->name }}</strong>
            under <strong>{{ $event->name }}</strong>.
        </p>

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
              action="{{ route('events.event-sessions.update', [$event, $eventSession]) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="name" class="form-label">
                    Session Name <span class="text-danger">*</span>
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    value="{{ old('name', $eventSession->name) }}"
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
                >{{ old('description', $eventSession->description) }}</textarea>
            </div>

            <div class="mb-3">
                <label for="session_date" class="form-label">
                    Session Date <span class="text-danger">*</span>
                </label>

                <input
                    type="date"
                    id="session_date"
                    name="session_date"
                    class="form-control"
                    value="{{ old('session_date', $eventSession->starts_at?->format('Y-m-d')) }}"
                    required
                >
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="start_time" class="form-label">
                        Start Time <span class="text-danger">*</span>
                    </label>

                    <input
                        type="time"
                        id="start_time"
                        name="start_time"
                        class="form-control"
                        value="{{ old('start_time', $eventSession->starts_at?->format('H:i')) }}"
                        required
                    >
                </div>

                <div class="col-md-6 mb-3">
                    <label for="end_time" class="form-label">
                        End Time <span class="text-danger">*</span>
                    </label>

                    <input
                        type="time"
                        id="end_time"
                        name="end_time"
                        class="form-control"
                        value="{{ old('end_time', $eventSession->ends_at?->format('H:i')) }}"
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
                Save Changes
            </button>

            <a
                href="{{ route('events.event-sessions.show', [$event, $eventSession]) }}"
                class="btn btn-secondary"
            >
                Cancel
            </a>
        </form>
    </div>
@endsection
