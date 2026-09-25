@extends('layouts.app')

@section('title', 'Account Management - Helpdesk')
@section('container_class', 'wide-box')

@section('content')
<style>
    .account-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }
    .account-create {
        padding-bottom: 28px;
        border-bottom: 1px solid #ddd;
    }
    .account-edit-link {
        display: inline-block;
        padding: 8px 14px;
        background: #222;
        color: white;
        text-decoration: none;
        border-radius: 3px;
    }
    .account-dialog {
        width: 540px;
        max-width: calc(100vw - 32px);
        max-height: calc(100dvh - 32px);
        box-sizing: border-box;
        padding: 24px;
        border: 1px solid #ccc;
        border-radius: 6px;
        overflow-y: auto;
        color: #222;
    }
    .account-dialog::backdrop {
        background: rgba(0, 0, 0, 0.45);
    }
    .account-dialog h2 {
        margin: 0 0 24px;
    }
    .dialog-actions {
        display: flex;
        gap: 12px;
    }
    .dialog-actions > * {
        flex: 1;
        text-align: center;
        padding: 11px;
        box-sizing: border-box;
    }
    .dialog-actions a {
        color: #222;
        background: #eee;
        border-radius: 3px;
        text-decoration: none;
    }
    @media (max-width: 600px) {
        .account-grid {
            grid-template-columns: 1fr;
            gap: 0;
        }
    }
</style>

<h1>Account Management</h1>

@if (session('success'))
    <p class="success">{{ session('success') }}</p>
@endif

@if ($errors->any() && !isset($editUser))
    <div class="error" role="alert">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form class="account-create" method="POST" action="{{ route('accounts.store') }}">
    @csrf
    <h2>Create Account</h2>

    <div class="account-grid">
        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" required maxlength="255"
                   value="{{ isset($editUser) ? '' : old('name') }}">
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required maxlength="255"
                   value="{{ isset($editUser) ? '' : old('email') }}">
        </div>
        <div class="field">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="user">User</option>
                <option value="organizer"
                    @selected(!isset($editUser) && old('role') === 'organizer')>
                    Organizer
                </option>
            </select>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password"
                   required minlength="8" maxlength="72" autocomplete="new-password">
        </div>
    </div>

    <button type="submit">Create Account</button>
</form>

<h2>Accounts</h2>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ ucfirst($user->role) }}</td>
                    <td>{{ $user->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                    <td>
                        <a class="account-edit-link"
                           href="{{ route('accounts.edit', $user) }}">Edit</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No accounts found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@isset($editUser)
    <dialog id="edit-dialog" class="account-dialog" aria-labelledby="edit-title">
        <h2 id="edit-title">Edit Account</h2>

        @if ($errors->any())
            <div class="error" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('accounts.update', $editUser) }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="edit-name">Name</label>
                <input id="edit-name" name="name" required autofocus maxlength="255"
                       value="{{ old('name', $editUser->name) }}">
            </div>

            <div class="field">
                <label for="edit-email">Email</label>
                <input id="edit-email" name="email" type="email" required maxlength="255"
                       value="{{ old('email', $editUser->email) }}">
            </div>

            <div class="field">
                <label for="edit-role">Role</label>
                <select id="edit-role" name="role" required>
                    <option value="user"
                        @selected(old('role', $editUser->role) === 'user')>
                        User
                    </option>
                    <option value="organizer"
                        @selected(old('role', $editUser->role) === 'organizer')>
                        Organizer
                    </option>
                </select>
            </div>

            <div class="field">
                <label for="edit-password">New Password (optional)</label>
                <input id="edit-password" name="password" type="password"
                       minlength="8" maxlength="72" autocomplete="new-password">
            </div>

            <div class="dialog-actions">
                <a id="cancel-edit" href="{{ route('accounts.index') }}">Cancel</a>
                <button type="submit">Save Changes</button>
            </div>
        </form>
    </dialog>

    <script>
        const editDialog = document.getElementById('edit-dialog');
        editDialog.showModal();

        editDialog.addEventListener('cancel', function (event) {
            event.preventDefault();
            window.location.href = document.getElementById('cancel-edit').href;
        });
    </script>
@endisset
@endsection