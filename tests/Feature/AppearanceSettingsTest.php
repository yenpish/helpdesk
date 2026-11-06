<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function appearanceSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'appearance_theme' => 'light',
        'appearance_accent' => 'blue',
        'appearance_density' => 'comfortable',
        'appearance_sidebar' => 'standard',
        'appearance_gradients' => '0',
    ], $overrides);
}

function appearanceRelativeLuminance(string $hex): float
{
    $channels = array_map(static function (int $channel): float {
        $value = $channel / 255;

        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, sscanf(ltrim($hex, '#'), '%02x%02x%02x'));

    return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
}

function appearanceContrastRatio(string $first, string $second): float
{
    $luminances = [appearanceRelativeLuminance($first), appearanceRelativeLuminance($second)];
    rsort($luminances);

    return ($luminances[0] + 0.05) / ($luminances[1] + 0.05);
}

it('requires authentication to view appearance settings', function () {
    $this->get(route('settings.edit'))->assertRedirect(route('login'));
});

it('shows labelled settings sections and previews all five existing themes immediately', function () {
    $user = User::factory()->create();
    $themes = ['light', 'dark', 'midnight', 'ocean', 'forest'];

    $response = $this->actingAs($user)->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Appearance')
        ->assertSee('Layout Preferences')
        ->assertDontSee('View profile')
        ->assertSee('Reset to Defaults')
        ->assertSee('Save Changes')
        ->assertSee('Discard Changes')
        ->assertSee("const appearanceKeys = ['theme', 'accent', 'density', 'gradients']", false)
        ->assertSee('applyPreview(current)', false)
        ->assertSee('writeForm(saved)', false)
        ->assertSee('writeForm(defaults)', false);

    foreach ($themes as $theme) {
        $response->assertSee('for="theme-'.$theme.'"', false)
            ->assertSee('data-preview-theme="'.$theme.'"', false)
            ->assertSee('html[data-theme="'.$theme.'"]', false);
    }

    expect($appearanceOptions = config('appearance.accents'))->toHaveCount(10);
    foreach ($appearanceOptions as $accentKey => $accent) {
        $response->assertSee('value="'.$accentKey.'"', false)
            ->assertSee($accent['label'])
            ->assertSee('for="accent-'.$accentKey.'"', false);
    }
    $response->assertSee('theme-choice-input:focus-visible', false)
        ->assertSee('aria-live="polite"', false)
        ->assertDontSee('Sidebar width')
        ->assertSee('name="appearance_sidebar" value="standard"', false);
});

it('keeps curated accent text and primary actions readable across all themes', function () {
    $accents = config('appearance.accents');
    $darkSurfaces = ['#202326', '#211F28', '#1D292D', '#202923'];

    foreach ($accents as $accent) {
        expect(appearanceContrastRatio($accent['light'], '#FFFFFF'))->toBeGreaterThanOrEqual(4.5)
            ->and(appearanceContrastRatio($accent['button'], '#FFFFFF'))->toBeGreaterThanOrEqual(4.5)
            ->and(appearanceContrastRatio($accent['button_hover'], '#FFFFFF'))->toBeGreaterThanOrEqual(4.5);

        foreach ($darkSurfaces as $surface) {
            expect(appearanceContrastRatio($accent['dark'], $surface))->toBeGreaterThanOrEqual(4.5);
        }
    }
});

it('continues to accept and apply every existing theme', function () {
    $user = User::factory()->create();

    foreach (['light', 'dark', 'midnight', 'ocean', 'forest'] as $theme) {
        $this->actingAs($user)->put(route('settings.appearance.update'), appearanceSettingsPayload([
            'appearance_theme' => $theme,
        ]))->assertRedirect(route('settings.edit'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-theme="'.$theme.'"', false);
        expect($user->fresh()->appearance_theme)->toBe($theme);
    }
});

it('accepts each curated accent palette', function () {
    $user = User::factory()->create();

    foreach (array_keys(config('appearance.accents')) as $accent) {
        $this->actingAs($user)->put(route('settings.appearance.update'), appearanceSettingsPayload([
            'appearance_accent' => $accent,
        ]))->assertRedirect(route('settings.edit'));

        expect($user->fresh()->appearance_accent)->toBe($accent);
    }
});

it('persists appearance and layout preferences for only the signed-in user', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $this->actingAs($firstUser)->put(route('settings.appearance.update'), appearanceSettingsPayload([
        'appearance_theme' => 'midnight',
        'appearance_accent' => 'violet',
        'appearance_density' => 'compact',
        'appearance_sidebar' => 'wide',
        'appearance_gradients' => '1',
    ]))->assertRedirect(route('settings.edit'));

    $savedUser = $firstUser->fresh();
    expect($savedUser->appearance_theme)->toBe('midnight')
        ->and($savedUser->appearance_accent)->toBe('violet')
        ->and($savedUser->appearance_density)->toBe('compact')
        ->and($savedUser->appearance_sidebar)->toBe('wide')
        ->and($savedUser->appearance_gradients)->toBeTrue();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme="midnight"', false)
        ->assertSee('data-accent="violet"', false)
        ->assertSee('data-density="compact"', false)
        ->assertSee('data-sidebar="wide"', false)
        ->assertSee('data-gradients="on"', false);

    $this->actingAs($secondUser)->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('data-theme="light"', false)
        ->assertSee('data-accent="blue"', false)
        ->assertSee('data-density="comfortable"', false);
    expect($secondUser->fresh()->appearance_density)->toBe('comfortable')
        ->and($secondUser->fresh()->appearance_sidebar)->toBe('standard')
        ->and($secondUser->fresh()->appearance_gradients)->toBeFalse();
});

it('rejects unsupported appearance preferences without changing saved values', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('settings.edit'))
        ->put(route('settings.appearance.update'), appearanceSettingsPayload([
            'appearance_theme' => 'system',
            'appearance_accent' => 'neon',
            'appearance_density' => 'tiny',
            'appearance_sidebar' => 'hidden',
            'appearance_gradients' => 'maybe',
        ]))
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors([
            'appearance_theme', 'appearance_accent', 'appearance_density',
            'appearance_sidebar', 'appearance_gradients',
        ]);

    expect($user->fresh()->appearance_theme)->toBe('light')
        ->and($user->fresh()->appearance_accent)->toBe('blue')
        ->and($user->fresh()->appearance_density)->toBe('comfortable');
});

