<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventNotificationDelivery extends Model
{
    protected $fillable = [
        'event_id', 'registration_id', 'type', 'notification_key',
        'recipient_email', 'status', 'attempt_count', 'last_error', 'last_attempted_at',
    ];

    protected $casts = ['last_attempted_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
