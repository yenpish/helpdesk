@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Create Location</h1>
        <p class="text-muted">Add a location that can be assigned to events.</p>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('locations.store') }}">
            @csrf

            <div class="mb-3">
                <label for="name">Name</label>
                <input type="text"
                       id="name"
                       name="name"
                       class="form-control"
                       value="{{ old('name') }}"
                       required>
            </div>

            <div class="mb-3">
                <label for="latitude">Latitude</label>
                <input type="number"
                       id="latitude"
                       name="latitude"
                       class="form-control"
                       step="any"
                       value="{{ old('latitude') }}"
                       required>
            </div>

            <div class="mb-3">
                <label for="longitude">Longitude</label>
                <input type="number"
                       id="longitude"
                       name="longitude"
                       class="form-control"
                       step="any"
                       value="{{ old('longitude') }}"
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
                       value="{{ old('allowed_radius', 150) }}"
                       required>
            </div>

            <button type="submit" class="btn btn-primary">
                Create Location
            </button>

            <a href="{{ route('locations.index') }}">Cancel</a>
        </form>
    </div>
@endsection
