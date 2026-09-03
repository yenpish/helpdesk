@extends('layouts.app')

@section('title', 'Create Attendance Session')

@section('content')

    <h1>Create Attendance Session</h1>

    <p>
        <a class="page-link" href="{{ route('home') }}">Back to Home</a>
        <a class="page-link" href="{{ route('attendance-events.index') }}">Back to Sessions</a>
    </p>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('attendance-events.store') }}">

        @csrf

        <div class="field">
            <label for="name">Workshop / Event</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
                required
            >
        </div>

        <div class="field">
            <label for="location_id">Location</label>

            <select id="location_id" name="location_id" required>
                <option value="">Select a location</option>

                @foreach ($locations as $location)
                    <option
                        value="{{ $location->id }}"
                        {{ old('location_id') == $location->id ? 'selected' : '' }}
                    >
                        {{ $location->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="start_date">Start Date</label>
            <input
                type="date"
                id="start_date"
                name="start_date"
                value="{{ old('start_date') }}"
                required
            >
        </div>

        <div class="field">
            <label for="end_date">End Date</label>
            <input
                type="date"
                id="end_date"
                name="end_date"
                value="{{ old('end_date') }}"
                required
            >
        </div>

        <div class="field">
            <label for="start_time">Start Time</label>
            <input
                type="time"
                id="start_time"
                name="start_time"
                value="{{ old('start_time') }}"
                required
            >
        </div>

        <div class="field">
            <label for="end_time">End Time</label>
            <input
                type="time"
                id="end_time"
                name="end_time"
                value="{{ old('end_time') }}"
                required
            >
        </div>

        <button type="submit">
            Create Session
        </button>

    </form>

@endsection
