@extends('layouts.app')

@section('content')
    <h1>User Details</h1>

    <p>
        ID: {{$user->id}}<br>
        Name: {{$user->name}}<br>
        Email: {{$user->email}}<br>
        Role: {{$user->role}}<br>
        @if ($user->role === 'organizer')
            Access: Can manage organizer features
        @else
            Access: Standard user access
        @endif
    </p>

@endsection
