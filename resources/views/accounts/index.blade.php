@extends('layouts.app')

@section('title', 'User Accounts')
@section('section', 'Administration')

@section('content')
    <div class="container">
        <header class="page-header">
            <div class="page-heading">
                <h1>User accounts</h1>
                <p>Manage system access and account roles.</p>
            </div>
            <a href="{{ route('accounts.create') }}" class="btn btn-primary">Create account</a>
        </header>

        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @if($users->count())
            <div class="table-responsive">
                <table class="table accounts-table">
                    <colgroup><col style="width: 18%"><col style="width: 28%"><col style="width: 22%"><col style="width: 18%"><col style="width: 14%"></colgroup>
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th class="actions-cell">Actions</th></tr></thead>
                    <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td><strong>{{ $user->name }}</strong>@if($user->id === auth()->id()) <span class="text-muted">(you)</span>@endif</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="role-label">{{ $user->roleDefinition?->name ?? 'Unassigned legacy role' }}</span>
                            </td>
                            <td>{{ $user->created_at?->format('d M Y') }}</td>
                            <td class="actions-cell">
                                <a href="{{ route('accounts.edit', $user) }}" class="btn btn-secondary btn-sm">Edit</a>
                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('accounts.destroy', $user) }}" class="inline-form" onsubmit="return confirm('Delete this account? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('components.pagination-row', ['paginator' => $users, 'ariaLabel' => 'Account pages'])
        @else
            <div class="empty-state">
                <h2>No user accounts</h2>
                <p>Create an account and assign the appropriate role.</p>
                <a href="{{ route('accounts.create') }}" class="btn btn-primary">Create account</a>
            </div>
        @endif
    </div>
@endsection
