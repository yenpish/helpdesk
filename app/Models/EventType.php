<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasEncryptedRouteKey;

class EventType extends Model
{
    use HasEncryptedRouteKey;

    protected $fillable = [
        'name',
        'description',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
