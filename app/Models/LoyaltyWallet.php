<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyWallet extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'balance' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
