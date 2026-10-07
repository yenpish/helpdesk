@extends('layouts.app')
@section('section', 'Configuration')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>Event Types</h1>
                <p class="text-muted mb-0">Manage event categories used by events.</p>
            </div>

            <a href="{{ route('event-types.create') }}" class="btn btn-primary">
                Create Event Type
            </a>
        </header>

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

                        <td class="table-actions">
                            <a class="btn btn-secondary btn-sm" href="{{ route('event-types.show', $eventType) }}">
                                View
                            </a>

                            <a class="btn btn-secondary btn-sm" href="{{ route('event-types.edit', $eventType) }}">
                                Edit
                            </a>

                            <form class="inline-form" action="{{ route('event-types.destroy', $eventType) }}"
                                  method="POST"
                                  style="display:inline"
                                  onsubmit="return confirm('Delete this event type?');">
                                @csrf
                                @method('DELETE')

                                <button class="btn btn-danger btn-sm" type="submit">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @include('components.pagination-row', ['paginator' => $eventTypes, 'ariaLabel' => 'Event type pages'])
        @else
            <p>No event types found.</p>
        @endif
    </div>
@endsection
