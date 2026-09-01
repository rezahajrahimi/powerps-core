<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentPermisson extends Model
{
    use HasFactory;
    protected $fillable = [ 'user_id', 'minus_ballance', 'minus_ballance_limit', 'create_products', 'delete_products','traffic_limitation_tb','product_limitation', 'product_count_baseline', 'traffic_tb_baseline'];
    public function user()
    {
        return $this->belongsTo(Pannel::class, 'user_id');

    }

}
