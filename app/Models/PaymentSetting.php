<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    use HasFactory;
    protected $fillable = ['key', 'value', 'description', 'status'];

    protected $casts = [
        'status' => 'boolean',
    ];
    public function getPaymentSettingByKey($key)
    {
        return PaymentSetting::where('key', $key)->first();
    }
    // check status by key
    public function checkStatusByKey($key)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);

        return (bool) ($paymentSetting->status ?? false);
    }
    public function getPaymentSettingValueByKey($key)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        return $paymentSetting->value;
    }
    public function getPaymentSettingDescriptionByKey($key)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        return $paymentSetting->description;
    }
    public function reverseStatusByKey($key)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        $paymentSetting->status = !$paymentSetting->status;
        $paymentSetting->save();
        return $paymentSetting;
    }


    public function getPaymentSettingStatusByKey($key)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        if ($paymentSetting) {
            return (bool) $paymentSetting->status;
        }

        return false;
    }
    public function setPaymentSettingValueByKey($key, $value)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        $paymentSetting->value = $value;
        $paymentSetting->save();
        return $paymentSetting;
    }
    
}
