<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReserverdConfig extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','product_id'];
    public function product()
    {
        return $this->belongsTo(ProductCategory::class, 'product_id');
    }
    public function user()
    {
        return $this->belongsTo(BotUser::class, 'user_id');
    }
}
