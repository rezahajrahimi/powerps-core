<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebAppMenuItem extends Model
{
    use HasFactory;
    protected $fillable = ['key','title','subtitle','is_active','position'];

}
