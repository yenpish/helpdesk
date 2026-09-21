@extends('layouts.app')

@section('title', 'Login - Helpdesk')

@section('container_class', 'login-box')

@section('content')
    <h1>Helpdesk</h1>

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
@endsection
