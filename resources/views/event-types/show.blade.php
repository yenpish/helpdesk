@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>{{ $eventType->name }}</h1>

            <div>
                <a href="{{ route('event-types.edit', $eventType) }}">
                    Edit
                </a>

                <a href="{{ route('event-types.index') }}">
                    Back
                </a>
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
                    {{ $eventType->name }}
                </p>

                <p>
                    <strong>Description:</strong>
                    {{ $eventType->description ?: '—' }}
                </p>

                <p class="mb-0">
                    <strong>Created:</strong>
                    {{ $eventType->created_at?->format('d M Y, h:i A') ?? '—' }}
                </p>
            </div>
        </div>

        <h2>Events Using This Type</h2>

        @if($eventType->events->count())
            <ul>
                @foreach($eventType->events as $event)
                    <li>
                        <a href="{{ route('events.show', $event) }}">
                            {{ $event->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p>No events currently use this type.</p>
        @endif
    </div>
@endsection
