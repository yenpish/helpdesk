@extends('layouts.app')

@section('section', 'Settings')
@section('title', 'Appearance Settings - Attendance Management')

@section('content')
    <div class="content-panel">
        <h1>Appearance</h1>
        <p class="subtitle">Choose how the application looks for your account.</p>

        @if(session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.appearance.update') }}">
            @csrf
            @method('PUT')

            <div class="field mb-3">
                <label for="appearance_theme">Theme</label>
                <select id="appearance_theme" name="appearance_theme" required>
                    <option value="light" @selected(old('appearance_theme', auth()->user()->appearance_theme ?? 'light') === 'light')>Light</option>
                    <option value="dark" @selected(old('appearance_theme', auth()->user()->appearance_theme ?? 'light') === 'dark')>Dark</option>
                </select>
                @error('appearance_theme')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field mb-3">
                <label for="appearance_accent">Accent colour</label>
                <select id="appearance_accent" name="appearance_accent" required>
                    @foreach($accents as $value => $label)
                        <option value="{{ $value }}" @selected(old('appearance_accent', auth()->user()->appearance_accent ?? 'blue') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('appearance_accent')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save appearance</button>
            </div>
        </form>
    </div>
@endsection
