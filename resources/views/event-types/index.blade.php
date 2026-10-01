@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Event Types</h1>
                <p class="text-muted mb-0">Manage event categories used by events.</p>
            </div>

            <a href="{{ route('event-types.create') }}" class="btn btn-primary">
                Create Event Type
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($eventTypes->count())
            <table class="table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Events</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                @foreach($eventTypes as $eventType)
                    <tr>
                        <td>{{ $eventType->name }}</td>

                        <td>
                            {{ $eventType->description ?: '—' }}
                        </td>

                        <td>
                            {{ $eventType->events()->count() }}
                        </td>

                        <td>
                            <a href="{{ route('event-types.show', $eventType) }}">
                                View
                            </a>

                            <a href="{{ route('event-types.edit', $eventType) }}">
                                Edit
                            </a>

                            <form action="{{ route('event-types.destroy', $eventType) }}"
                                  method="POST"
                                  style="display:inline"
                                  onsubmit="return confirm('Delete this event type?');">
                                @csrf
                                @method('DELETE')

                                <button type="submit">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="mt-3">
                <div class="d-flex justify-content-between">
                    @if($eventTypes->onFirstPage())
                        <span class="text-muted">Previous</span>
                    @else
                        <a href="{{ $eventTypes->previousPageUrl() }}">Previous</a>
                    @endif

                    <span>
                    Page {{ $eventTypes->currentPage() }}
                    of {{ $eventTypes->lastPage() }}
                </span>

                    @if($eventTypes->hasMorePages())
                        <a href="{{ $eventTypes->nextPageUrl() }}">Next</a>
                    @else
                        <span class="text-muted">Next</span>
                    @endif
                </div>
            </div>
        @else
            <p>No event types found.</p>
        @endif
    </div>
@endsection
