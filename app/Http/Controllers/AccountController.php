<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        return view('createAcc', [
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['user', 'organizer'])],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect()->route('accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function edit(User $user)
    {
        return view('createAcc', [
            'users' => User::orderBy('name')->get(),
            'editUser' => $user,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::in(['user', 'organizer'])],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ]);

        if ($user->is($request->user()) && $data['role'] !== 'organizer') {
            return back()->withErrors([
                'role' => 'You cannot remove your own organizer access.',
            ])->withInput($request->except('password'));
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($user->email !== $data['email']) {
            $user->email_verified_at = null;
        }

        $user->fill($data);
        $user->save();

        return redirect()->route('accounts.index')
            ->with('success', 'Account updated successfully.');
    }
}