<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function roleAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

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

    $this->actingAs($admin)
        ->put(route('roles.update', $role), [
            'name' => 'Registration Reviewer',
            'permissions' => ['events.manage'],
        ])
        ->assertRedirect(route('roles.index'));

    expect($role->fresh()->slug)->toBe('attendance-reviewer')
        ->and($role->fresh()->name)->toBe('Registration Reviewer')
        ->and($role->fresh()->permissions)->toBe(['events.manage']);
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
        ->and($role->fresh())->toBeNull();
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
