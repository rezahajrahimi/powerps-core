<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronLog extends Model
{
    use HasFactory;
    protected $fillable = ['cron_id','product_id'];


    public function cron_job()
    {
        return $this->belongsTo(CronJob::class, 'cron_id');
    }
    public function product()
    {
        return $this->belongsTo(ProductCategory::class, 'product_id');
    }

}
