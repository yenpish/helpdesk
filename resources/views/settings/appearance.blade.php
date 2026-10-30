@extends('layouts.app')

@section('section', 'Appearance')
@section('title', 'Appearance - Attendance Management')

@section('content')
    <div class="content-panel appearance-page">
        <div class="page-header">
            <div class="page-heading">
                <h1>Appearance</h1>
                <p>Choose a theme for your account.</p>
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.appearance.update') }}">
            @csrf
            @method('PUT')

            <fieldset class="theme-options" @if($errors->has('appearance_theme')) aria-describedby="appearance-theme-error" @endif>
                <legend class="visually-hidden">Choose a theme</legend>
                <div class="theme-grid" role="radiogroup" aria-label="Appearance theme">
                    @foreach($themes as $theme)
                        <label class="theme-choice" for="theme-{{ $theme }}">
                            <input
                                class="theme-choice-input"
                                type="radio"
                                id="theme-{{ $theme }}"
                                name="appearance_theme"
                                value="{{ $theme }}"
                                @checked(old('appearance_theme', auth()->user()->appearance_theme ?? 'light') === $theme)
                                required
                            >
                            <span class="theme-choice-card" data-preview-theme="{{ $theme }}">
                                <span class="theme-preview" aria-hidden="true">
                                    <span class="theme-preview-nav"></span>
                                    <span class="theme-preview-main">
                                        <span class="theme-preview-panel"></span>
                                        <span class="theme-preview-accent"></span>
                                    </span>
                                </span>
                                <span class="theme-choice-name">{{ ucfirst($theme) }}</span>
                                <span class="theme-choice-selected" aria-hidden="true">Selected</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('appearance_theme')
                    <div class="field-error" id="appearance-theme-error">{{ $message }}</div>
                @enderror
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Theme</button>
            </div>
        </form>
    </div>
    <script>
        document.querySelectorAll('input[name="appearance_theme"]').forEach((input) => {
            input.addEventListener('change', () => {
                if (input.checked) document.documentElement.dataset.theme = input.value;
            });
        });
    </script>
@endsection
