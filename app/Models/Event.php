<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Models\Concerns\HasEncryptedRouteKey;

class Event extends Model
{
    use HasEncryptedRouteKey, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'starts_at',
        'ends_at',
        'event_type_id',
        'location_id',
        'organizer_id',
        'created_by',
        'updated_by',
        'status',
        'pin',
        'reminder_24h',
        'reminder_1h',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'reminder_24h' => 'boolean',
        'reminder_1h' => 'boolean',
    ];

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function attendances(): HasManyThrough
    {
        return $this->hasManyThrough(
            Attendance::class,
            EventSession::class,
            'event_id',
            'session_id',
            'id',
            'id'
        );
    }

    public function feedbackInvitations(): HasMany
    {
        return $this->hasMany(FeedbackInvitation::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(EventNotificationDelivery::class);
    }
}
