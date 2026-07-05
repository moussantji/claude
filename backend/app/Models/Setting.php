<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company', 'email', 'phone', 'address', 'currency', 'slogan',
    ];
}
