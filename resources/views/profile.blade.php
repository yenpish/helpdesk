@extends('layouts.app')
@section('section', 'Your account')

@section('title', 'Profile - Attendance Management')

@section('content')
    <div class="content-panel profile-panel">
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
            value="{{ match(auth()->user()->role) { 'admin' => 'System Admin', 'organizer' => 'Organizer', 'user' => 'User', default => 'Legacy account' } }}"
            readonly
        >
    </div>
    </div>
@endsection
