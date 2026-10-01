@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Registrations</h1>

        <p>
            <strong>Event:</strong> {{ $event->name }}
        </p>

        @if(session('success'))
            <div>
                {{ session('success') }}
            </div>
        @endif

        @if($registrations->count())
            <table>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Organisation</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Action</th>
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

                                <select
                                    name="status"
                                    onchange="this.form.submit()"
                                >
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
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{ $registrations->links() }}
        @else
            <p>No registrations have been submitted for this event yet.</p>
        @endif

        <a href="{{ route('events.show', $event) }}">
            Back to Event
        </a>
    </div>
@endsection
