<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'poster_url', 'backdrop_url',
        'genre', 'year', 'duration_minutes', 'rating', 'quality',
        'stream_url', 'trailer_url', 'is_featured', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
