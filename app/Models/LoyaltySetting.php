<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltySetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'earn_on_purchase' => 'boolean',
        'earn_on_renewal' => 'boolean',
        'earn_on_deposit' => 'boolean',
        'earn_on_referral' => 'boolean',
        'redeem_enabled' => 'boolean',
    ];
}
