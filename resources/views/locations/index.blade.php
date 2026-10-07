@extends('layouts.app')
@section('section', 'Configuration')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>Locations</h1>
                <p class="text-muted mb-0">Manage locations used by events.</p>
            </div>

            <a href="{{ route('locations.create') }}" class="btn btn-primary">
                Create Location
            </a>
        </header>

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

                        <td class="table-actions">
                            <a class="btn btn-secondary btn-sm" href="{{ route('locations.show', $location) }}">View</a>
                            <a class="btn btn-secondary btn-sm" href="{{ route('locations.edit', $location) }}">Edit</a>

                            <form class="inline-form" action="{{ route('locations.destroy', $location) }}"
                                  method="POST"
                                  style="display:inline"
                                  onsubmit="return confirm('Delete this location?');">
                                @csrf
                                @method('DELETE')

                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @include('components.pagination-row', ['paginator' => $locations, 'ariaLabel' => 'Location pages'])
        @else
            <p>No locations found.</p>
        @endif
    </div>
@endsection
