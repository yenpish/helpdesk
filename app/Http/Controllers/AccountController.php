<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $users = User::with('roleDefinition')->orderBy('name')->paginate(20);

        return view('accounts.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();

        return view('accounts.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::exists('roles', 'slug')],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $data['password'] = Hash::make($data['password']);

        DB::transaction(function () use ($data): void {
            Role::where('slug', $data['role'])->lockForUpdate()->firstOrFail();
            $user = User::create($data);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'created',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'description' => 'User account created.',
                'old_values' => null,
                'new_values' => $this->accountAuditValues($user->fresh()),
            ]);
        });

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();
        $canManageInternalRemark = $this->canManageInternalRemark($user);

        return view('accounts.edit', compact('user', 'roles', 'canManageInternalRemark'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::exists('roles', 'slug')],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ];

        $canManageInternalRemark = $this->canManageInternalRemark($user);
        if ($canManageInternalRemark) {
            $rules['internal_remark'] = ['nullable', 'string', 'max:2000'];
        }

        $data = $request->validate($rules);
        $targetRole = Role::where('slug', $data['role'])->firstOrFail();
        $canSaveInternalRemark = $canManageInternalRemark
            && $targetRole->slug !== 'admin'
            && in_array('events.manage', $targetRole->permissions ?? [], true);

        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()
                ->withErrors([
                    'role' => 'You cannot remove your own administrator access.',
                ])
                ->withInput($request->except('password'));
        }

        $passwordChanged = !empty($data['password']);
        $remarkProvided = array_key_exists('internal_remark', $data);
        $internalRemark = $data['internal_remark'] ?? null;
        unset($data['internal_remark']);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $lastAdminBlocked = DB::transaction(function () use (
            $data,
            $user,
            $passwordChanged,
            $canSaveInternalRemark,
            $remarkProvided,
            $internalRemark
        ): bool {
            Role::where('slug', 'admin')->lockForUpdate()->firstOrFail();
            Role::where('slug', $data['role'])->lockForUpdate()->firstOrFail();
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (
                $lockedUser->role === 'admin'
                && $data['role'] !== 'admin'
                && User::where('role', 'admin')->count() <= 1
            ) {
                return true;
            }

            $oldValues = $this->accountAuditValues($lockedUser);
            $oldRemark = $lockedUser->internal_remark;

            if ($lockedUser->email !== $data['email']) {
                $data['email_verified_at'] = null;
            }

            $lockedUser->fill($data);
            if ($canSaveInternalRemark && $remarkProvided) {
                $lockedUser->internal_remark = $internalRemark;
            }
            $lockedUser->save();

            $newAuditValues = $this->accountAuditValues($lockedUser->fresh());
            if ($canSaveInternalRemark && $remarkProvided && $oldRemark !== $lockedUser->internal_remark) {
                $newAuditValues['internal_remark'] = $lockedUser->internal_remark === null || $lockedUser->internal_remark === ''
                    ? 'Cleared'
                    : 'Changed';
                $oldValues['internal_remark'] = $oldRemark === null || $oldRemark === ''
                    ? 'No remark'
                    : 'Existing remark';
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'updated',
                'auditable_type' => User::class,
                'auditable_id' => $lockedUser->id,
                'description' => $passwordChanged
                    ? 'User account and password updated.'
                    : 'User account updated.',
                'old_values' => $oldValues,
                'new_values' => $newAuditValues,
            ]);

            return false;
        });

        if ($lastAdminBlocked) {
            return back()
                ->withErrors([
                    'role' => 'You cannot remove administrator access from the last administrator account.',
                ])
                ->withInput($request->except('password'));
        }

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

        $lastAdminBlocked = DB::transaction(function () use ($user): bool {
            Role::where('slug', 'admin')->lockForUpdate()->firstOrFail();
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Role::where('slug', $lockedUser->role)->lockForUpdate()->first();

            if ($lockedUser->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
                return true;
            }

            $oldValues = $this->accountAuditValues($lockedUser);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'auditable_type' => User::class,
                'auditable_id' => $lockedUser->id,
                'description' => 'User account deleted.',
                'old_values' => $oldValues,
                'new_values' => null,
            ]);

            $lockedUser->delete();

            return false;
        });

        if ($lastAdminBlocked) {
            return back()->withErrors([
                'account' => 'You cannot delete the last administrator account.',
            ]);
        }

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Account deleted successfully.');
    }

    private function accountAuditValues(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    private function canManageInternalRemark(User $user): bool
    {
        return $user->role !== 'admin' && $user->hasPermission('events.manage');
    }
}
