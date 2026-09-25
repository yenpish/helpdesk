@extends('layouts.app')

@section('title', 'Organizer Dashboard')

@section('container_class', 'wide-box')

@section('content')

    <div class="session-page-header">
        <div>
            <h1>Attendance Sessions</h1>
            <p class="subtitle">
                Manage attendance sessions and view attendee records.
            </p>
        </div>

        <a class="session-primary-action" href="{{ route('attendance-events.create') }}">
            Create New Session
        </a>
    </div>

    <div class="stats">

        <div class="stat">
            <div class="stat-label">Total Sessions</div>
            <div class="stat-value">{{ $totalEvents }}</div>
        </div>

        <div class="stat">
            <div class="stat-label">Total Attendees</div>
            <div class="stat-value">{{ $totalAttendees }}</div>
        </div>

        <div class="stat">
            <div class="stat-label">Active Sessions</div>
            <div class="stat-value">{{ $activeEvents }}</div>
        </div>

    </div>

    <form class="session-search" method="GET" action="{{ route('attendance-events.index') }}">

        <input
            type="text"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search sessions..."
        >

        <select name="sort" onchange="this.form.submit()">
            <option value="start_desc" {{ $sort === 'start_desc' ? 'selected' : '' }}>
                Start Date — Newest
            </option>

            <option value="start_asc" {{ $sort === 'start_asc' ? 'selected' : '' }}>
                Start Date — Oldest
            </option>

            <option value="end_desc" {{ $sort === 'end_desc' ? 'selected' : '' }}>
                End Date — Newest
            </option>

            <option value="end_asc" {{ $sort === 'end_asc' ? 'selected' : '' }}>
                End Date — Oldest
            </option>

            <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>
                Name — A to Z
            </option>

            <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>
                Name — Z to A
            </option>
        </select>

        <button type="submit">
            Search
        </button>

    </form>

    <form id="bulk-export-form" method="POST" action="{{ route('attendance-events.export-selected') }}">
        @csrf
    </form>

    <div class="bulk-actions">
        <button type="button" id="select-all-sessions">
            Select All
        </button>

        <button type="submit" form="bulk-export-form">
            Export Selected
        </button>
    </div>

    <div class="table-container">

        <table>

            <thead>
            <tr>
                <th>Event</th>
                <th>Location</th>
                <th>Start</th>
                <th>End</th>
                <th>Attendees</th>
                <th></th>
            </tr>
            </thead>

            <tbody>

            @forelse ($events as $event)

                @php
                    $status = 'upcoming';

                    if ($event->starts_at && $event->ends_at) {
                        if (now()->between($event->starts_at, $event->ends_at)) {
                            $status = 'active';
                        } elseif (now()->gt($event->ends_at)) {
                            $status = 'ended';
                        }
                    }
                @endphp

                <tr>

                    <td>
                        <input
                            type="checkbox"
                            name="event_ids[]"
                            value="{{ $event->id }}"
                            form="bulk-export-form"
                            class="session-checkbox"
                        >

                        <span class="status-dot status-{{ $status }}"></span>
                        <strong>{{ $event->name }}</strong>
                    </td>

                    <td>
                        {{ $event->location->name }}
                    </td>

                    <td>
                        {{ $event->starts_at?->format('d/m/Y H:i') ?? 'Not set' }}
                    </td>

                    <td>
                        {{ $event->ends_at?->format('d/m/Y H:i') ?? 'Not set' }}
                    </td>

                    <td>
                        {{ $event->attendances_count }}
                    </td>

                    <td class="session-actions-cell">

                        <details class="session-actions">
                            <summary>Actions</summary>

                            <div class="session-actions-menu">

                                <a href="{{ route('attendance-events.show', $event) }}">
                                    View Attendance
                                </a>

                                <a href="{{ route('attendance-events.edit', $event) }}">
                                    Edit Session
                                </a>

                                <a href="{{ route('attendance-events.export', $event) }}">
                                    Export CSV
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('attendance-events.destroy', $event) }}"
                                    onsubmit="return confirm('Are you sure you want to delete this attendance session?');"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit">
                                        Delete Session
                                    </button>
                                </form>

                            </div>
                        </details>

                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="6">
                        No attendance sessions found.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

@endsection

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAllButton = document.getElementById('select-all-sessions');
        const checkboxes = document.querySelectorAll('.session-checkbox');

        selectAllButton.addEventListener('click', function () {
            const shouldSelectAll = Array.from(checkboxes)
                .some(checkbox => !checkbox.checked);

            checkboxes.forEach(function (checkbox) {
                checkbox.checked = shouldSelectAll;
            });

            this.textContent = shouldSelectAll
                ? 'Clear Selection'
                : 'Select All';
        });
    });
</script>
