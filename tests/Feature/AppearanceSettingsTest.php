<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication to view appearance settings', function () {
    $this->get(route('settings.edit'))->assertRedirect(route('login'));
});

it('allows an authenticated user to save and retain appearance preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Appearance');

    $this->put(route('settings.appearance.update'), [
        'appearance_theme' => 'dark',
        'appearance_accent' => 'teal',
    ])->assertRedirect(route('settings.edit'))
        ->assertSessionHas('status', 'Appearance settings saved.');

    auth()->logout();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('data-theme="dark"', false)
        ->assertSee('--blue: #0F766E', false)
        ->assertSee('value="dark" selected', false)
        ->assertSee('value="teal" selected', false);

    expect($user->fresh()->appearance_theme)->toBe('dark')
        ->and($user->fresh()->appearance_accent)->toBe('teal');
});

it('keeps appearance preferences isolated between users', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $this->actingAs($firstUser)->put(route('settings.appearance.update'), [
        'appearance_theme' => 'dark',
        'appearance_accent' => 'violet',
    ])->assertRedirect(route('settings.edit'));

    $this->actingAs($secondUser)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('data-theme="light"', false)
        ->assertSee('--blue: #2563EB', false);

    expect($firstUser->fresh()->appearance_theme)->toBe('dark')
        ->and($firstUser->fresh()->appearance_accent)->toBe('violet')
        ->and($secondUser->fresh()->appearance_theme)->toBe('light')
        ->and($secondUser->fresh()->appearance_accent)->toBe('blue');
});

it('rejects invalid theme and accent values', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->put(route('settings.appearance.update'), [
            'appearance_theme' => 'system',
            'appearance_accent' => 'url(javascript:alert(1))',
        ])
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors(['appearance_theme', 'appearance_accent']);

    expect($user->fresh()->appearance_theme)->toBe('light')
        ->and($user->fresh()->appearance_accent)->toBe('blue');
});

it('gives existing users the current light blue appearance by default', function () {
    $user = User::factory()->create();

    expect($user->fresh()->appearance_theme)->toBe('light')
        ->and($user->fresh()->appearance_accent)->toBe('blue');

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme="light"', false)
        ->assertSee('--blue: #2563EB', false);
});
