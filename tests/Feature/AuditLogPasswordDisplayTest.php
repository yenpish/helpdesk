<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows that a password changed without exposing its value or hash', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'organizer']);

    AuditLog::create([
        'user_id' => $admin->id,
        'action' => 'updated',
        'auditable_type' => User::class,
        'auditable_id' => $target->id,
        'description' => 'User account and password updated.',
        'old_values' => [
            'name' => $target->name,
            'email' => 'previous-private@example.test',
            'role' => 'organizer',
            'password' => 'old-password-hash-must-not-display',
        ],
        'new_values' => [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'organizer',
            'password' => 'new-password-hash-must-not-display',
            'pin' => 'JTSN',
            'nested' => ['access_token' => 'plaintext-token-must-not-persist'],
        ],
    ]);

    $stored = AuditLog::latest('id')->firstOrFail();
    expect($stored->getRawOriginal('old_values'))
        ->not->toContain('old-password-hash-must-not-display')
        ->and($stored->getRawOriginal('new_values'))
        ->not->toContain('new-password-hash-must-not-display')
        ->not->toContain('JTSN')
        ->not->toContain('plaintext-token-must-not-persist')
        ->toContain($target->email);

    $this->actingAs($admin)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertSee('View changes')
        ->assertSee('Password')
        ->assertSee('Changed (value hidden)')
        ->assertSee('Address hidden')
        ->assertDontSee('old-password-hash-must-not-display')
        ->assertDontSee('new-password-hash-must-not-display')
        ->assertDontSee($target->email)
        ->assertDontSee('previous-private@example.test');
});
