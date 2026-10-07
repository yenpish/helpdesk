@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>Edit Event</h1>
                <p>Update the overall event information.</p>
            </div>
        </header>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form
                    id="event-edit-form"
                    method="POST"
                    action="{{ route('events.update', $event) }}"
                    data-original-start="{{ $event->starts_at?->format('Y-m-d\TH:i') }}"
                    data-original-end="{{ $event->ends_at?->format('Y-m-d\TH:i') }}"
                >
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="sync_sessions" id="sync-sessions" value="0">
                    <input type="hidden" name="expected_sessions" value="{{ $event->sessions_count }}">

                    <div class="mb-3">
                        <label for="name" class="form-label">
                            Event Name <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            value="{{ old('name', $event->name) }}"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">
                            Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            rows="4"
                        >{{ old('description', $event->description) }}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="starts_at" class="form-label">
                                Event Starts <span class="text-danger">*</span>
                            </label>

                            <input
                                type="datetime-local"
                                id="starts_at"
                                name="starts_at"
                                class="form-control"
                                value="{{ old('starts_at', $event->starts_at?->format('Y-m-d\TH:i')) }}"
                                required
                            >
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="ends_at" class="form-label">
                                Event Ends <span class="text-danger">*</span>
                            </label>

                            <input
                                type="datetime-local"
                                id="ends_at"
                                name="ends_at"
                                class="form-control"
                                value="{{ old('ends_at', $event->ends_at?->format('Y-m-d\TH:i')) }}"
                                required
                            >
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="event_type_id" class="form-label">
                            Event Type
                        </label>

                        <select
                            id="event_type_id"
                            name="event_type_id"
                            class="form-select"
                        >
                            <option value="">Select event type</option>

                            @foreach($eventTypes as $eventType)
                                <option
                                    value="{{ $eventType->id }}"
                                    @selected(old('event_type_id', $event->event_type_id) == $eventType->id)
                                >
                                    {{ $eventType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="location_id" class="form-label">
                            Location
                        </label>

                        <select
                            id="location_id"
                            name="location_id"
                            class="form-select"
                        >
                            <option value="">Select location</option>

                            @foreach($locations as $location)
                                <option
                                    value="{{ $location->id }}"
                                    @selected(old('location_id', $event->location_id) == $location->id)
                                >
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">
                            Status <span class="text-danger">*</span>
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-select"
                            required
                        >
                            <option value="draft" @selected(old('status', $event->status) === 'draft')>
                                Draft
                            </option>

                            <option value="published" @selected(old('status', $event->status) === 'published')>
                                Published
                            </option>

                            <option value="cancelled" @selected(old('status', $event->status) === 'cancelled')>
                                Cancelled
                            </option>

                            <option value="completed" @selected(old('status', $event->status) === 'completed')>
                                Completed
                            </option>
                        </select>
                    </div>

                    <div class="alert alert-light border">
                        <strong>Session schedule</strong>
                        <div class="text-muted mt-1">
                            Changing event dates moves existing sessions with them. Added dates get sessions; removed dates can be removed only when their sessions have no attendance.
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            Save Changes
                        </button>

                        <a
                            href="{{ route('events.show', $event) }}"
                            class="btn btn-secondary"
                        >
                            Cancel
                        </a>
                    </div>
                </form>
                <dialog id="reschedule-confirmation" aria-labelledby="reschedule-title">
                    <h2 id="reschedule-title">Reschedule sessions?</h2>
                    <p>Changing the event dates will also move its existing sessions.<br>Attendance records will stay with their sessions.</p>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" id="cancel-reschedule">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirm-reschedule">Reschedule Event</button>
                    </div>
                </dialog>
            </div>
        </div>
    </div>

    <style>
        #reschedule-confirmation {
            width: min(460px, calc(100% - 32px));
            padding: 24px;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
            background: var(--surface);
        }

        #reschedule-confirmation::backdrop {
            background: rgb(15 23 42 / 45%);
        }

        #reschedule-confirmation h2 {
            margin: 0 0 12px;
            font-size: 20px;
        }

        #reschedule-confirmation p {
            margin-bottom: 20px;
        }
    </style>

    <script>
        const eventEditForm = document.getElementById('event-edit-form');

        if (eventEditForm) {
            eventEditForm.addEventListener('submit', (event) => {
                const startsAt = document.getElementById('starts_at').value;
                const endsAt = document.getElementById('ends_at').value;
                const datesChanged = startsAt !== eventEditForm.dataset.originalStart
                    || endsAt !== eventEditForm.dataset.originalEnd;

                if (!datesChanged) return;
                if (document.getElementById('sync-sessions').value === '1') return;

                event.preventDefault();
                document.getElementById('reschedule-confirmation').showModal();
            });

            document.getElementById('cancel-reschedule').addEventListener('click', () => {
                document.getElementById('reschedule-confirmation').close();
            });

            document.getElementById('confirm-reschedule').addEventListener('click', () => {
                document.getElementById('sync-sessions').value = '1';
                document.getElementById('reschedule-confirmation').close();
                eventEditForm.requestSubmit();
            });
        }
    </script>
@endsection
