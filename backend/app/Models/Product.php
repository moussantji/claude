<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'category', 'price', 'cycle', 'subscribers', 'status',
    ];

    protected $casts = [
        'price' => 'integer',
        'subscribers' => 'integer',
    ];
}
