
@extends('layouts.app')

@section('section', 'Event Feedback Results')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Feedback Results</h2>
            <h5>{{ $event->name }}</h5>
        </div>

        <a href="{{ route('events.show', $event) }}"
           class="btn btn-secondary">
            Back to Event
        </a>
    </div>

    {{-- Summary --}}
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-muted">Average Rating</h6>
                    <h2>
                        {{ $averageRating !== null
                            ? number_format($averageRating, 1) . ' / 5'
                            : 'No ratings yet' }}
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-muted">Total Responses</h6>
                    <h2>{{ $totalFeedback }}</h2>
                </div>
            </div>
        </div>
    </div>

    {{-- Rating Breakdown --}}
    <div class="card mb-4">
        <div class="card-header">
            <strong>Rating Breakdown</strong>
        </div>

        <div class="card-body">
            @for ($rating = 5; $rating >= 1; $rating--)
                <div class="d-flex justify-content-between mb-2">
                    <span>
                        {{ $rating }} {{ $rating === 1 ? 'star' : 'stars' }}
                    </span>
                    <strong>
                        {{ $ratingCounts->get($rating, 0) }}
                    </strong>
                </div>
            @endfor
        </div>
    </div>

    {{-- Feedback List --}}
    <div class="card">
        <div class="card-header">
            <strong>Participant Feedback</strong>
        </div>

        <div class="card-body">
            @if ($feedback->isEmpty())
                <p class="text-muted mb-0">
                    No feedback has been submitted for this event yet.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Participant</th>
                                <th>Rating</th>
                                <th>Comments</th>
                                <th>Submitted At</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($feedback as $item)
                                <tr>
                                    <td>
                                        <strong>
                                            {{ $item->participant_name }}
                                        </strong>
                                        <br>
                                        <small class="text-muted">
                                            {{ $item->participant_email }}
                                        </small>
                                    </td>

                                    <td>
                                        {{ $item->rating }} / 5
                                    </td>

                                    <td>
                                        {{ $item->comments ?: 'No comments' }}
                                    </td>

                                    <td>
                                        {{ $item->created_at?->format('d M Y, h:i A') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $feedback->links() }}
            @endif
        </div>
    </div>
</div>
@endsection
