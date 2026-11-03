<p>Hello,</p>

@if ($type === 'published')
    <p>{{ $event->name }} has been published.</p>
@elseif ($type === 'cancelled')
    <p>{{ $event->name }} has been cancelled.</p>
@elseif (str_starts_with($type, 'reminder_'))
    <p>This is a reminder for {{ $event->name }}.</p>
@else
    <p>Details for {{ $event->name }} have changed.</p>
@endif

<p>
    Schedule: {{ $event->starts_at?->format('d M Y, h:i A') ?? 'To be announced' }}
    — {{ $event->ends_at?->format('d M Y, h:i A') ?? 'To be announced' }}
</p>

<p>{{ config('app.name') }}</p>
