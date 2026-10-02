@extends('layouts.app')
@section('section', 'Configuration')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>{{ $location->name }}</h1>

            <div class="d-flex gap-2">
                <a class="btn btn-primary" href="{{ route('locations.edit', $location) }}">Edit</a>
                <a class="btn btn-secondary" href="{{ route('locations.index') }}">Back to Locations</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <p>
                    <strong>Name:</strong>
                    {{ $location->name }}
                </p>

                <p>
                    <strong>Latitude:</strong>
                    {{ $location->latitude }}
                </p>

                <p>
                    <strong>Longitude:</strong>
                    {{ $location->longitude }}
                </p>

                <p>
                    <strong>Allowed Radius:</strong>
                    {{ $location->allowed_radius }} metres
                </p>

                <p class="mb-0">
                    <strong>Created:</strong>
                    {{ $location->created_at?->format('d M Y, h:i A') ?? '—' }}
                </p>
            </div>
        </div>

        <h2>Events Using This Location</h2>

        @if($location->events->count())
            <ul>
                @foreach($location->events as $event)
                    <li>
                        <a href="{{ route('events.show', $event) }}">
                            {{ $event->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p>No events currently use this location.</p>
        @endif
    </div>
@endsection
