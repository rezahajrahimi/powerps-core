<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    public const TYPE_EARN = 'earn';

    public const TYPE_REDEEM = 'redeem';

    public const TYPE_ADMIN = 'admin_adjust';

    public const TYPE_REFUND = 'refund';

    protected $guarded = ['id'];

    protected $casts = [
        'points' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'purchase' => 'خرید',
            'renewal' => 'تمدید',
            'deposit' => 'واریز',
            'referral_signup' => 'معرفی',
            'checkout' => 'استفاده در خرید',
            'admin' => 'تغییر مدیر',
            default => $this->event ?? 'امتیاز',
        };
    }
}