it('saves reset defaults when the user chooses to reset and save', function () {
    $user = User::factory()->create([
        'appearance_theme' => 'forest',
        'appearance_accent' => 'green',
        'appearance_density' => 'compact',
        'appearance_sidebar' => 'wide',
        'appearance_gradients' => true,
    ]);

    $this->actingAs($user)->put(route('settings.appearance.update'), appearanceSettingsPayload([
        'appearance_sidebar' => 'wide',
    ]))
        ->assertRedirect(route('settings.edit'));

    expect($user->fresh()->appearance_theme)->toBe('light')
        ->and($user->fresh()->appearance_accent)->toBe('blue')
        ->and($user->fresh()->appearance_density)->toBe('comfortable')
        ->and($user->fresh()->appearance_sidebar)->toBe('wide')
        ->and($user->fresh()->appearance_gradients)->toBeFalse();
});

it('hides the retired sidebar-width control and preserves the saved value on appearance saves', function () {
    $user = User::factory()->create(['appearance_sidebar' => 'wide']);

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertOk()
        ->assertDontSee('Sidebar width')
        ->assertSee('name="appearance_sidebar" value="wide"', false);

    $this->put(route('settings.appearance.update'), appearanceSettingsPayload([
        'appearance_sidebar' => 'wide',
        'appearance_theme' => 'midnight',
    ]))->assertRedirect(route('settings.edit'));

    expect($user->fresh()->appearance_sidebar)->toBe('wide')
        ->and($user->fresh()->appearance_theme)->toBe('midnight');
});

it('keeps existing profile and logout account actions available', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('href="'.route('profile').'"', false)
        ->assertSee($user->name)
        ->assertSee($user->email);

    $this->get(route('profile'))->assertOk();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('name="_token"', false);

    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});
