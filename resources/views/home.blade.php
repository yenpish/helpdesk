@extends('layouts.app')

@section('title', 'Helpdesk')

@section('content')

    <h1>Helpdesk</h1>

    <a class="page-link" href="{{ route('attendance.pin') }}">
        Attendance
    </a>

    @auth

        @if (auth()->user()->role === 'organizer')

            <a class="page-link" href="{{ route('attendance-events.index') }}">
                Attendance Sessions
            </a>

        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit">
                Logout
            </button>
        </form>

    @else

        <a class="page-link" href="{{ route('login') }}">
            Login
        </a>

    @endauth

@endsection
