<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShetabVerify extends Model
{
    use HasFactory;
    protected $table = 'shetab_verifies';
    protected $fillable = [
        'user_id',
        'product_category_id',
        'amount',
        'base_amount',
        'tracking_code',
        'status',
    ];

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
