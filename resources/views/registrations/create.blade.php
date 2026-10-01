@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Register for {{ $event->name }}</h1>

        <p>
            Please provide your details below to register for this event.
        </p>

        <form method="POST" action="{{ route('registrations.store', $event) }}">
            @csrf

            <div class="mb-3">
                <label for="guest_name">Full Name *</label>
                <input
                    type="text"
                    id="guest_name"
                    name="guest_name"
                    value="{{ old('guest_name') }}"
                    required
                >
                @error('guest_name')
                <div>{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="guest_email">Email *</label>
                <input
                    type="email"
                    id="guest_email"
                    name="guest_email"
                    value="{{ old('guest_email') }}"
                    required
                >
                @error('guest_email')
                <div>{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="guest_phone">Phone</label>
                <input
                    type="text"
                    id="guest_phone"
                    name="guest_phone"
                    value="{{ old('guest_phone') }}"
                >
                @error('guest_phone')
                <div>{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="organisation">Organisation</label>
                <input
                    type="text"
                    id="organisation"
                    name="organisation"
                    value="{{ old('organisation') }}"
                >
                @error('organisation')
                <div>{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="position">Position</label>
                <input
                    type="text"
                    id="position"
                    name="position"
                    value="{{ old('position') }}"
                >
                @error('position')
                <div>{{ $message }}</div>
                @enderror
            </div>

            <button type="submit">
                Submit Registration
            </button>

            <a href="{{ route('registrations.public-index') }}">
                Cancel
            </a>
        </form>
    </div>
@endsection
