<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Attendance Session</title>
</head>
<body>
<h1>Create Attendance Session</h1>

@if (session('success'))
    <p>{{ session('success') }}</p>
@endif

@if ($errors->any())
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ route('attendance-events.store') }}">
    @csrf

    <div>
        <label for="name">Workshop / Event</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" required>
    </div>

    <div>
        <label for="location_id">Location</label>
        <select id="location_id" name="location_id" required>
            <option value="">Select a location</option>

            @foreach ($locations as $location)
                <option value="{{ $location->id }}">
                    {{ $location->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="start_date">Start Date</label>
        <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
    </div>

    <div>
        <label for="end_date">End Date</label>
        <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" required>
    </div>

    <div>
        <label for="start_time">Start Time</label>
        <input type="time" id="start_time" name="start_time" value="{{ old('start_time') }}" required>
    </div>

    <div>
        <label for="end_time">End Time</label>
        <input type="time" id="end_time" name="end_time" value="{{ old('end_time') }}" required>
    </div>

    <button type="submit">Create Session</button>
</form>
</body>
</html>
