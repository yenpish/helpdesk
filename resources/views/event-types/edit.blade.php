@extends('layouts.app')
@section('section', 'Configuration')

@section('content')
    <div class="container">
        <h1>Edit Event Type</h1>
        <p class="text-muted">Update the event type.</p>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('event-types.update', $eventType) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="name">Name</label>

                <input type="text"
                       id="name"
                       name="name"
                       class="form-control"
                       value="{{ old('name', $eventType->name) }}"
                       required>
            </div>

            <div class="mb-3">
                <label for="description">Description</label>

                <textarea id="description"
                          name="description"
                          class="form-control"
                          rows="4">{{ old('description', $eventType->description) }}</textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a class="btn btn-secondary" href="{{ route('event-types.show', $eventType) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
