<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Form</title>
</head>
<body>
<h1>{{ $event->name }}</h1>

<p>Location: {{ $event->location->name }}</p>
<p>Starts: {{ $event->starts_at->format('d/m/Y H:i') }}</p>
<p>Ends: {{ $event->ends_at->format('d/m/Y H:i') }}</p>

<h2>Attendance Details</h2>

<form method="POST" action="#">
    @csrf

    <div>
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" required>
    </div>

    <div>
        <label for="position">Position</label>
        <input type="text" id="position" name="position">
    </div>

    <div>
        <label for="unit">Unit / Organization</label>
        <input type="text" id="unit" name="unit">
    </div>

    <div>
        <label for="phone">Phone</label>
        <input type="text" id="phone" name="phone">
    </div>

    <div>
        <label for="email">Email</label>
        <input type="email" id="email" name="email">
    </div>

    <div>
        <label for="signature">Signature</label>
        <textarea id="signature" name="signature"></textarea>
    </div>

    <button type="submit">Submit Attendance</button>
</form>
</body>
</html>
