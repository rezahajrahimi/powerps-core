<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountBallance extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['account_id', 'ballance'];
    public function user()
    {
        return $this->hasOne(BotUser::class, 'account_id', 'account_id');
    }
}
