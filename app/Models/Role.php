<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasEncryptedRouteKey;

class Role extends Model
{
    use HasEncryptedRouteKey;

    protected $fillable = ['slug', 'name', 'permissions', 'is_system'];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public static function availablePermissions(): array
    {
        return [
            'dashboard.view' => 'View the attendance dashboard',
            'events.manage' => 'Manage owned events, sessions, registrations, attendance, and feedback',
            'events.publish' => 'Publish owned events (requires event management)',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'slug');
    }
}
