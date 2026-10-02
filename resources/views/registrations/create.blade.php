@extends('layouts.app')
@section('section', 'Registration')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>Register for {{ $event->name }}</h1>
                <p>Provide your details to request a place at this event.</p>
            </div>
            <a href="{{ route('registrations.public-index') }}" class="btn btn-secondary">Back to events</a>
        </header>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif

        <div class="content-panel">
        <form method="POST" action="{{ route('registrations.store', $event) }}" class="account-form">
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
                @error('guest_name')<div class="field-error">{{ $message }}</div>@enderror
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
                @error('guest_email')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="guest_phone">Phone *</label>
                <input
                    type="tel"
                    id="guest_phone"
                    name="guest_phone"
                    value="{{ old('guest_phone') }}"
                    autocomplete="tel"
                    inputmode="tel"
                    required
                >
                @error('guest_phone')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="organisation">Organisation</label>
                <input
                    type="text"
                    id="organisation"
                    name="organisation"
                    value="{{ old('organisation') }}"
                >
                @error('organisation')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="position">Position</label>
                <input
                    type="text"
                    id="position"
                    name="position"
                    value="{{ old('position') }}"
                >
                @error('position')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <p class="form-note">Your registration will be submitted as pending and reviewed by the event organizer.</p>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit registration</button>
                <a href="{{ route('registrations.public-index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
        </div>
    </div>
@endsection
