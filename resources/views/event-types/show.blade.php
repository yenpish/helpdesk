@extends('layouts.app')
@section('section', 'Configuration')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading"><h1>{{ $eventType->name }}</h1><p>Event type details</p></div>

            <div class="d-flex gap-2 flex-wrap header-actions">
                <a class="btn btn-primary" href="{{ route('event-types.edit', $eventType) }}">
                    Edit
                </a>

                <a class="btn btn-secondary" href="{{ route('event-types.index') }}">
                    Back to Event Types
                </a>
            </div>
        </header>

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
