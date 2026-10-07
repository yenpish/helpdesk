@extends('layouts.app')
@section('section', 'Configuration')

@section('content')
    <div class="container">
        <header class="page-header"><div class="page-heading"><h1>Create Event Type</h1><p>Add a configurable event type.</p></div></header>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('event-types.store') }}">
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
                <label for="description">Description</label>

                <textarea id="description"
                          name="description"
                          class="form-control"
                          rows="4">{{ old('description') }}</textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Event Type</button>
                <a class="btn btn-secondary" href="{{ route('event-types.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
