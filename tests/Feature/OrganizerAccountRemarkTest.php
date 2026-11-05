<?php

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function remarkAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function accountUpdateData(User $user, array $extra = []): array
{
    return [
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,
        'password' => '',
        ...$extra,
    ];
}

it('allows admins to add, edit, and clear an organizer internal remark', function () {
    $admin = remarkAdmin();
    $organizer = User::factory()->create(['role' => 'organizer']);

    $this->actingAs($admin)
        ->get(route('accounts.edit', $organizer))
        ->assertOk()
        ->assertSee('Internal Remark');

    $this->put(route('accounts.update', $organizer), accountUpdateData($organizer, [
        'internal_remark' => 'Coordinate the room setup with facilities.',
    ]))->assertRedirect(route('accounts.index'));

    expect($organizer->fresh()->internal_remark)->toBe('Coordinate the room setup with facilities.');

    $this->put(route('accounts.update', $organizer), accountUpdateData($organizer, [
        'internal_remark' => 'Use the updated room setup plan.',
    ]))->assertRedirect(route('accounts.index'));

    expect($organizer->fresh()->internal_remark)->toBe('Use the updated room setup plan.');

    $this->put(route('accounts.update', $organizer), accountUpdateData($organizer, [
        'internal_remark' => '',
    ]))->assertRedirect(route('accounts.index'));

    expect($organizer->fresh()->internal_remark)->toBeNull();

    $remarkAudit = AuditLog::where('auditable_type', User::class)
        ->where('auditable_id', $organizer->id)
        ->latest('id')
        ->firstOrFail();
    expect($remarkAudit->getRawOriginal('old_values'))
        ->not->toContain('Use the updated room setup plan.')
        ->and($remarkAudit->getRawOriginal('new_values'))
        ->not->toContain('Use the updated room setup plan.')
        ->toContain('Cleared');
});

it('rejects internal remarks longer than 2000 characters', function () {
    $admin = remarkAdmin();
    $organizer = User::factory()->create(['role' => 'organizer']);

    $this->actingAs($admin)
        ->from(route('accounts.edit', $organizer))
        ->put(route('accounts.update', $organizer), accountUpdateData($organizer, [
            'internal_remark' => str_repeat('x', 2001),
        ]))
        ->assertRedirect(route('accounts.edit', $organizer))
        ->assertSessionHasErrors('internal_remark');

    expect($organizer->fresh()->internal_remark)->toBeNull();
});

it('blocks organizers, regular users, and guests from viewing or changing internal remarks', function () {
    $admin = remarkAdmin();
    $organizer = User::factory()->create(['role' => 'organizer']);
    $organizer->forceFill(['internal_remark' => 'Admin-only confidential note.'])->save();
    $regularUser = User::factory()->create(['role' => 'user']);

    foreach ([$organizer, $regularUser] as $account) {
        $this->actingAs($account)
            ->get(route('accounts.edit', $organizer))
            ->assertForbidden();

        $this->actingAs($account)
            ->put(route('accounts.update', $organizer), accountUpdateData($organizer, [
                'internal_remark' => 'Unauthorized replacement.',
            ]))
            ->assertForbidden();
    }

    auth()->logout();
    $this->get(route('accounts.edit', $organizer))->assertRedirect(route('login'));

    $this->actingAs($organizer)
        ->get(route('profile'))
        ->assertOk()
        ->assertDontSee('Admin-only confidential note.');

    expect($organizer->fresh()->internal_remark)->toBe('Admin-only confidential note.')
        ->and($organizer->toArray())->not->toHaveKey('internal_remark');
});

it('ignores remark input for non-organizer accounts and excludes it from mass assignment', function () {
    $admin = remarkAdmin();
    $regularUser = User::factory()->create(['role' => 'user']);

    $this->actingAs($admin)
        ->get(route('accounts.edit', $regularUser))
        ->assertOk()
        ->assertDontSee('Internal Remark');

    $this->put(route('accounts.update', $regularUser), accountUpdateData($regularUser, [
        'internal_remark' => 'Must not be stored on this account.',
    ]))->assertRedirect(route('accounts.index'));

    $regularUser->fill(['internal_remark' => 'Mass assignment attempt.']);

    expect($regularUser->fresh()->internal_remark)->toBeNull()
        ->and($regularUser->internal_remark)->toBeNull();
});
