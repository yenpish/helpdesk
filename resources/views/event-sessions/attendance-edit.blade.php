@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>Edit Attendance</h1>
                <p>{{ $event->name }} · {{ $eventSession->name }} · {{ $attendance->full_name }}</p>
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
                <form method="POST" action="{{ route('events.event-sessions.attendance.update', [$event, $eventSession, $attendance]) }}">
                    @csrf
                    @method('PATCH')
                    @foreach($returnParameters as $key => $value)
                        <input type="hidden" name="_return_{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full name</label>
                            <input id="full_name" name="full_name" class="form-control" required maxlength="255" value="{{ old('full_name', $attendance->full_name) }}">
                            @error('full_name')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input id="email" type="email" name="email" class="form-control" required maxlength="255" value="{{ old('email', $attendance->email) }}">
                            @error('email')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input id="phone" type="tel" name="phone" class="form-control" required maxlength="50" value="{{ old('phone', $attendance->phone) }}">
                            @error('phone')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="position" class="form-label">Position</label>
                            <input id="position" name="position" class="form-control" maxlength="255" value="{{ old('position', $attendance->position) }}">
                            @error('position')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 mb-3">
                            <label for="unit" class="form-label">Organisation</label>
                            <input id="unit" name="unit" class="form-control" maxlength="255" value="{{ old('unit', $attendance->unit) }}">
                            @error('unit')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="{{ route('events.event-sessions.show', [$event, $eventSession] + $returnParameters) }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
