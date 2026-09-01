<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuLevel extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['account_id', 'level'];

}
