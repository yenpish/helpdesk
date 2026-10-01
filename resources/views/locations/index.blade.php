@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Locations</h1>
                <p class="text-muted mb-0">Manage locations used by events.</p>
            </div>

            <a href="{{ route('locations.create') }}" class="btn btn-primary">
                Create Location
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($locations->count())
            <table class="table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Radius</th>
                    <th>Events</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                @foreach($locations as $location)
                    <tr>
                        <td>{{ $location->name }}</td>
                        <td>{{ $location->latitude }}</td>
                        <td>{{ $location->longitude }}</td>
                        <td>{{ $location->allowed_radius }} m</td>
                        <td>{{ $location->events()->count() }}</td>

                        <td>
                            <a href="{{ route('locations.show', $location) }}">View</a>
                            <a href="{{ route('locations.edit', $location) }}">Edit</a>

                            <form action="{{ route('locations.destroy', $location) }}"
                                  method="POST"
                                  style="display:inline"
                                  onsubmit="return confirm('Delete this location?');">
                                @csrf
                                @method('DELETE')

                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="d-flex justify-content-between mt-3">
                @if($locations->onFirstPage())
                    <span class="text-muted">Previous</span>
                @else
                    <a href="{{ $locations->previousPageUrl() }}">Previous</a>
                @endif

                <span>
                Page {{ $locations->currentPage() }}
                of {{ $locations->lastPage() }}
            </span>

                @if($locations->hasMorePages())
                    <a href="{{ $locations->nextPageUrl() }}">Next</a>
                @else
                    <span class="text-muted">Next</span>
                @endif
            </div>
        @else
            <p>No locations found.</p>
        @endif
    </div>
@endsection
