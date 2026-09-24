@extends('layouts.app')

@section('content')
    <h1>Users</h1>

    <h2>
        Total Users: {{ $totalCount }}<br>
        @if ($search !== null && $resultCount === 0)
            Search Results: {{ $resultCount}}<br>
        @endif
        Organizers' Count: {{ $organizerCount }}
    </h2>

    <form method="get" action="{{route('users.index')}}">
        <input
            type="text"
            name="search"
            value="{{ $search }}"
            placeholder="Search users ..."
        >
    </form>

    @foreach ($users as $user)
        <p>
            {{ $user->id }}
            - <a href="{{route('users.show', $user)}}">{{$user->name}}</a>
            - {{ $user->email }}
            -
            @if($user->role === 'organizer')
                Organizer
            @else
                User
            @endif
        </p>
    @endforeach
@endsection
