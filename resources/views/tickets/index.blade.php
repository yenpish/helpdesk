<h1>Tickets</h1>

@foreach($tickets as $ticket)
    <h2>{{ $ticket->title }}</h2>

    <p>
        {{ $ticket->description }}
    </p>

    <p>
        <b>Category:</b> {{ $ticket->category->name }}
    </p>

    <p>
        Status: {{ $ticket->status }}
    </p>

    <p>
        Priority: {{ $ticket->priority }}
    </p>

    <hr>
@endforeach
