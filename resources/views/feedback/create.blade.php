@extends('layouts.app')

@section('section', 'Event Feedback')

@section('content')
    <div class="container">
        <div class="feedback-content">

            <h2>Event Feedback</h2>
            <h4>{{ $event->name }}</h4>

            <p>
                Hello, {{ $invitation->participant_name }}.
                Thank you for attending this event!
            </p>

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST"
                  action="{{ route('feedback.invitation.store', [
                  'event' => $event,
                  'token' => $token,
              ]) }}">
                @csrf

                <div class="mb-3">
                    <label for="rating" class="form-label">Rating</label>
                    <select name="rating" id="rating" class="form-select" required>
                        <option value="">Select rating</option>
                        @for ($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}" @selected(old('rating') == $i)>
                                {{ $i }} / 5
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="mb-3">
                    <label for="comments" class="form-label">Comments (optional)</label>
                    <textarea name="comments" id="comments"
                              class="form-control" rows="4"
                              maxlength="2000">{{ old('comments') }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    Submit Feedback
                </button>
            </form>

        </div>
    </div>
@endsection
