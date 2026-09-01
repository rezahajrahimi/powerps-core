<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = [
        'bot_name',
        'admin_id',
        'bot_token',
        'panel_address',
        'welcome_message',
        'config_name_prefix',
        'config_name_format',
        'use_admin_alias_in_config_name',
    ];

    protected $casts = [
        'use_admin_alias_in_config_name' => 'boolean',
    ];
}
