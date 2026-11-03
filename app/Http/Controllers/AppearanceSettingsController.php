<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppearanceSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.appearance', [
            'appearanceOptions' => config('appearance'),
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'appearance_theme' => ['required', Rule::in(array_keys(config('appearance.themes')))],
            'appearance_accent' => ['required', Rule::in(array_keys(config('appearance.accents')))],
            'appearance_density' => ['required', Rule::in(array_keys(config('appearance.density')))],
            'appearance_sidebar' => ['required', Rule::in(array_keys(config('appearance.sidebar')))],
            'appearance_gradients' => ['required', Rule::in(['0', '1'])],
        ]);

        $request->user()->update([
            'appearance_theme' => $validated['appearance_theme'],
            'appearance_accent' => $validated['appearance_accent'],
            'appearance_density' => $validated['appearance_density'],
            'appearance_sidebar' => $validated['appearance_sidebar'],
            'appearance_gradients' => $validated['appearance_gradients'] === '1',
        ]);

        return redirect()->route('settings.edit')->with('status', 'Settings saved.');
    }
}
