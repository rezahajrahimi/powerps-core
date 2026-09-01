<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'account_id', 'product_categories_id','product_id'];
    protected $fillable = ['account_id', 'product_categories_id', 'price','product_id','order_number'];
    public function product_category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_categories_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
