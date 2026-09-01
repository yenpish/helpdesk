<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Ticket;
use App\Http\Requests\StoreCommentRequest;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Ticket $ticket)
    {
        Comment::create([
            'body' => $request->validated('body'),
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
        ]);

        return redirect("/tickets/{$ticket->id}");
    }
}
