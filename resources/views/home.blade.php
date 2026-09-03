<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpdesk</title>
</head>
<body>

<h1>Helpdesk</h1>

<a href="{{ route('attendance.pin') }}">Attendance</a>

@auth
    @if (auth()->user()->role === 'organizer')
        <a href="{{ route('attendance-events.index') }}">Attendance Sessions</a>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
@else
    <a href="{{ route('login') }}">Login</a>
@endauth

</body>
</html>
