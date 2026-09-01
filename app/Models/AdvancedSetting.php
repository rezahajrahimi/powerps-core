<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvancedSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_show_configs_by_panels_category',
        'bot_auto_set_price_by_dollar_price',
        'bot_show_web_app_link_in_telegram_for_all_users',
        'bot_show_one_row_config',
    ];
}
