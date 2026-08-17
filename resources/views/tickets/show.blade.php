<h1>{{ $ticket->title }}</h1>

<p>
    {{ $ticket->description }}
</p>

<p>
    Category:
    {{ $ticket->category->name }}
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

        <option value="Pending">
            Pending
        </option>

        <option value="In Progress">
            In Progress
        </option>

        <option value="Resolved">
            Resolved
        </option>

        <option value="Closed">
            Closed
        </option>

    </select>

    <button>
        Update Status
    </button>

</form>


<h2>Comments</h2>

<h2>Add Comment</h2>

<form method="POST" action="/tickets/{{ $ticket->id }}/comments">

    @csrf

    <textarea name="body"></textarea>

    <button>
        Add Comment
    </button>

</form>

@foreach($ticket->comments as $comment)

    <p>
        {{ $comment->body }}
    </p>

@endforeach
