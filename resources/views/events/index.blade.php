@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Events</h1>
                <p class="text-muted mb-0">Manage attendance events.</p>
            </div>

            <a href="{{ route('events.create') }}" class="btn btn-primary">
                Create Event
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($events->count())
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Organizer</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach($events as $event)
                                <tr>
                                    <td>
                                        <strong>{{ $event->name }}</strong>
                                    </td>

                                    <td>
                                        {{ $event->eventType?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $event->location?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $event->organizer?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ ucfirst($event->status) }}
                                    </td>

                                    <td>
                                        <a href="{{ route('events.show', $event) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>

                                        <a href="{{ route('events.edit', $event) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            Edit
                                        </a>

                                        <form action="{{ route('events.destroy', $event) }}"
                                              method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('Delete this event?');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        @if($events->onFirstPage())
                            <span class="text-muted">Previous</span>
                        @else
                            <a href="{{ $events->previousPageUrl() }}">Previous</a>
                        @endif
                    </div>

                    <div>
                        Page {{ $events->currentPage() }} of {{ $events->lastPage() }}
                    </div>

                    <div>
                        @if($events->hasMorePages())
                            <a href="{{ $events->nextPageUrl() }}">Next</a>
                        @else
                            <span class="text-muted">Next</span>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-5">
                    <h4>No events found</h4>
                    <p class="text-muted">Create your first event to get started.</p>

                    <a href="{{ route('events.create') }}" class="btn btn-primary">
                        Create Event
                    </a>
                </div>
            </div>
        @endif
    </div>
@endsection
