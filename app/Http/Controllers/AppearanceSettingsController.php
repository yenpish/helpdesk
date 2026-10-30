<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppearanceSettingsController extends Controller
{
    private const ACCENTS = [
        'blue' => 'Blue',
        'teal' => 'Teal',
        'violet' => 'Violet',
        'amber' => 'Amber',
    ];

    public function edit(): View
    {
        return view('settings.appearance', ['accents' => self::ACCENTS]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'appearance_theme' => ['required', Rule::in(['light', 'dark'])],
            'appearance_accent' => ['required', Rule::in(array_keys(self::ACCENTS))],
        ]);

        $request->user()->update($validated);

        return redirect()->route('settings.edit')->with('status', 'Appearance settings saved.');
    }
}
