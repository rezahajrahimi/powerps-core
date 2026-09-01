<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelLock extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['channel_id', 'is_active'];

}
