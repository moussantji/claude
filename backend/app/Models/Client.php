<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'name', 'contact', 'email', 'phone', 'city',
        'sector', 'value', 'status', 'since',
    ];

    protected $casts = [
        'value' => 'integer',
        'since' => 'date:Y-m-d',
    ];
}
