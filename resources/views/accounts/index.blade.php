@extends('layouts.app')

@section('title', 'Account Management')

@section('content')
    <div class="app-box" style="max-width:1200px;">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <div>
                <h1 style="margin:0 0 6px;">Account Management</h1>
                <p style="margin:0; color:#666;">
                    Manage system users, roles and account access.
                </p>
            </div>

            <a href="{{ route('accounts.create') }}"
               style="padding:10px 16px; background:#222; color:white; text-decoration:none; border-radius:4px;">
                + Create Account
            </a>
        </div>

        @if(session('success'))
            <div style="padding:12px 14px; margin-bottom:20px; background:#e8f5e9; border:1px solid #c8e6c9; color:#2e7d32;">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div style="padding:12px 14px; margin-bottom:20px; background:#ffebee; border:1px solid #ffcdd2; color:#c62828;">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; background:white;">
                <thead>
                <tr style="background:#f1f1f1; text-align:left;">
                    <th style="padding:12px; border-bottom:1px solid #ddd;">Name</th>
                    <th style="padding:12px; border-bottom:1px solid #ddd;">Email</th>
                    <th style="padding:12px; border-bottom:1px solid #ddd;">Role</th>
                    <th style="padding:12px; border-bottom:1px solid #ddd;">Created</th>
                    <th style="padding:12px; border-bottom:1px solid #ddd;">Actions</th>
                </tr>
                </thead>

                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td style="padding:12px; border-bottom:1px solid #eee;">
                            {{ $user->name }}
                        </td>

                        <td style="padding:12px; border-bottom:1px solid #eee;">
                            {{ $user->email }}
                        </td>

                        <td style="padding:12px; border-bottom:1px solid #eee;">
                            {{ ucfirst($user->role) }}
                        </td>

                        <td style="padding:12px; border-bottom:1px solid #eee;">
                            {{ $user->created_at?->format('d M Y') }}
                        </td>

                        <td style="padding:12px; border-bottom:1px solid #eee; white-space:nowrap;">
                            <a href="{{ route('accounts.edit', $user) }}"
                               style="margin-right:10px;">
                                Edit
                            </a>

                            @if($user->id !== auth()->id())
                                <form method="POST"
                                      action="{{ route('accounts.destroy', $user) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm('Delete this account? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            style="border:0; background:none; color:#c62828; cursor:pointer; padding:0;">
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding:30px; text-align:center;">
                            No accounts found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:20px;">
            {{ $users->links() }}
        </div>

    </div>
@endsection
