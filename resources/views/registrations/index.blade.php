@extends('layouts.app')
@section('section', 'Event management')

@section('content')
    <style>
        .registration-management-page .registration-edit-dialog {
            width: min(600px, calc(100vw - 32px));
            max-width: 600px;
            max-height: calc(100vh - 32px);
            padding: 0;
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            color: var(--text);
        }
        .registration-management-page .registration-edit-dialog::backdrop { background: rgb(15 23 42 / 55%); }
        .registration-edit-dialog-header,
        .registration-edit-dialog-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
        }
        .registration-edit-dialog-header { background: var(--surface-raised); border-bottom: 1px solid var(--line); }
        .registration-edit-dialog-header h2 { margin: 0; line-height: 1.2; }
        .registration-edit-dialog-body { padding: 16px 18px; }
        .registration-edit-dialog-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; }
        .registration-edit-dialog-fields label { margin-bottom: 5px; }
        .registration-edit-dialog-fields .form-control { min-width: 0; }
        .registration-edit-dialog-footer { justify-content: flex-end; border-top: 1px solid var(--line); }
        .registration-name-edit { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; min-width: 0; }
        .registration-name-edit > span { min-width: 0; flex: 0 1 auto; overflow-wrap: anywhere; }
        .registration-submitted span { display: block; white-space: nowrap; }
        @media (max-width: 600px) { .registration-edit-dialog-fields { grid-template-columns: 1fr; } }
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
                    <col style="width: 11%"><col style="width: 15%"><col style="width: 9%"><col style="width: 12%">
                    <col style="width: 9%"><col style="width: 11%"><col style="width: 10%"><col style="width: 23%">
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
                </tr>
                </thead>

                <tbody>
                @foreach($registrations as $registration)
                    <tr>
                        <td>
                            <div class="registration-name-edit">
                                <span>{{ $registration->guest_name }}</span>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-edit-registration
                                    data-edit-action="{{ route('events.registrations.details', [$event, $registration]) }}"
                                    data-registration-id="{{ $registration->id }}"
                                    data-guest-name="{{ $registration->guest_name }}"
                                    data-guest-email="{{ $registration->guest_email }}"
                                    data-guest-phone="{{ $registration->guest_phone }}"
                                    data-organisation="{{ $registration->organisation }}"
                                    data-position="{{ $registration->position }}"
                                    aria-label="Edit pre-registration details for {{ $registration->guest_name }}"
                                >Edit</button>
                            </div>
                        </td>
                        <td>{{ $registration->guest_email }}</td>
                        <td class="table-cell-nowrap">{{ $registration->guest_phone ?? '-' }}</td>
                        <td>{{ $registration->organisation ?? '-' }}</td>
                        <td>{{ $registration->position ?? '-' }}</td>

                        <td>
                            {{ ucfirst($registration->status) }}
                            @if($registration->decision_email_for === $registration->status && in_array($registration->status, ['approved', 'rejected'], true))
                                <small class="d-block text-muted">
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
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>

        @include('components.pagination-row', ['paginator' => $registrations, 'ariaLabel' => 'Pre-registration pages'])
        @else
            <p>No pre-registrations have been submitted for this event yet.</p>
        @endif

        <dialog
            id="registration-edit-dialog"
            class="registration-edit-dialog"
            aria-labelledby="registration-edit-title"
            @if($errors->any() && old('editing_registration_id')) open @endif
        >
            <form
                id="registration-edit-form"
                method="POST"
                action="{{ old('editing_registration_id') ? route('events.registrations.details', [$event, old('editing_registration_id')]) : '' }}"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="editing_registration_id" value="{{ old('editing_registration_id') }}">
                <div class="registration-edit-dialog-header">
                    <h2 id="registration-edit-title" class="h5">Edit pre-registration</h2>
                    <button type="button" class="btn btn-secondary btn-sm" data-close-registration-edit>Cancel</button>
                </div>
                <div class="registration-edit-dialog-body">
                    <div class="registration-edit-dialog-fields">
                        <div>
                            <label for="registration-edit-name">Full name</label>
                            <input id="registration-edit-name" name="guest_name" class="form-control" required maxlength="255" value="{{ old('guest_name') }}">
                            @error('guest_name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div>
                            <label for="registration-edit-email">Email</label>
                            <input id="registration-edit-email" type="email" name="guest_email" class="form-control" required maxlength="255" value="{{ old('guest_email') }}">
                            @error('guest_email')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div>
                            <label for="registration-edit-phone">Phone</label>
                            <input id="registration-edit-phone" type="tel" name="guest_phone" class="form-control" required maxlength="50" value="{{ old('guest_phone') }}">
                            @error('guest_phone')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div>
                            <label for="registration-edit-position">Position</label>
                            <input id="registration-edit-position" name="position" class="form-control" maxlength="255" value="{{ old('position') }}">
                            @error('position')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div>
                            <label for="registration-edit-organisation">Organisation</label>
                            <input id="registration-edit-organisation" name="organisation" class="form-control" maxlength="255" value="{{ old('organisation') }}">
                            @error('organisation')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </div>
                <div class="registration-edit-dialog-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </dialog>

    </div>

    <script>
        (() => {
            const dialog = document.getElementById('registration-edit-dialog');
            const form = document.getElementById('registration-edit-form');
            document.addEventListener('click', event => {
                const button = event.target.closest('[data-edit-registration]');
                if (!button || !dialog || !form) return;

                form.action = button.dataset.editAction;
                form.elements.namedItem('editing_registration_id').value = button.dataset.registrationId;
                form.elements.namedItem('guest_name').value = button.dataset.guestName || '';
                form.elements.namedItem('guest_email').value = button.dataset.guestEmail || '';
                form.elements.namedItem('guest_phone').value = button.dataset.guestPhone || '';
                form.elements.namedItem('position').value = button.dataset.position || '';
                form.elements.namedItem('organisation').value = button.dataset.organisation || '';
                dialog.showModal();
            });
            document.querySelector('[data-close-registration-edit]')?.addEventListener('click', () => dialog?.close());
            if (dialog?.hasAttribute('open')) {
                dialog.removeAttribute('open');
                dialog.showModal();
            }
        })();
    </script>
@endsection
