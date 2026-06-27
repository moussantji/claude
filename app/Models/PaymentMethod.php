<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'account_number', 'instructions', 'qr_path', 'color', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Public URL of the admin-uploaded QR code, if any.
     */
    public function qrUrl(): ?string
    {
        if (! $this->qr_path) {
            return null;
        }

        return Storage::disk('public')->url($this->qr_path);
    }
}
