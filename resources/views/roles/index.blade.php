@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('section', 'Administration')

@section('content')
    <div class="container roles-page">
        <header class="page-header">
            <div class="page-heading">
                <h1>Roles &amp; Permissions</h1>
                <p>Create roles for account access and choose what each role can do.</p>
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <section class="content-panel role-create-panel mb-4" aria-labelledby="create-role-heading">
            <h2 id="create-role-heading">Create role</h2>
            <form method="POST" action="{{ route('roles.store') }}" class="account-form">
                @csrf
                <div class="field">
                    <label for="new-role-name">Role name</label>
                    <input id="new-role-name" name="name" value="{{ old('name') }}" maxlength="80" required>
                </div>
                <fieldset class="field role-permissions role-permissions-create">
                    <legend>Permissions</legend>
                    @foreach($permissions as $permission => $label)
                        <label class="role-permission-option">
                            <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', []), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </fieldset>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create role</button>
                </div>
            </form>
        </section>

        <section aria-labelledby="existing-roles-heading">
            <h2 id="existing-roles-heading">Existing roles</h2>
            @foreach($roles as $role)
                <article class="content-panel role-card mb-3">
                    <div class="role-card-header">
                        <h3 class="h5 mb-0">{{ $role->name }}</h3>
                        <p class="text-muted mb-0">
                            {{ $role->is_system ? 'System role' : 'Custom role' }} · {{ $role->users_count }} assigned account(s)
                        </p>
                    </div>

                    @if($role->is_system)
                        <p class="role-system-note mb-0">Built-in access is protected to preserve existing system capabilities.</p>
                    @else
                        <form id="role-update-{{ $role->id }}" method="POST" action="{{ route('roles.update', $role) }}" class="account-form role-edit-form">
                            @csrf
                            @method('PUT')
                            <div class="field">
                                <label for="role-name-{{ $role->id }}">Role name</label>
                                <input id="role-name-{{ $role->id }}" name="name" value="{{ $role->name }}" maxlength="80" required>
                            </div>
                            <fieldset class="field role-permissions role-permissions-edit">
                                <legend>Permissions</legend>
                                @foreach($permissions as $permission => $label)
                                    <label class="role-permission-option">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $role->permissions ?? [], true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </fieldset>
                        </form>
                        <div class="role-card-actions">
                            <button type="submit" form="role-update-{{ $role->id }}" class="btn btn-primary">Save role</button>
                            <form method="POST" action="{{ route('roles.destroy', $role) }}" class="inline-form" onsubmit="return confirm('Delete this role?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" @disabled($role->users_count > 0)>Delete role</button>
                            </form>
                        </div>
                        @if($role->users_count > 0)
                            <small class="text-muted d-block mt-1">Reassign its accounts before deleting this role.</small>
                        @endif
                    @endif
                </article>
            @endforeach
        </section>
    </div>
@endsection
