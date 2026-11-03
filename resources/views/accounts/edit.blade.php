@extends('layouts.app')

@section('title', 'Edit Account')
@section('section', 'Administration')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading"><h1>Edit account</h1><p>Update {{ $user->name }}'s account details and role.</p></div>
            <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Back to accounts</a>
        </header>
        <div class="content-panel">
            @if(!$user->roleDefinition)
                <div class="alert alert-light" role="status">
                    This account has a role from the previous system. Assign a current Attendance role before saving.
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            @endif
            <form method="POST" action="{{ route('accounts.update', $user) }}" class="account-form">
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required>
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" required>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->slug }}" @selected(old('role', $user->role) === $role->slug)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="password">New password <span class="text-muted">(optional)</span></label>
                    <input id="password" type="password" name="password" autocomplete="new-password">
                    <small>Leave blank to keep the current password.</small>
                    @error('password')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                @if($canManageInternalRemark)
                    <div class="field">
                        <label for="internal_remark">Internal Remark</label>
                        <textarea id="internal_remark" name="internal_remark" rows="3" maxlength="2000">{{ old('internal_remark', $user->internal_remark) }}</textarea>
                        @error('internal_remark')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('accounts.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
