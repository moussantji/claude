<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Series extends Model
{
    use HasFactory;

    // "series" is uncountable, so set the table explicitly.
    protected $table = 'series';

    protected $fillable = [
        'title', 'slug', 'description', 'poster_url', 'backdrop_url',
        'genre', 'start_year', 'end_year', 'seasons', 'rating',
        'is_featured', 'is_active',
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

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)
            ->orderBy('season')
            ->orderBy('episode_number');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
