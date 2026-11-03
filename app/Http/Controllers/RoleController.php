<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
        $permissions = Role::availablePermissions();

        return view('roles.index', compact('roles', 'permissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedRole($request);
        $slug = Str::slug($data['name']);

        if ($slug === '' || Role::where('slug', $slug)->exists()) {
            return back()->withErrors([
                'name' => 'That role name is already in use or cannot form a role identifier.',
            ])->withInput();
        }

        Role::create([
            'slug' => $slug,
            'name' => $data['name'],
            'permissions' => $data['permissions'] ?? [],
            'is_system' => false,
        ]);

        return redirect()->route('roles.index')->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403);

        $data = $this->validatedRole($request, $role);

        $role->update([
            'name' => $data['name'],
            'permissions' => $data['permissions'] ?? [],
        ]);

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403);

        $deleted = DB::transaction(function () use ($role): bool {
            $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();

            if (User::where('role', $lockedRole->slug)->exists()) {
                return false;
            }

            $lockedRole->delete();

            return true;
        });

        if (! $deleted) {
            return back()->withErrors([
                'role' => 'A role assigned to one or more accounts cannot be deleted.',
            ]);
        }

        return redirect()->route('roles.index')->with('success', 'Role deleted.');
    }

    private function validatedRole(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('roles', 'name')->ignore($role?->id),
                'regex:/[\pL\pN]/u',
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(Role::availablePermissions()))],
        ]);

        if (
            in_array('events.publish', $data['permissions'] ?? [], true)
            && ! in_array('events.manage', $data['permissions'] ?? [], true)
        ) {
            throw ValidationException::withMessages([
                'permissions' => 'Event publishing requires event management permission.',
            ]);
        }

        return $data;
    }
}
