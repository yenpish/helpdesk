<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppearanceSettingsController extends Controller
{
    private const THEMES = [
        'light' => 'blue',
        'dark' => 'blue',
        'midnight' => 'violet',
        'ocean' => 'teal',
        'forest' => 'green',
    ];

    public function edit(): View
    {
        return view('settings.appearance', ['themes' => array_keys(self::THEMES)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'appearance_theme' => ['required', Rule::in(array_keys(self::THEMES))],
        ]);

        $request->user()->update([
            'appearance_theme' => $validated['appearance_theme'],
            'appearance_accent' => self::THEMES[$validated['appearance_theme']],
        ]);

        return redirect()->route('settings.edit')->with('status', 'Appearance settings saved.');
    }
}
