<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    use HasFactory;

    public const PAYMENT_KEYS = [
        'zarinpal',
        'offline',
        'nowpayments',
        'cryptomus',
        'swappay',
        'usd_transaction',
    ];

    public const PAYMENT_KEY_LABELS = [
        'zarinpal' => 'زرین‌پال',
        'offline' => 'پرداخت آفلاین (کارت به کارت و ...)',
        'nowpayments' => 'NOWPayments',
        'cryptomus' => 'Cryptomus',
        'swappay' => 'SwapPay (سواپ‌ولت)',
        'usd_transaction' => 'پرداخت دلاری / ارزی',
    ];

    protected $fillable = ['name', 'role_type', 'is_default'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function paymentMethods()
    {
        return $this->hasMany(UserGroupPaymentMethod::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function verificationPaymentMethods()
    {
        return $this->hasMany(UserGroupVerificationPaymentMethod::class);
    }
}
