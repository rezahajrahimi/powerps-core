<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'max_uses',
        'used_count',
        'max_uses_per_user',
        'starts_at',
        'expires_at',
        'min_order_amount',
        'allowed_category_ids',
        'allowed_user_group_ids',
        'is_active',
    ];

    protected $casts = [
        'value' => 'float',
        'min_order_amount' => 'float',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'allowed_category_ids' => 'array',
        'allowed_user_group_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    public function normalizedCode(): string
    {
        return strtoupper(trim($this->code));
    }
}
