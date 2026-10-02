@extends('layouts.app')
@section('section', 'Registration')

@section('title', 'Upcoming Events')

@section('content')
    <div class="container public-events">
        <header class="page-header">
            <div class="page-heading">
                <h1>Upcoming events</h1>
        <p>Browse published events and register.</p>
            </div>
            <a class="btn btn-secondary" href="{{ route('attendance.pin') }}">Record attendance</a>
        </header>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif

        @forelse($events as $event)
            <article class="public-event-card">
                <div class="public-event-main">
                    @if($event->eventType)<span class="event-type-label">{{ $event->eventType->name }}</span>@endif
                    <h2>{{ $event->name }}</h2>
                    @if($event->description)<p>{{ \Illuminate\Support\Str::limit($event->description, 200) }}</p>@endif
                    <dl class="public-event-meta">
                        <div><dt>Date</dt><dd>{{ $event->starts_at?->format('d M Y, h:i A') ?? 'To be confirmed' }}@if($event->ends_at && $event->starts_at?->toDateString() !== $event->ends_at->toDateString()) <span>to {{ $event->ends_at->format('d M Y, h:i A') }}</span>@endif</dd></div>
                        <div><dt>Location</dt><dd>{{ $event->location?->name ?? 'To be confirmed' }}</dd></div>
                    </dl>
                </div>
                <div class="public-event-action">
                    <a href="{{ route('registrations.create', $event) }}" class="btn btn-primary">Register</a>
                </div>
            </article>
        @empty
            <div class="empty-state">
                <h2>No upcoming events</h2>
                <p>There are no published events open for registration right now.</p>
            </div>
        @endforelse

        @if($events->hasPages())<div class="pagination-wrap">{{ $events->links() }}</div>@endif
    </div>
@endsection
