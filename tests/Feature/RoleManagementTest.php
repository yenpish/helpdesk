<?php

use App\Models\Role;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function roleAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

it('uses dedicated role create and edit pages while keeping system admin protected', function () {
    $admin = roleAdmin();
    $organizerRole = Role::where('slug', 'organizer')->firstOrFail();
    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $customRole = Role::create([
        'slug' => 'session-reviewer',
        'name' => 'Session Reviewer',
        'permissions' => ['events.manage'],
        'is_system' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('roles.create'))
        ->assertOk()
        ->assertSee('Create role')
        ->assertSee('name="permissions[]"', false);

    $this->actingAs($admin)
        ->get(route('roles.edit', $organizerRole))
        ->assertOk()
        ->assertSee('Edit permissions')
        ->assertSee('Organizer');

    $this->actingAs($admin)
        ->get(route('roles.edit', $customRole))
        ->assertOk()
        ->assertSee('Edit role')
        ->assertSee('value="Session Reviewer"', false);

    $this->actingAs($admin)
        ->get(route('roles.edit', $adminRole))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'organizer']))
        ->get(route('roles.create'))
        ->assertForbidden();
});

it('allows admins to create and rename custom roles with configurable permissions', function () {
    $admin = roleAdmin();

    $this->actingAs($admin)
        ->post(route('roles.store'), [
            'name' => 'Attendance Reviewer',
            'permissions' => ['events.manage', 'dashboard.view'],
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::where('slug', 'attendance-reviewer')->firstOrFail();

    expect($role->permissions)->toBe(['events.manage', 'dashboard.view'])
        ->and($role->is_system)->toBeFalse();
    expect(AuditLog::where('auditable_type', Role::class)->where('auditable_id', $role->id)->latest('id')->value('action'))
        ->toBe('created');

    $this->actingAs($admin)
        ->put(route('roles.update', $role), [
            'name' => 'Registration Reviewer',
            'permissions' => ['events.manage'],
        ])
        ->assertRedirect(route('roles.index'));

    expect($role->fresh()->slug)->toBe('attendance-reviewer')
        ->and($role->fresh()->name)->toBe('Registration Reviewer')
        ->and($role->fresh()->permissions)->toBe(['events.manage'])
        ->and(AuditLog::where('auditable_type', Role::class)->where('auditable_id', $role->id)->latest('id')->value('action'))
        ->toBe('updated');
});

it('allows admins to delete unassigned custom roles and prevents deleting assigned roles', function () {
    $admin = roleAdmin();

    $role = Role::create([
        'slug' => 'reviewer',
        'name' => 'Reviewer',
        'permissions' => [],
        'is_system' => false,
    ]);
    $assignedRole = Role::create([
        'slug' => 'session-helper',
        'name' => 'Session Helper',
        'permissions' => ['events.manage'],
        'is_system' => false,
    ]);
    User::factory()->create(['role' => $assignedRole->slug]);

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $assignedRole))
        ->assertRedirect()
        ->assertSessionHasErrors('role');

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect($assignedRole->fresh())->not->toBeNull()
        ->and($role->fresh())->toBeNull()
        ->and(AuditLog::where('auditable_type', Role::class)->where('auditable_id', $role->id)->latest('id')->value('action'))
        ->toBe('deleted');
});

it('assigns custom roles through the existing account create and edit actions', function () {
    $admin = roleAdmin();
    $role = Role::create([
        'slug' => 'event-coordinator',
        'name' => 'Event Coordinator',
        'permissions' => ['events.manage', 'events.publish'],
        'is_system' => false,
    ]);
    $user = User::factory()->create(['role' => 'organizer']);

    $this->actingAs($admin)
        ->put(route('accounts.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role->slug,
            'password' => '',
        ])
        ->assertRedirect(route('accounts.index'));

    expect($user->fresh()->role)->toBe($role->slug);

    $this->actingAs($admin)
        ->post(route('accounts.store'), [
            'name' => 'New Coordinator',
            'email' => 'coordinator@example.test',
            'role' => $role->slug,
            'password' => 'valid-password',
        ])
        ->assertRedirect(route('accounts.index'));

    expect(User::where('email', 'coordinator@example.test')->value('role'))
        ->toBe($role->slug);
});

