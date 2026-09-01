<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['service_name'];


        public function product_category(): HasMany
        {
            return $this->hasMany(ProductCategory::class, 'service_types_id');
        }


}
