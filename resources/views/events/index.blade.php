@extends('layouts.app')

@section('title', 'Events')

@section('content')
    <style>
        .events-page {
            max-width: 1180px;
            margin: 40px auto;
            padding: 0 24px;
        }

        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px;
            margin-bottom: 28px;
        }

        .events-heading h1 {
            margin: 0 0 8px;
            font-size: 30px;
            font-weight: 600;
            color: #222;
        }

        .events-heading p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .create-event-button {
            display: inline-block;
            padding: 10px 16px;
            background: #222;
            border: 1px solid #222;
            border-radius: 4px;
            color: white;
            font-size: 14px;
            text-decoration: none;
            white-space: nowrap;
        }

        .create-event-button:hover {
            background: #333;
            color: white;
        }

        .events-message {
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #b8d9c0;
            background: #f1f8f3;
            color: #245c31;
            font-size: 14px;
        }

        .events-table-wrapper {
            overflow-x: auto;
            background: white;
            border: 1px solid #d9d9d9;
        }

        .events-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            font-size: 14px;
        }

        .events-table th {
            padding: 13px 14px;
            background: #f6f6f6;
            border-bottom: 1px solid #d9d9d9;
            color: #555;
            font-size: 12px;
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
        }

        .events-table td {
            padding: 15px 14px;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: middle;
            color: #333;
        }

        .events-table tbody tr:last-child td {
            border-bottom: none;
        }

        .events-table tbody tr:hover {
            background: #fafafa;
        }

        .event-name {
            min-width: 190px;
        }

        .event-name a {
            color: #222;
            font-weight: 600;
            text-decoration: none;
        }

        .event-name a:hover {
            text-decoration: underline;
        }

        .event-description {
            margin-top: 4px;
            color: #777;
            font-size: 12px;
        }

        .event-status {
            display: inline-block;
            padding: 4px 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background: #f5f5f5;
            color: #555;
            font-size: 12px;
            white-space: nowrap;
        }

        .event-status.published {
            border-color: #b8d9c0;
            background: #f1f8f3;
            color: #245c31;
        }

        .event-status.cancelled {
            border-color: #e0b7b7;
            background: #fbf1f1;
            color: #8a3030;
        }

        .event-status.completed {
            border-color: #cfcfcf;
            background: #eeeeee;
            color: #555;
        }

        .event-status.draft {
            border-color: #d6d0b8;
            background: #f8f6ed;
            color: #685d32;
        }

        .event-actions {
            white-space: nowrap;
            text-align: right;
        }

        .event-action {
            display: inline-block;
            margin-left: 5px;
            padding: 6px 9px;
            border: 1px solid #bbb;
            border-radius: 3px;
            background: white;
            color: #333;
            font-size: 12px;
            text-decoration: none;
            cursor: pointer;
        }

        .event-action:first-child {
            margin-left: 0;
        }

        .event-action:hover {
            background: #f3f3f3;
            color: #222;
        }

        .event-action-danger {
            border-color: #c8a5a5;
            color: #8a3030;
        }

        .event-action-danger:hover {
            background: #faf0f0;
            color: #7a2424;
        }

        .events-empty {
            padding: 60px 30px;
            border: 1px solid #d9d9d9;
            background: white;
            text-align: center;
        }

        .events-empty h2 {
            margin: 0 0 8px;
            font-size: 20px;
            font-weight: 600;
        }

        .events-empty p {
            margin: 0 0 20px;
            color: #666;
            font-size: 14px;
        }

        .events-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-top: 18px;
            color: #666;
            font-size: 13px;
        }

        .events-pagination-links {
            display: flex;
            gap: 6px;
        }

        .events-pagination-links a,
        .events-pagination-links span {
            display: inline-block;
            padding: 6px 9px;
            border: 1px solid #ccc;
            border-radius: 3px;
            background: white;
            color: #333;
            text-decoration: none;
        }

        .events-pagination-links span[aria-current="page"] {
            background: #222;
            border-color: #222;
            color: white;
        }

        @media (max-width: 700px) {
            .events-page {
                margin: 25px auto;
                padding: 0 16px;
            }

            .events-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .create-event-button {
                width: fit-content;
            }

            .events-pagination {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="events-page">

        <div class="events-header">
            <div class="events-heading">
                <h1>Events</h1>
                <p>Manage attendance events and their sessions.</p>
            </div>

            <a
                href="{{ route('events.create') }}"
                class="create-event-button"
            >
                Create Event
            </a>
        </div>

        @if(session('success'))
            <div class="events-message">
                {{ session('success') }}
            </div>
        @endif

        @if($events->count())
            <div class="events-table-wrapper">
                <table class="events-table">
                    <thead>
                    <tr>
                        <th>Event</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Organizer</th>
                        <th>Status</th>
                        <th>Registrations</th>
                        <th class="event-actions">Actions</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($events as $event)
                        <tr>
                            <td class="event-name">
                                <a href="{{ route('events.show', $event) }}">
                                    {{ $event->name }}
                                </a>

                                @if($event->description)
                                    <div class="event-description">
                                        {{ \Illuminate\Support\Str::limit($event->description, 70) }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                {{ $event->eventType?->name ?? '—' }}
                            </td>

                            <td>
                                @if($event->starts_at)
                                    {{ \Carbon\Carbon::parse($event->starts_at)->format('d M Y') }}
                                @else
                                    —
                                @endif
                            </td>

                            <td>
                                {{ $event->location?->name ?? '—' }}
                            </td>

                            <td>
                                {{ $event->organizer?->name ?? '—' }}
                            </td>

                            <td>
                                <span class="event-status {{ $event->status }}">
                                    {{ ucfirst($event->status) }}
                                </span>
                            </td>

                            <td>
                                {{ $event->registrations_count }}
                            </td>

                            <td class="event-actions">
                                <a
                                    href="{{ route('events.show', $event) }}"
                                    class="event-action"
                                >
                                    View
                                </a>

                                <a
                                    href="{{ route('events.edit', $event) }}"
                                    class="event-action"
                                >
                                    Edit
                                </a>

                                <form
                                    action="{{ route('events.destroy', $event) }}"
                                    method="POST"
                                    style="display: inline;"
                                    onsubmit="return confirm('Delete this event? This action cannot be undone.');"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="event-action event-action-danger"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="events-pagination">
                <div>
                    Showing {{ $events->firstItem() }}–{{ $events->lastItem() }}
                    of {{ $events->total() }} events
                </div>

                @if($events->hasPages())
                    <div class="events-pagination-links">
                        @if($events->onFirstPage())
                            <span>Previous</span>
                        @else
                            <a href="{{ $events->previousPageUrl() }}">
                                Previous
                            </a>
                        @endif

                        @if($events->hasMorePages())
                            <a href="{{ $events->nextPageUrl() }}">
                                Next
                            </a>
                        @else
                            <span>Next</span>
                        @endif
                    </div>
                @endif
            </div>

        @else
            <div class="events-empty">
                <h2>No events found</h2>

                <p>
                    There are currently no events available to manage.
                </p>

                <a
                    href="{{ route('events.create') }}"
                    class="create-event-button"
                >
                    Create Event
                </a>
            </div>
        @endif

    </div>
@endsection
