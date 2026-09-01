<h1>Tickets</h1>

<form method="POST" action="/logout">
    @csrf
    <button type="submit">Logout</button>
</form>

@foreach($tickets as $ticket)
    <h2>{{ $ticket->title }}</h2>

    <p>
        {{ $ticket->description }}
    </p>

    <p>
        <b>Category:</b> {{ $ticket->category->name }}
    </p>

    <p>
        <b>Created by:</b> {{ $ticket->user->name }} (Id: {{ $ticket->id }})
    </p>

    <p>
        Status: {{ $ticket->status }}
    </p>

    <p>
        Priority: {{ $ticket->priority }}
    </p>

    <hr>
@endforeach
