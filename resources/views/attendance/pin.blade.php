<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance</title>
</head>
<body>
<h1>Attendance</h1>

@if ($errors->any())
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ route('attendance.verify') }}">
    @csrf

    <label for="pin">Enter Attendance PIN</label>
    <input
        type="text"
        id="pin"
        name="pin"
        maxlength="4"
        inputmode="numeric"
        required
    >

    <button type="submit">Continue</button>
</form>
</body>
</html>
