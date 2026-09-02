<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'position',
        'unit',
        'phone',
        'email',
        'signature',
        'clock_in_at',
        'clock_out_at',
        'latitude',
        'longitude',
        'accuracy',
        'verification_method',
        'location_id',
        'distance_from_site',
        'within_site',
        'attendance_event_id',
    ];

    protected $casts = [
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy' => 'decimal:2',
        'distance_from_site' => 'decimal:2',
        'within_site' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function attendanceEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class);
    }
}
