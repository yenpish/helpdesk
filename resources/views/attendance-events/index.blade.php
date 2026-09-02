<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Sessions</title>
</head>
<body>
<h1>Attendance Sessions</h1>

<a href="{{ route('attendance-events.create') }}">Create New Session</a>

@foreach ($events as $event)
    <div>
        <h2>{{ $event->name }}</h2>

        <p>Location: {{ $event->location->name }}</p>
        <p>Starts: {{ $event->starts_at?->format('d/m/Y H:i') ?? 'Not set' }}</p>
        <p>Ends: {{ $event->ends_at?->format('d/m/Y H:i') ?? 'Not set' }}</p>
        <p>Attendees: {{ $event->attendances_count }}</p>

        <a href="{{ route('attendance-events.show', $event) }}">
            View Attendance
        </a>
    </div>

    <hr>
@endforeach
</body>
</html>
