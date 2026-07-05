<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name', 'client', 'type', 'budget',
        'status', 'progress', 'deadline', 'lead',
    ];

    protected $casts = [
        'budget' => 'integer',
        'progress' => 'integer',
        'deadline' => 'date:Y-m-d',
    ];
}
