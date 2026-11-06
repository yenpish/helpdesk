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
            <a href="{{ route('roles.create') }}" class="btn btn-primary">Create role</a>
        </header>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

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
                        @if($role->slug === 'admin')
                            <p class="role-system-note mb-0">System Admin permissions and identity are protected.</p>
                        @else
                            <div class="role-card-actions">
                                <a href="{{ route('roles.edit', $role) }}" class="btn btn-secondary">Edit permissions</a>
                            </div>
                        @endif
                    @else
                        <div class="role-card-actions">
                            <a href="{{ route('roles.edit', $role) }}" class="btn btn-secondary">Edit</a>
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
