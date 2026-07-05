<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'author',
        'date', 'status', 'excerpt', 'content',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];
}
