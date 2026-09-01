<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseIntent extends Model
{
    protected $fillable = [
        'account_id',
        'product_category_id',
        'product_id',
        'stage',
        'reminder_count',
        'last_reminder_at',
        'completed_at',
    ];

    protected $casts = [
        'last_reminder_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
