<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication to view appearance settings', function () {
    $this->get(route('settings.edit'))->assertRedirect(route('login'));
});

it('offers and persists each supported theme for the authenticated user', function () {
    $user = User::factory()->create();
    $themes = ['light', 'dark', 'midnight', 'ocean', 'forest'];

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Choose a theme')
        ->assertSee('Save Theme')
        ->assertDontSee('appearance_accent')
        ->assertSee("input.addEventListener('change'", false)
        ->assertSee('document.documentElement.dataset.theme = input.value', false);

    $appearanceResponse = $this->get(route('settings.edit'));
    foreach ($themes as $theme) {
        $appearanceResponse->assertSee('for="theme-'.$theme.'"', false)
            ->assertSee('html[data-theme="'.$theme.'"]', false);
    }

    foreach ($themes as $theme) {
        $this->put(route('settings.appearance.update'), ['appearance_theme' => $theme])
            ->assertRedirect(route('settings.edit'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-theme="'.$theme.'"', false);

        expect($user->fresh()->appearance_theme)->toBe($theme);
    }

    auth()->logout();
    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme="forest"', false);
});

it('keeps appearance preferences isolated between users', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $this->actingAs($firstUser)->put(route('settings.appearance.update'), [
        'appearance_theme' => 'midnight',
    ])->assertRedirect(route('settings.edit'));

    $this->actingAs($secondUser)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('data-theme="light"', false);

    expect($firstUser->fresh()->appearance_theme)->toBe('midnight')
        ->and($secondUser->fresh()->appearance_theme)->toBe('light');
});

it('rejects unsupported themes without changing the saved preference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->put(route('settings.appearance.update'), ['appearance_theme' => 'system'])
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors(['appearance_theme']);

    expect($user->fresh()->appearance_theme)->toBe('light');
});

it('defaults existing users to light and shows account actions without a sidebar settings item', function () {
    $user = User::factory()->create();

    expect($user->fresh()->appearance_theme)->toBe('light');

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme="light"', false)
        ->assertSee('--sidebar-muted: #C2C9D0', false)
        ->assertSee('.sidebar-link { color: var(--sidebar-text); }', false)
        ->assertSee('href="'.route('settings.edit').'"', false)
        ->assertSee('Appearance')
        ->assertSee('Profile')
        ->assertSee('action="'.route('logout').'"', false)
        ->assertDontSee('Settings');
});

it('preserves the existing csrf-protected logout action', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('name="_token"', false);

    $this->post(route('logout'))->assertRedirect();

    $this->assertGuest();
});
