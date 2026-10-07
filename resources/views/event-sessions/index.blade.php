@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>{{ $event->name }} — Sessions</h1>
                <p class="text-muted mb-0">Manage sessions for this event.</p>
            </div>

            <div class="d-flex gap-2 flex-wrap header-actions">
                <a href="{{ route('events.event-sessions.create', $event) }}" class="btn btn-primary">
                    Create Session
                </a>

                <a href="{{ route('events.show', $event) }}" class="btn btn-secondary">
                    Back to Event
                </a>
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($event->sessions->count())
            <div class="table-responsive">
            <table class="table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Starts</th>
                    <th>Ends</th>
                    <th>Attendance Opens</th>
                    <th>Attendance Closes</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                @foreach($event->sessions as $session)
                    <tr>
                        <td>{{ $session->name }}</td>

                        <td>
                            {{ $session->starts_at?->format('d M Y, h:i A') ?? '—' }}
                        </td>

                        <td>
                            {{ $session->ends_at?->format('d M Y, h:i A') ?? '—' }}
                        </td>

                        <td>
                            {{ $session->attendance_opens_at?->format('d M Y, h:i A') ?? '—' }}
                        </td>

                        <td>
                            {{ $session->attendance_closes_at?->format('d M Y, h:i A') ?? '—' }}
                        </td>

                        <td class="table-actions">
                            <a class="btn btn-secondary btn-sm" href="{{ route('events.event-sessions.show', [$event, $session]) }}">View</a>
                            <a class="btn btn-secondary btn-sm" href="{{ route('events.event-sessions.edit', [$event, $session]) }}">Edit</a>

                            <form class="inline-form" action="{{ route('events.event-sessions.destroy', [$event, $session]) }}"
                                  method="POST" onsubmit="return confirm('Delete this session?');">
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
        @else
            <p>No sessions have been created for this event.</p>
        @endif
    </div>
@endsection
