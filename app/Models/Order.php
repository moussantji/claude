<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'processing', 'completed', 'rejected'];

    protected $fillable = [
        'reference', 'user_id', 'package_id', 'package_name',
        'payment_method_id', 'payment_method_name', 'payment_number',
        'amount', 'currency', 'status',
        'iptv_username', 'iptv_password', 'iptv_dns', 'admin_note',
        'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->reference ??= 'CMD-' . now()->format('ymd') . '-' . Str::upper(Str::random(5));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function hasCredentials(): bool
    {
        return filled($this->iptv_username) && filled($this->iptv_password) && filled($this->iptv_dns);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'completed' => 'bg-emerald-500/20 text-emerald-300',
            'processing' => 'bg-sky-500/20 text-sky-300',
            'rejected' => 'bg-rose-500/20 text-rose-300',
            default => 'bg-amber-500/20 text-amber-300',
        };
    }
}
