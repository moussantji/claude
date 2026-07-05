<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'ref', 'customer', 'type', 'item', 'amount', 'status', 'city', 'date',
    ];

    protected $casts = [
        'amount' => 'integer',
        'date' => 'date:Y-m-d',
    ];
}
