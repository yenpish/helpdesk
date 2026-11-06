@extends('layouts.app')

@section('title', 'Edit Role')
@section('section', 'Administration')

@section('content')
    @php($previousPermissions = old('permissions', $role->permissions ?? []))
    @php($selectedPermissions = is_array($previousPermissions) ? $previousPermissions : [])
    <div class="container roles-page">
        <header class="page-header">
            <div class="page-heading">
                <h1>{{ $role->is_system ? 'Edit permissions' : 'Edit role' }}</h1>
                <p>{{ $role->name }} · {{ $role->users_count }} assigned account(s)</p>
            </div>
            <a href="{{ route('roles.index') }}" class="btn btn-secondary">Back to roles</a>
        </header>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <div class="content-panel">
            <form method="POST" action="{{ route('roles.update', $role) }}" class="account-form">
                @csrf
                @method('PUT')
                @unless($role->is_system)
                    <div class="field">
                        <label for="role-name">Role name</label>
                        <input id="role-name" name="name" value="{{ old('name', $role->name) }}" maxlength="80" required>
                        @error('name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                @endunless
                <fieldset class="field role-permissions role-permissions-edit">
                    <legend>Permissions</legend>
                    @foreach($permissions as $permission => $label)
                        <label class="role-permission-option">
                            <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissions, true))>
                            {{ $label }}
                        </label>
                    @endforeach
                    @error('permissions')<div class="field-error">{{ $message }}</div>@enderror
                </fieldset>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('roles.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
