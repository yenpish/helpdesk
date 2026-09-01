<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\Request;
use App\Models\User;
use App\models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\UpdateTicketRequest;

class TicketController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tickets = Ticket::with(['user','category'])->get();

        return view('tickets.index', [
            'tickets'=>$tickets
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::all();
        return view('tickets.create',[
            'categories'=>$categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTicketRequest $request)
    {

        Ticket::create([
            $request->validated(),
            'user_id' => $request->user()->id,
            ]);

        return redirect('/tickets');
    }

    /**
     * Display the specified resource.
     */
    public function show(Ticket $ticket)
    {
        $ticket->load(['category', 'user', 'comments.user']);

        $technicians = User::where('role', 'technician')->get();

        return view('tickets.show', [
            'ticket' => $ticket,
            'technicians' => $technicians,
        ]);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ticket $ticket)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket)
    {
        Gate::authorize('update', $ticket);
        $ticket->update($request->validated());

        return redirect("/tickets/{$ticket->id}");
    }

    public function assign(UpdateTicketRequest $request, Ticket $ticket)
    {
        Gate::authorize('assign', $ticket);

        $ticket->update([
            'assigned_to' => $request->validated('assigned_to'),
        ]);

        return redirect("/tickets/{$ticket->id}");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Ticket $ticket)
    {
        //
    }
}
