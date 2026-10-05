@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <div class="container">
        <div class="page-header">
            <div class="page-heading">
                <h1>Pre-registrations</h1>
                <p>Review pre-registration requests for <strong>{{ $event->name }}</strong>.</p>
            </div>
            <a class="btn btn-secondary" href="{{ route('events.show', $event) }}">Back to event</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if($registrations->count())
            <div class="table-responsive">
            <table class="registrations-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Organisation</th>
                    <th>Position</th>
                    <th>Request status</th>
                    <th>Submitted</th>
                    <th>Update status</th>
                </tr>
                </thead>

                <tbody>
                @foreach($registrations as $registration)
                    <tr>
                        <td>{{ $registration->guest_name }}</td>
                        <td>{{ $registration->guest_email }}</td>
                        <td>{{ $registration->guest_phone ?? '-' }}</td>
                        <td>{{ $registration->organisation ?? '-' }}</td>
                        <td>{{ $registration->position ?? '-' }}</td>

                        <td>
                            {{ ucfirst($registration->status) }}
                        </td>

                        <td>
                            {{ $registration->registered_at?->format('d M Y, h:i A') }}
                        </td>

                        <td>
                            <form
                                method="POST"
                                action="{{ route(
                                    'events.registrations.status',
                                    [$event, $registration]
                                ) }}"
                            >
                                @csrf
                                @method('PATCH')

                                <div class="status-control">
                                <select name="status" aria-label="Registration status for {{ $registration->guest_name }}">
                                    <option
                                        value="pending"
                                        @selected($registration->status === 'pending')
                                    >
                                        Pending
                                    </option>

                                    <option
                                        value="approved"
                                        @selected($registration->status === 'approved')
                                    >
                                        Approved
                                    </option>

                                    <option
                                        value="rejected"
                                        @selected($registration->status === 'rejected')
                                    >
                                        Rejected
                                    </option>

                                    <option
                                        value="cancelled"
                                        @selected($registration->status === 'cancelled')
                                    >
                                        Cancelled
                                    </option>
                                </select>
                                <button class="btn btn-secondary btn-sm" type="submit">Save</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>

        <div class="pagination-wrap">{{ $registrations->links() }}</div>
        @else
            <p>No registrations have been submitted for this event yet.</p>
        @endif

    </div>
@endsection
