@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <style>
        .registration-submitted span { display: block; white-space: nowrap; }
        .registration-decision-status { display: block; }
        .registration-email-status { display: block; margin-top: 3px; color: var(--muted); font-size: 12px; }
    </style>

    <div class="container registration-management-page">
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

        <form method="GET" action="{{ route('events.registrations.index', $event) }}" class="events-filter-form registration-management-filters">
            <div class="events-search-field">
                <label for="registration-search" class="form-label">Search pre-registrations</label>
                <input id="registration-search" type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Name, email, phone, organisation, position...">
            </div>
            <div class="events-sort-field">
                <label for="registration-status" class="form-label">Approval status</label>
                <select id="registration-status" name="status" class="form-select">
                    <option value="" @selected($status === '')>All statuses</option>
                    <optgroup label="Approval">
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="approved" @selected($status === 'approved')>Approved</option>
                        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                        <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                    </optgroup>
                </select>
            </div>
            <div class="events-filter-action"><button type="submit" class="btn btn-secondary">Apply</button></div>
            @if($search !== '' || $status !== '')
                <div class="events-filter-action"><a href="{{ route('events.registrations.index', $event) }}" class="btn btn-link">Clear</a></div>
            @endif
        </form>

        @if($registrations->count())
            <div class="table-responsive">
            <table class="registrations-table">
                <colgroup>
                    <col style="width: 13%"><col style="width: 15%"><col style="width: 9%"><col style="width: 12%">
                    <col style="width: 7%"><col style="width: 12%"><col style="width: 9%"><col style="width: 15%"><col style="width: 8%">
                </colgroup>
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Organisation</th>
                    <th>Position</th>
                    <th>Approval status</th>
                    <th>Submitted</th>
                    <th>Update status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                @foreach($registrations as $registration)
                    <tr>
                        <td>
                            {{ $registration->guest_name }}
                        </td>
                        <td>{{ $registration->guest_email }}</td>
                        <td class="table-cell-nowrap">{{ $registration->guest_phone ?? '-' }}</td>
                        <td>{{ $registration->organisation ?? '-' }}</td>
                        <td>{{ $registration->position ?? '-' }}</td>

                        <td>
                            <span class="registration-decision-status">{{ ucfirst($registration->status) }}</span>
                            @if($registration->decision_email_for === $registration->status && in_array($registration->status, ['approved', 'rejected'], true))
                                <small class="registration-email-status">
                                    Email {{ $registration->decision_email_status === 'accepted' ? 'sent' : ($registration->decision_email_status === 'failed' ? 'failed' : 'pending') }}
                                </small>
                            @endif
                        </td>

                        <td class="registration-submitted">
                            <span>{{ $registration->registered_at?->format('d M Y') }}</span>
                            <span>{{ $registration->registered_at?->format('h:i A') }}</span>
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
                                <select name="status" aria-label="Approval status for {{ $registration->guest_name }}">
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
                                @if($registration->decision_email_status === 'failed' && $registration->decision_email_for === $registration->status)
                                    <button class="btn btn-secondary btn-sm" type="submit" name="retry_decision_email" value="1">Retry email</button>
                                @endif
                                </div>
                            </form>
                        </td>
                        <td class="table-actions">
                            <a
                                class="btn btn-secondary btn-sm"
                                href="{{ route('events.registrations.edit', [$event, $registration, 'search' => $search, 'status' => $status, 'page' => $registrations->currentPage()]) }}"
                                aria-label="Edit pre-registration details for {{ $registration->guest_name }}"
                            >Edit</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>

        @include('components.pagination-row', ['paginator' => $registrations, 'ariaLabel' => 'Pre-registration pages'])
        @else
            <p>No pre-registrations have been submitted for this event yet.</p>
        @endif

    </div>
@endsection
