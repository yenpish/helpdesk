@extends('layouts.app')

@section('title', 'Profile - Helpdesk')

@section('content')
    <h1>Profile</h1>

    <p class="subtitle">
        Your account information
    </p>

    <div class="field">
        <label for="profile-name">Name</label>

        <input
            id="profile-name"
            type="text"
            value="{{ auth()->user()->name }}"
            readonly
        >
    </div>

    <div class="field">
        <label for="profile-email">Email</label>

        <input
            id="profile-email"
            type="email"
            value="{{ auth()->user()->email }}"
            readonly
        >
    </div>

    <div class="field">
        <label for="profile-role">Role</label>

        <input
            id="profile-role"
            type="text"
            value="{{ ucfirst(auth()->user()->role ?? 'User') }}"
            readonly
        >
    </div>
@endsection