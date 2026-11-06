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
    public function create(): View
    {
        $permissions = Role::availablePermissions();

        return view('roles.create', compact('permissions'));
    }

    public function edit(Role $role): View
    {
        abort_if($role->slug === 'admin', 403);
        abort_if($role->is_system && ! in_array($role->slug, ['organizer', 'user'], true), 403);

        $permissions = Role::availablePermissions();
        $role->loadCount('users');

        return view('roles.edit', compact('role', 'permissions'));
    }

    public function index(): View
    {
        $roles = Role::withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('roles.index', compact('roles'));
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

        DB::transaction(function () use ($slug, $data): void {
            $role = Role::create([
                'slug' => $slug,
                'name' => $data['name'],
                'permissions' => $data['permissions'] ?? [],
                'is_system' => false,
            ]);

            $this->auditRole('created', $role, 'Role created.', null, [
                'name' => $role->name,
                'permissions' => $role->permissions ?? [],
            ]);
        });

        return redirect()->route('roles.index')->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->slug === 'admin', 403);

        if ($role->is_system || in_array($role->slug, ['organizer', 'user'], true)) {
            abort_unless(in_array($role->slug, ['organizer', 'user'], true), 403);
            $data = $this->validatedPermissions($request);
            DB::transaction(function () use ($role, $data): void {
                $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
                $oldPermissions = $lockedRole->permissions ?? [];
                $lockedRole->update(['permissions' => $data['permissions'] ?? []]);

                $this->auditRole(
                    'updated',
                    $lockedRole,
                    'Built-in role permissions updated.',
                    ['permissions' => $oldPermissions],
                    ['permissions' => $lockedRole->permissions ?? []]
                );
            });

            return redirect()->route('roles.index')->with('success', 'Role permissions updated.');
        }

        $data = $this->validatedRole($request, $role);
        $oldValues = ['name' => $role->name, 'permissions' => $role->permissions ?? []];

        DB::transaction(function () use ($role, $data, $oldValues): void {
            $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            $lockedRole->update([
                'name' => $data['name'],
                'permissions' => $data['permissions'] ?? [],
            ]);
            $this->auditRole('updated', $lockedRole, 'Role updated.', $oldValues, [
                'name' => $lockedRole->name,
                'permissions' => $lockedRole->permissions ?? [],
            ]);
        });

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system || in_array($role->slug, ['admin', 'organizer', 'user'], true), 403);

        $deleted = DB::transaction(function () use ($role): bool {
            $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();

            if (User::where('role', $lockedRole->slug)->exists()) {
                return false;
            }

            $this->auditRole('deleted', $lockedRole, 'Role deleted.', [
                'name' => $lockedRole->name,
                'permissions' => $lockedRole->permissions ?? [],
            ], null);
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

        $this->validatePermissionDependencies($data['permissions'] ?? []);

        return $data;
    }

    private function validatedPermissions(Request $request): array
    {
        $data = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(Role::availablePermissions()))],
        ]);

        $this->validatePermissionDependencies($data['permissions'] ?? []);

        return $data;
    }

    private function validatePermissionDependencies(array $permissions): void
    {
        if (
            in_array('events.publish', $permissions, true)
            && ! in_array('events.manage', $permissions, true)
        ) {
            throw ValidationException::withMessages([
                'permissions' => 'Event publishing requires event management permission.',
            ]);
        }

    }

    private function auditRole(string $action, Role $role, string $description, ?array $oldValues, ?array $newValues): void
    {
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
