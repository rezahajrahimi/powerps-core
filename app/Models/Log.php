<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Verta;

class Log extends Model
{
    use HasFactory;
    protected $fillable = ['type', 'message','account_id','username','event'];
    public function getCreatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }
    public function getUpdatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }
    public function user()
    {
        return $this->hasOne(BotUser::class, 'account_id', 'account_id');
    }
}
