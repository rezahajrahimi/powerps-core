<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGroupVerificationPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = ['user_group_id', 'is_verified', 'payment_key', 'is_enabled'];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public function userGroup()
    {
        return $this->belongsTo(UserGroup::class);
    }
}
