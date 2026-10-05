@extends('layouts.app')
@section('section', 'Configuration')

@section('content')
    <div class="container">
        <h1>Edit Location</h1>
        <p class="text-muted">Update the location.</p>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('locations.update', $location) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="name">Name</label>
                <input type="text"
                       id="name"
                       name="name"
                       class="form-control"
                       value="{{ old('name', $location->name) }}"
                       required>
            </div>

            <div class="mb-3">
                <label for="latitude">Latitude</label>
                <input type="number"
                       id="latitude"
                       name="latitude"
                       class="form-control"
                       step="any"
                       value="{{ old('latitude', $location->latitude) }}"
                       required>
            </div>

            <div class="mb-3">
                <label for="longitude">Longitude</label>
                <input type="number"
                       id="longitude"
                       name="longitude"
                       class="form-control"
                       step="any"
                       value="{{ old('longitude', $location->longitude) }}"
                       required>
            </div>

            <div class="mb-3">
                <label for="allowed_radius">Allowed Radius (metres)</label>
                <input type="number"
                       id="allowed_radius"
                       name="allowed_radius"
                       class="form-control"
                       step="0.01"
                       min="0"
                       value="{{ old('allowed_radius', $location->allowed_radius) }}"
                       required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a class="btn btn-secondary" href="{{ route('locations.show', $location) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
