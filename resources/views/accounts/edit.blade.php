@extends('layouts.app')

@section('title', 'Edit Account')

@section('content')
    <div class="app-box" style="max-width:700px;">

        <h1>Edit Account</h1>

        <p style="color:#666;">
            Update this system account's details and role.
        </p>

        @if($errors->any())
            <div style="padding:12px; margin-bottom:20px; background:#ffebee; color:#c62828;">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('accounts.update', $user) }}">
            @csrf
            @method('PUT')

            <div style="margin-bottom:16px;">
                <label>Name</label>

                <input type="text"
                       name="name"
                       value="{{ old('name', $user->name) }}"
                       required
                       style="width:100%; padding:10px;">
            </div>

            <div style="margin-bottom:16px;">
                <label>Email</label>

                <input type="email"
                       name="email"
                       value="{{ old('email', $user->email) }}"
                       required
                       style="width:100%; padding:10px;">
            </div>

            <div style="margin-bottom:16px;">
                <label>Role</label>

                <select name="role"
                        required
                        style="width:100%; padding:10px;">

                    <option value="admin"
                        @selected(old('role', $user->role) === 'admin')>
                        System Admin
                    </option>

                    <option value="organizer"
                        @selected(old('role', $user->role) === 'organizer')>
                        Organizer
                    </option>

                    <option value="user"
                        @selected(old('role', $user->role) === 'user')>
                        User
                    </option>

                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label>New Password</label>

                <input type="password"
                       name="password"
                       style="width:100%; padding:10px;">

                <small style="color:#666;">
                    Leave blank to keep the current password.
                </small>
            </div>

            <button type="submit"
                    style="padding:10px 16px; background:#222; color:white; border:0; border-radius:4px;">
                Save Changes
            </button>

            <a href="{{ route('accounts.index') }}"
               style="margin-left:10px;">
                Cancel
            </a>

        </form>

    </div>
@endsection
