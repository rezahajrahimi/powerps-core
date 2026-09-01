<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronJob extends Model
{
    use HasFactory;
    protected $fillable = ['name','frequency','is_active','description'];

    public function cron_log()
    {
        return $this->hasMany(CronLog::class, 'cron_id', 'id');
    }
}
