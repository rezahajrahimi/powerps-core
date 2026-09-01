<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupOperationJob extends Model
{
    protected $fillable = [
        'action',
        'panel_id',
        'status',
        'total_configs',
        'processed_configs',
        'success_items',
        'failed_items',
        'error_message',
    ];

    protected $casts = [
        'success_items' => 'array',
        'failed_items' => 'array',
    ];

    public static function actionLabels(): array
    {
        return [
            'inc_days' => 'افزایش روز',
            'dec_days' => 'کاهش روز',
            'modify_days' => 'تغییر روز',
            'inc_vol' => 'افزایش حجم',
            'dec_vol' => 'کاهش حجم',
            'modify_vol' => 'تغییر حجم',
            'reset' => 'ریست مصرف',
            'active' => 'فعالسازی',
            'deactive' => 'غیرفعالسازی',
            'delete' => 'حذف',
            'delete_expired' => 'حذف اکانت‌های منقضی',
        ];
    }

    public function actionLabel(): string
    {
        return self::actionLabels()[$this->action] ?? $this->action;
    }
}
