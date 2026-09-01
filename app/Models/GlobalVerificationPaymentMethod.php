<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalVerificationPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = ['is_verified', 'payment_key', 'is_enabled'];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_enabled' => 'boolean',
    ];
}
