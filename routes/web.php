<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;

Route::resource('tickets', TicketController::class);
