<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentProduct extends Model
{
    use HasFactory;
    protected $fillable = ['product_categories_id', 'user_id', 'is_active', 'price', 'price_in_dollar'];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function product_categories()
    {
        return $this->belongsTo(ProductCategory::class, 'product_categories_id');
    }

}
