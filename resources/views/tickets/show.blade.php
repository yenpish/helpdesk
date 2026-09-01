<h1>{{ $ticket->title }}</h1>

<p>
    {{ $ticket->description }}
</p>

<p>
    Category:
    {{ $ticket->category->name }}
</p>

<p>
    Created by:
    {{ $ticket->user->name }}
</p>

<p>
    Status:
    {{ $ticket->status }}
</p>

<p>
    Priority:
    {{ $ticket->priority }}
</p>

<h2>Update Status</h2>

<form method="POST" action="/tickets/{{ $ticket->id }}">

    @csrf
    @method('PATCH')

    <select name="status">

        <option value="Pending" @selected($ticket->status === 'Pending')>
            Pending
        </option>

        <option value="In Progress" @selected($ticket->status === 'In Progress')>
            In Progress
        </option>

        <option value="Resolved" @selected($ticket->status === 'Resolved')>
            Resolved
        </option>

        <option value="Closed" @selected($ticket->status === 'Closed')>
            Closed
        </option>

    </select>

    <button>
        Update Status
    </button>

</form>

<h2>Assign Technician</h2>

<form method="POST" action="/tickets/{{ $ticket->id }}/assignment">

    @csrf
    @method('PATCH')

    <select name="assigned_to">

        <option value="">Unassigned</option>

        @foreach($technicians as $technician)
            <option
                value="{{ $technician->id }}"
                @selected($ticket->assigned_to == $technician->id)
            >
                {{ $technician->name }}
            </option>
        @endforeach

    </select>

    <button>
        Assign
    </button>

</form>

<h2>Comments</h2>

<h2>Add Comment</h2>

@if ($errors->any())
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="/tickets/{{ $ticket->id }}/comments">

    @csrf

    <textarea name="body"></textarea>

    <button>
        Add Comment
    </button>

</form>

@foreach($ticket->comments as $comment)

    <p>
        <b>{{ $comment->user?->name ?? 'Unknown' }}</b>
    </p>

    <p>
        {{ $comment->body }}
    </p>

    <small>
        {{ $comment->created_at->format('d M Y, h:i A') }}
    </small>

@endforeach
