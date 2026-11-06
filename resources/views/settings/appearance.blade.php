@extends('layouts.app')

@section('section', 'Settings')
@section('title', 'Settings - Attendance Management')

@section('content')
    @php
        $defaults = $appearanceOptions['defaults'];
        $saved = [
            'theme' => array_key_exists($user->appearance_theme, $appearanceOptions['themes']) ? $user->appearance_theme : $defaults['theme'],
            'accent' => array_key_exists($user->appearance_accent, $appearanceOptions['accents']) ? $user->appearance_accent : $defaults['accent'],
            'density' => array_key_exists($user->appearance_density, $appearanceOptions['density']) ? $user->appearance_density : $defaults['density'],
            'sidebar' => array_key_exists($user->appearance_sidebar, $appearanceOptions['sidebar']) ? $user->appearance_sidebar : $defaults['sidebar'],
            'gradients' => (bool) $user->appearance_gradients,
        ];
        $selectedTheme = old('appearance_theme', $saved['theme']);
        $selectedAccent = old('appearance_accent', $saved['accent']);
        $selectedDensity = old('appearance_density', $saved['density']);
        $selectedGradientsValue = (string) old('appearance_gradients', $saved['gradients'] ? '1' : '0');
        $selectedGradients = in_array($selectedGradientsValue, ['0', '1'], true)
            ? $selectedGradientsValue === '1'
            : $saved['gradients'];
        $selectedTheme = is_string($selectedTheme) && array_key_exists($selectedTheme, $appearanceOptions['themes']) ? $selectedTheme : $saved['theme'];
        $selectedAccent = is_string($selectedAccent) && array_key_exists($selectedAccent, $appearanceOptions['accents']) ? $selectedAccent : $saved['accent'];
        $selectedDensity = is_string($selectedDensity) && array_key_exists($selectedDensity, $appearanceOptions['density']) ? $selectedDensity : $saved['density'];
    @endphp

    <div class="content-panel appearance-page">
        <div class="page-header appearance-page-header">
            <div class="page-heading">
                <h1>Settings</h1>
                <p>Choose how the app looks and feels.</p>
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.appearance.update') }}" id="appearance-settings-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="appearance_sidebar" value="{{ $saved['sidebar'] }}">

            <section class="settings-section" aria-labelledby="appearance-heading">
                <div class="settings-section-heading">
                    <h2 id="appearance-heading">Appearance</h2>
                </div>

                <fieldset class="settings-fieldset">
                    <legend class="settings-label">Theme</legend>
                    <div class="theme-grid" role="radiogroup" aria-label="Theme">
                        @foreach($appearanceOptions['themes'] as $themeKey => $theme)
                            <label class="theme-choice" for="theme-{{ $themeKey }}">
                                <input class="theme-choice-input" type="radio" id="theme-{{ $themeKey }}" name="appearance_theme" value="{{ $themeKey }}" @checked($selectedTheme === $themeKey) required>
                                <span class="theme-choice-card" data-preview-theme="{{ $themeKey }}">
                                    <span class="theme-preview" aria-hidden="true"><span class="theme-preview-nav"></span><span class="theme-preview-main"><span class="theme-preview-panel"></span><span class="theme-preview-accent"></span></span></span>
                                    <span class="theme-choice-name">{{ $theme['label'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('appearance_theme')<div class="field-error">{{ $message }}</div>@enderror
                </fieldset>

                <fieldset class="settings-fieldset accent-fieldset">
                    <legend class="settings-label">Accent colour</legend>
                    <div class="accent-grid" role="radiogroup" aria-label="Accent colour">
                        @foreach($appearanceOptions['accents'] as $accentKey => $accent)
                            <label class="accent-choice" for="accent-{{ $accentKey }}">
                                <input type="radio" id="accent-{{ $accentKey }}" name="appearance_accent" value="{{ $accentKey }}" @checked($selectedAccent === $accentKey) required>
                                <span class="accent-swatch" style="--swatch: {{ $accent['swatch'] }}" aria-hidden="true"></span>
                                <span>{{ $accent['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('appearance_accent')<div class="field-error">{{ $message }}</div>@enderror
                </fieldset>

                <div class="gradient-setting">
                    <input type="hidden" name="appearance_gradients" value="0">
                    <input id="appearance-gradients" type="checkbox" name="appearance_gradients" value="1" @checked($selectedGradients)>
                    <label for="appearance-gradients">
                        <span class="settings-label">Subtle gradients</span>
                        <small>Use a coordinated accent blend on primary actions.</small>
                    </label>
                </div>
                @error('appearance_gradients')<div class="field-error">{{ $message }}</div>@enderror
            </section>

            <section class="settings-section layout-settings" aria-labelledby="layout-heading">
                <div class="settings-section-heading">
                    <h2 id="layout-heading">Layout Preferences</h2>
                </div>
                <div class="settings-preference-grid">
                    <div class="settings-field">
                        <label for="appearance-density" class="settings-label">Page spacing</label>
                        <select id="appearance-density" name="appearance_density" required>
                            @foreach($appearanceOptions['density'] as $value => $label)
                                <option value="{{ $value }}" @selected($selectedDensity === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('appearance_density')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            <div class="appearance-actions">
                <button type="button" class="btn btn-secondary" id="appearance-reset">Reset to Defaults</button>
            </div>

            <div class="appearance-dirty-bar" id="appearance-dirty-bar" role="status" aria-live="polite" hidden>
                <span>Unsaved appearance changes</span>
                <div class="appearance-dirty-actions">
                    <button type="button" class="btn btn-secondary" id="appearance-discard">Discard Changes</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const root = document.documentElement;
            const form = document.getElementById('appearance-settings-form');
            const dirtyBar = document.getElementById('appearance-dirty-bar');
            const saved = @json($saved);
            const defaults = @json($defaults);
            const appearanceKeys = ['theme', 'accent', 'density', 'gradients'];

            function readForm() {
                return {
                    theme: form.querySelector('[name="appearance_theme"]:checked').value,
                    accent: form.querySelector('[name="appearance_accent"]:checked').value,
                    density: document.getElementById('appearance-density').value,
                    gradients: document.getElementById('appearance-gradients').checked,
                };
            }

            function applyPreview(values) {
                root.dataset.theme = values.theme;
                root.dataset.accent = values.accent;
                root.dataset.density = values.density;
                root.dataset.gradients = values.gradients ? 'on' : 'off';
            }

            function writeForm(values) {
                form.querySelector(`[name="appearance_theme"][value="${values.theme}"]`).checked = true;
                form.querySelector(`[name="appearance_accent"][value="${values.accent}"]`).checked = true;
                document.getElementById('appearance-density').value = values.density;
                document.getElementById('appearance-gradients').checked = values.gradients;
            }

            function refresh() {
                const current = readForm();
                applyPreview(current);
                dirtyBar.hidden = appearanceKeys.every((key) => saved[key] === current[key]);
            }

            form.addEventListener('input', refresh);
            form.addEventListener('change', refresh);
            document.getElementById('appearance-discard').addEventListener('click', () => {
                writeForm(saved);
                refresh();
            });
            document.getElementById('appearance-reset').addEventListener('click', () => {
                writeForm(defaults);
                refresh();
            });

            refresh();
        })();
    </script>
@endsection
