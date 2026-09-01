<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionCrypto extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = [
        'account_id',
        'username',
        'crypto_payment_id',
        'amount_dollar',
        'confirmed',
        'recipe_number',
        'order_id',
        'gateway',
        'status',
        'payment_id',
        'payment_url',
        'callback_data',
        'currency',
    ];
    public function getCreatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }

    public function Crypto_payment()
    {
        return $this->belongsTo(CryptoPayment::class, 'crypto_payment_id');
    }

    public function user()
    {
        return $this->belongsTo(BotUser::class, 'account_id', 'account_id');
    }
}
