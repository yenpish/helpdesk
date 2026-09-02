<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->name }}</title>
</head>
<body>
<h1>{{ $event->name }}</h1>

<p>PIN: {{ $event->pin }}</p>
<p>Location: {{ $event->location->name }}</p>
<p>Starts: {{ $event->starts_at?->format('d/m/Y H:i') ?? 'Not set' }}</p>
<p>Ends: {{ $event->ends_at?->format('d/m/Y H:i') ?? 'Not set' }}</p>

<h2>Attendance Records</h2>

<p>Total Attendees: {{ $event->attendances->count() }}</p>

@foreach ($event->attendances as $attendance)
    <div>
        <p>Name: {{ $attendance->full_name }}</p>
        <p>Position: {{ $attendance->position }}</p>
        <p>Unit: {{ $attendance->unit }}</p>
        <p>Phone: {{ $attendance->phone }}</p>
        <p>Email: {{ $attendance->email }}</p>
        <p>Submitted: {{ $attendance->created_at->format('d/m/Y H:i:s') }}</p>
    </div>

    <hr>
@endforeach
</body>
</html>
