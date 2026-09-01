<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingCampaign extends Model
{
    protected $fillable = [
        'name',
        'segment_type',
        'segment_params',
        'message',
        'image_path',
        'cta_type',
        'cta_payload',
        'scheduled_at',
        'status',
        'total_users',
        'sent_users',
        'recipient_ids',
        'sent_ids',
        'failed_ids',
    ];

    protected $casts = [
        'segment_params' => 'array',
        'scheduled_at' => 'datetime',
        'recipient_ids' => 'array',
        'sent_ids' => 'array',
        'failed_ids' => 'array',
    ];
}
