@extends('layouts.app')

@section('title', 'Events')
@section('section', 'Event management')

@section('content')
    <style>
        .events-page .btn.events-deleted-button {
            background: color-mix(in srgb, var(--blue) 8%, var(--surface)) !important;
            border-color: var(--blue) !important;
            color: var(--blue) !important;
        }

        .events-page .btn.events-deleted-button:hover {
            background: color-mix(in srgb, var(--blue) 14%, var(--surface)) !important;
        }
    </style>

    <div class="container events-page">
        <header class="page-header">
            <div class="page-heading">
                <h1>Events</h1>
                <p>Manage events, sessions, and pre-registrations.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('events.deleted') }}" class="btn btn-outline-primary events-deleted-button">Deleted</a>
                <a href="{{ route('events.create') }}" class="btn btn-primary">Create event</a>
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('events.index') }}" class="events-filter-form">
            <div class="events-search-field">
                <label for="event-search" class="form-label">Search events</label>
                <input
                    id="event-search"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    class="form-control"
                    placeholder="Name, description, location, organizer..."
                >
            </div>
            <div class="events-sort-field">
                <label for="event-sort" class="form-label">Sort by</label>
                <select id="event-sort" name="sort" class="form-select">
                    <option value="created_desc" @selected($sort === 'created_desc')>Recently added</option>
                    <option value="start_asc" @selected($sort === 'start_asc')>Event date: earliest</option>
                    <option value="start_desc" @selected($sort === 'start_desc')>Event date: latest</option>
                    <option value="name_asc" @selected($sort === 'name_asc')>Name: A–Z</option>
                    <option value="name_desc" @selected($sort === 'name_desc')>Name: Z–A</option>
                </select>
            </div>
            <div class="events-filter-action">
                <button type="submit" class="btn btn-secondary">Apply</button>
            </div>
            @if($search !== '' || $sort !== 'created_desc')
                <div class="events-filter-action">
                    <a href="{{ route('events.index') }}" class="btn btn-link">Clear</a>
                </div>
            @endif
        </form>

        @if($events->count())
            <div class="table-responsive events-table-responsive">
                <table class="table events-table">
                    <colgroup>
                        <col style="width: 23%"><col style="width: 10%"><col style="width: 12%"><col style="width: 10%">
                        <col style="width: 6%"><col style="width: 12%"><col style="width: 10%"><col style="width: 17%">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Organizer</th>
                        <th>Check-ins</th>
                        <th>Pre-<wbr>registrations</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($events as $event)
                        <tr>
                            <td class="event-name">
                                {{ $event->name }}
                                @if($event->eventType)
                                    <div class="event-description">{{ $event->eventType->name }}</div>
                                @endif
                                @if($event->description)
                                    <div class="event-description">{{ \Illuminate\Support\Str::limit($event->description, 70) }}</div>
                                @endif
                            </td>
                            <td class="table-cell-nowrap">{{ $event->starts_at?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $event->location?->name ?? '—' }}</td>
                            <td>{{ $event->organizer?->name ?? '—' }}</td>
                            <td class="table-cell-nowrap">{{ $event->attendees_count }}</td>
                            <td class="table-cell-nowrap">{{ $event->registrations_count }}</td>
                            <td class="table-cell-nowrap"><span class="event-status {{ $event->status }}">{{ ucfirst($event->status) }}</span></td>
                            <td class="actions-cell">
                                <a href="{{ route('events.show', $event) }}" class="btn btn-secondary btn-sm">View</a>
                                <a href="{{ route('events.edit', $event) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <form action="{{ route('events.destroy', $event) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete event ' + @js($event->name) + '? It can be restored from Deleted events.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @include('components.pagination-row', ['paginator' => $events, 'ariaLabel' => 'Event pages'])
        @else
            <div class="events-empty">
                @if($search !== '')
                    <h2>No matching events</h2>
                    <p>Try a different search term or clear the search.</p>
                    <a href="{{ route('events.index') }}" class="btn btn-secondary">Clear search</a>
                @elseif(auth()->user()->role === 'organizer')
                    <h2>No events assigned to you yet</h2>
                    <p>Organizers see the events they own. Create an event to manage its sessions and pre-registrations.</p>
                @else
                    <h2>No events yet</h2>
                    <p>Create an event to manage its sessions and pre-registrations.</p>
                @endif
            </div>
        @endif
    </div>
@endsection