it('denies non-admins all direct role administration requests', function () {
    $organizer = User::factory()->create(['role' => 'organizer']);
    $role = Role::create([
        'slug' => 'reviewer',
        'name' => 'Reviewer',
        'permissions' => [],
        'is_system' => false,
    ]);

    $this->actingAs($organizer)
        ->get(route('roles.index'))
        ->assertForbidden();
    $this->actingAs($organizer)
        ->post(route('roles.store'), ['name' => 'Bad Role'])
        ->assertForbidden();
    $this->actingAs($organizer)
        ->put(route('roles.update', $role), ['name' => 'Changed'])
        ->assertForbidden();
    $this->actingAs($organizer)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();
});

it('enforces custom permissions on protected event routes', function () {
    $role = Role::create([
        'slug' => 'event-coordinator',
        'name' => 'Event Coordinator',
        'permissions' => ['events.manage'],
        'is_system' => false,
    ]);
    $user = User::factory()->create(['role' => $role->slug]);

    $this->actingAs($user)
        ->get(route('events.index'))
        ->assertOk();

    $role->update(['permissions' => []]);

    $this->actingAs($user->fresh())
        ->get(route('events.index'))
        ->assertForbidden();
});

it('preserves built-in admin and organizer access and protects the last administrator', function () {
    $admin = roleAdmin();
    $organizer = User::factory()->create(['role' => 'organizer']);

    expect($admin->hasPermission('roles.manage'))->toBeTrue()
        ->and($organizer->hasPermission('events.manage'))->toBeTrue()
        ->and($organizer->hasPermission('events.publish'))->toBeTrue();

    $this->actingAs($admin)
        ->put(route('accounts.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'user',
            'password' => '',
        ])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe('admin');
});

it('allows administrators to edit Organizer and User permissions without changing built-in identity', function () {
    $admin = roleAdmin();
    $organizerRole = Role::where('slug', 'organizer')->firstOrFail();
    $userRole = Role::where('slug', 'user')->firstOrFail();
    $assigned = User::factory()->create(['role' => 'organizer']);

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertSee('1 assigned account(s)');

    $this->actingAs($admin)
        ->put(route('roles.update', $organizerRole), [
            'name' => 'Changed by request',
            'permissions' => ['dashboard.view'],
        ])
        ->assertRedirect(route('roles.index'));

    expect($organizerRole->fresh()->slug)->toBe('organizer')
        ->and($organizerRole->fresh()->name)->toBe('Organizer')
        ->and($organizerRole->fresh()->permissions)->toBe(['dashboard.view'])
        ->and($assigned->fresh()->hasPermission('events.manage'))->toBeFalse()
        ->and(AuditLog::where('auditable_type', Role::class)->where('auditable_id', $organizerRole->id)->latest('id')->value('action'))->toBe('updated');

    $this->actingAs($admin)
        ->put(route('roles.update', $userRole), ['permissions' => ['dashboard.view']])
        ->assertRedirect(route('roles.index'));

    expect($userRole->fresh()->permissions)->toBe(['dashboard.view']);
});

it('keeps the System Admin role immutable and rejects other built-in role deletion', function () {
    $admin = roleAdmin();
    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $organizerRole = Role::where('slug', 'organizer')->firstOrFail();
    $originalPermissions = $adminRole->permissions;

    $this->actingAs($admin)
        ->put(route('roles.update', $adminRole), ['permissions' => []])
        ->assertForbidden();
    $this->actingAs($admin)
        ->delete(route('roles.destroy', $organizerRole))
        ->assertForbidden();

    expect($adminRole->fresh()->permissions)->toBe($originalPermissions)
        ->and($organizerRole->fresh())->not->toBeNull();
});
