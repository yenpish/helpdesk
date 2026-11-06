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

        @if(session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif

        @if($locations->count())
            <div class="table-responsive">
            <table class="table locations-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th class="table-numeric">Latitude</th>
                    <th class="table-numeric">Longitude</th>
                    <th class="table-numeric">Radius</th>
                    <th class="table-numeric">Events</th>
                    <th class="table-actions">Actions</th>
                </tr>
                </thead>

                <tbody>
                @foreach($locations as $location)
                    <tr>
                        <td>{{ $location->name }}</td>
                        <td class="table-numeric table-cell-nowrap">{{ $location->latitude }}</td>
                        <td class="table-numeric table-cell-nowrap">{{ $location->longitude }}</td>
                        <td class="table-numeric table-cell-nowrap">{{ $location->allowed_radius }} m</td>
                        <td class="table-numeric">{{ $location->events()->count() }}</td>

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
            </div>

            @include('components.pagination-row', ['paginator' => $locations, 'ariaLabel' => 'Location pages'])
        @else
            <p>No locations found.</p>
        @endif
    </div>
@endsection
