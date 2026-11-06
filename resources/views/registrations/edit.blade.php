@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>Edit pre-registration</h1>
                <p>{{ $event->name }} · {{ $registration->guest_name }} · {{ ucfirst($registration->status) }}</p>
            </div>
        </header>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('events.registrations.details', [$event, $registration]) }}">
                    @csrf
                    @method('PATCH')
                    @foreach($returnParameters as $key => $value)
                        <input type="hidden" name="_return_{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="guest_name" class="form-label">Full name</label>
                            <input id="guest_name" name="guest_name" class="form-control" required maxlength="255" value="{{ old('guest_name', $registration->guest_name) }}">
                            @error('guest_name')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="guest_email" class="form-label">Email</label>
                            <input id="guest_email" type="email" name="guest_email" class="form-control" required maxlength="255" value="{{ old('guest_email', $registration->guest_email) }}">
                            @error('guest_email')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="guest_phone" class="form-label">Phone</label>
                            <input id="guest_phone" type="tel" name="guest_phone" class="form-control" required maxlength="50" value="{{ old('guest_phone', $registration->guest_phone) }}">
                            @error('guest_phone')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="position" class="form-label">Position</label>
                            <input id="position" name="position" class="form-control" maxlength="255" value="{{ old('position', $registration->position) }}">
                            @error('position')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 mb-3">
                            <label for="organisation" class="form-label">Organisation</label>
                            <input id="organisation" name="organisation" class="form-control" maxlength="255" value="{{ old('organisation', $registration->organisation) }}">
                            @error('organisation')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="{{ route('events.registrations.index', [$event] + $returnParameters) }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
