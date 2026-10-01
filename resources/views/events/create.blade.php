@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="mb-4">
            <h1>Create Event</h1>
            <p class="text-muted">
                Create the main event. Its sessions will be created automatically.
            </p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('events.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">
                            Event Name <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            value="{{ old('name') }}"
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
                            rows="4"
                        >{{ old('description') }}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="starts_at" class="form-label">
                                Event Starts <span class="text-danger">*</span>
                            </label>

                            <input
                                type="datetime-local"
                                id="starts_at"
                                name="starts_at"
                                class="form-control"
                                value="{{ old('starts_at') }}"
                                required
                            >

                            <small class="text-muted">
                                Used as the default start time for generated sessions.
                            </small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="ends_at" class="form-label">
                                Event Ends <span class="text-danger">*</span>
                            </label>

                            <input
                                type="datetime-local"
                                id="ends_at"
                                name="ends_at"
                                class="form-control"
                                value="{{ old('ends_at') }}"
                                required
                            >

                            <small class="text-muted">
                                Multi-day events automatically receive one session per day.
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="event_type_id" class="form-label">
                            Event Type
                        </label>

                        <select
                            id="event_type_id"
                            name="event_type_id"
                            class="form-select"
                        >
                            <option value="">Select event type</option>

                            @foreach($eventTypes as $eventType)
                                <option
                                    value="{{ $eventType->id }}"
                                    @selected(old('event_type_id') == $eventType->id)
                                >
                                    {{ $eventType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="location_id" class="form-label">
                            Location
                        </label>

                        <select
                            id="location_id"
                            name="location_id"
                            class="form-select"
                        >
                            <option value="">Select location</option>

                            @foreach($locations as $location)
                                <option
                                    value="{{ $location->id }}"
                                    @selected(old('location_id') == $location->id)
                                >
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="alert alert-light border">
                        <strong>Automatically configured</strong>

                        <div class="text-muted mt-1">
                            You will be assigned as the organizer of this event.
                            The event will start as a draft until you publish it.
                            A unique event access code will also be generated automatically.
                        </div>
                    </div>

                    <div class="alert alert-light border">
                        <strong>What happens next?</strong>

                        <div class="text-muted mt-1">
                            A Session 1 will be created automatically.
                            If the event spans multiple days, one session will be
                            created for each day. You can manage individual sessions
                            afterwards.
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            Create Event
                        </button>

                        <a
                            href="{{ route('events.index') }}"
                            class="btn btn-secondary"
                        >
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
