@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Upcoming Events</h1>

        @if($events->count())
            @foreach($events as $event)
                <div class="card mb-3">
                    <div class="card-body">
                        <h3>{{ $event->name }}</h3>

                        @if($event->description)
                            <p>
                                {{ \Illuminate\Support\Str::limit($event->description, 200) }}
                            </p>
                        @endif

                        <p>
                            <strong>Starts:</strong>
                            {{ $event->starts_at?->format('d M Y, h:i A') ?? 'TBA' }}
                        </p>

                        <p>
                            <strong>Location:</strong>
                            {{ $event->location?->name ?? 'TBA' }}
                        </p>

                        @if($event->eventType)
                            <p>
                                <strong>Type:</strong>
                                {{ $event->eventType->name }}
                            </p>
                        @endif

                        <a href="{{ route('registrations.create', $event) }}">
                            Register
                        </a>
                    </div>
                </div>
            @endforeach

            {{ $events->links() }}
        @else
            <p>No upcoming events are currently available for registration.</p>
        @endif
    </div>
@endsection
