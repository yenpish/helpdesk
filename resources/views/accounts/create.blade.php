@extends('layouts.app')

@section('title', 'Create Account')
@section('section', 'Administration')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading"><h1>Create account</h1><p>Add a system account and assign its access role.</p></div>
            <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Back to accounts</a>
        </header>
        <div class="content-panel">
            @if($errors->any())
                <div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            @endif
            <form method="POST" action="{{ route('accounts.store') }}" class="account-form">
                @csrf
                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="">Select role</option>
                        <option value="admin" @selected(old('role') === 'admin')>System Admin</option>
                        <option value="organizer" @selected(old('role') === 'organizer')>Organizer</option>
                    </select>
                    @error('role')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="password">Temporary password</label>
                    <input id="password" type="password" name="password" autocomplete="new-password" required>
                    @error('password')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create account</button>
                    <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
