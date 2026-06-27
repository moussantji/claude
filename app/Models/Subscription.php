<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'status',
        'payment_method',
        'payment_reference',
        'payment_token',
        'amount_paid',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    /**
     * Activate this subscription, stacking onto the end date of any other
     * currently-active subscription the user holds.
     */
    public function activate(): void
    {
        $this->loadMissing('package', 'user');

        $current = $this->user->subscriptions()
            ->where('id', '!=', $this->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();

        $start = $current && $current->ends_at?->isFuture()
            ? $current->ends_at->copy()
            : now();

        $this->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $start->addDays($this->package->duration_days),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('ends_at', '>', now());
    }
}
