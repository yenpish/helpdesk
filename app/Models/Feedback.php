<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = [
        'event_id',
        'participant_name',
        'participant_email',
        'rating',
        'comments',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}