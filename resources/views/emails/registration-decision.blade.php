<p>Hello {{ $registration->guest_name ?: $registration->user?->name ?: 'there' }},</p>

@if($approved)
    <p>Your registration for <strong>{{ $event->name }}</strong> has been approved. We look forward to seeing you!</p>
    <p>{{ $event->starts_at?->format('d M Y, h:i A') ?? 'Event time to be announced' }} · {{ $event->location?->name ?? 'Location to be announced' }}</p>
@else
    <p>Your pre-registration request for <strong>{{ $event->name }}</strong> was not approved.</p>
    <p>If pre-registration is still open, you can correct your details and submit again from the <a href="{{ route('registrations.create', $event) }}">event pre-registration form</a>.</p>
@endif

<p>{{ config('app.name') }}</p>
