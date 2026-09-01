<?php

namespace App\Models;
use Verta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'referral_user_id',
        'amount',
    ];

    // public function getAmountAttribute($value)
    // {
    //     return $value / 100;
    // }

    public function getCreatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }

    public function getUpdatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }

    public function referral_user()
    {
        return $this->belongsTo(User::class, 'referral_user_id');
    }
}
