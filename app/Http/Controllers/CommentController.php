<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        Comment::create([
            'body' => $request->body,
            'ticket_id' => $ticket->id,
        ]);

        return redirect("/tickets/{$ticket->id}");
    }
}
