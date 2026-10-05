@extends('layouts.app')
@section('section', 'Sign in')

@section('title', 'Sign in - Attendance Management')

@section('container_class', 'login-box')

@section('content')
    <div class="content-panel login-panel">
    <h1>Attendance Management</h1>

    <p class="subtitle">
        Sign in to access your account
    </p>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field">
            <label for="email">Email</label>

            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="Enter your email"
                autocomplete="email"
                required
                autofocus
            >
        </div>

        <div class="field">
            <label for="password">Password</label>

            <input
                id="password"
                type="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit">
            Sign In
        </button>
    </form>
    </div>
@endsection
