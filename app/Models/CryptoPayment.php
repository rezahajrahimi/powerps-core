<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CryptoPayment extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'api_key', 'env', 'callback_url', 'email', 'password', 'ipn_callback_url', 'success_url', 'cancel_url', 'partially_paid_url', 'is_fixed_rate', 'is_fee_paid_by_user','is_active'];
    public function crypto_transactions()
    {
        return $this->hasMany(TransactionCrypto::class, 'crypto_payment_id');
    }

}
