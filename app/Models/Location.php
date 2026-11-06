<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasEncryptedRouteKey;

class Location extends Model
{
    use HasEncryptedRouteKey;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'allowed_radius',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'allowed_radius' => 'decimal:2',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
