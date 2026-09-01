<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCardMenuItem extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'alias_name', 'level'];

}
