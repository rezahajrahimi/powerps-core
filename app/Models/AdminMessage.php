<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'image_path',
        'type',
        'status',
        'total_users',
        'sent_users',
        'sent_ids',
        'failed_ids',
        'recipient_ids',
        'scheduled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_ids' => 'array',
        'failed_ids' => 'array',
        'recipient_ids' => 'array',
    ];
}
