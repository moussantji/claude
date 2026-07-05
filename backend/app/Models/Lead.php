<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'name', 'company', 'email', 'phone', 'city',
        'service', 'budget', 'status', 'source', 'note',
    ];

    protected $casts = [
        'budget' => 'integer',
    ];
}
