<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('name')->paginate(20);

        return view('accounts.index', compact('users'));
    }

    public function create(): View
    {
        return view('accounts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'organizer', 'user'])],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => 'User account created.',
            'old_values' => null,
            'new_values' => json_encode($user->fresh()->getAttributes()),
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function edit(User $user): View
    {
        return view('accounts.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::in(['admin', 'organizer', 'user'])],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ]);

        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()
                ->withErrors([
                    'role' => 'You cannot remove your own administrator access.',
                ])
                ->withInput($request->except('password'));
        }

        $oldValues = $user->getAttributes();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($user->email !== $data['email']) {
            $data['email_verified_at'] = null;
        }

        $user->fill($data);
        $user->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => 'User account updated.',
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($user->fresh()->getAttributes()),
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return back()->withErrors([
                'account' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors([
                'account' => 'You cannot delete the last administrator account.',
            ]);
        }

        $oldValues = $user->getAttributes();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => 'User account deleted.',
            'old_values' => json_encode($oldValues),
            'new_values' => null,
        ]);

        $user->delete();

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account deleted successfully.');
    }
}
