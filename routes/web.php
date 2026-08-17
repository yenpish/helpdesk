<?php

use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use function Pest\Laravel\post;

Route::resource('tickets', TicketController::class);

Route::post('tickets/{ticket}/comments', [CommentController::class, 'store']);
