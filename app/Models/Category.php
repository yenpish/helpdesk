<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\models\Ticket;

class Category extends Model
{
    protected $guarded = [];
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
