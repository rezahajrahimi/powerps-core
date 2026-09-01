<?php
namespace App\Http\Controllers;

use App\Http\Controllers\HiddifyPannelController;
use App\Models\PaymentSetting;

class PaymentSettingController extends Controller
{
    public function __construct()
    {
        $this->hiddifyCtrl = new HiddifyPannelController();
    }
    public function seed()
    {
        try {
            // // check run on local
            // if (env('APP_ENV') != 'local') {
            //     \Log::info('PaymentSetting table seeding failed because run on local');
            //     return false;
            // }
            // truncate table
            PaymentSetting::truncate();
            // insert data
            $data = [

                [
                    'key'         => 'shetab_verify',
                    'value'       => $this->hiddifyCtrl->generateUUID(),
                    'description' => "6104333333333333",
                    'status'      => true,
                ],
                [
                    'key'         => 'usd_transaction',
                    'value'       => '0',
                    'description' => '0',
                    'status'      => true,
                ],

            ];
            PaymentSetting::insert($data);
            \Log::info('PaymentSetting table seeded successfully');
            return true;
        } catch (\Throwable $th) {
            \Log::info("PaymentSetting table seeding failed: $th");
            return false;
        }
    }
    public function reGenerateShetabVerify()
    {
        $shetabVerify = $this->getPaymentSettingValueByKey('shetab_verify');
        if ($shetabVerify) {
            $this->setPaymentSettingValueByKey('shetab_verify', $this->hiddifyCtrl->generateUUID());
            return $this->getPaymentSettingValueByKey('shetab_verify');
        }
        return response()->json(['message' => 'Shetab verify not found'], 500);
    }
    public function getPaymentSettingByKey($key)
    {
        $paymentSetting = new PaymentSetting();
        $data = $paymentSetting->getPaymentSettingByKey($key);
        if (!$data) {
            $this->seed();
            $data = $paymentSetting->getPaymentSettingByKey($key);
        }
        return $data;
    }
    public function getPaymentSettingValueByKey($key)
    {
        $paymentSetting = new PaymentSetting();
        return $paymentSetting->getPaymentSettingValueByKey($key);
    }
    public function getPaymentSettingDescriptionByKey($key)
    {
        $paymentSetting = new PaymentSetting();
        $paymentSetting = $paymentSetting->getPaymentSettingDescriptionByKey($key);

        if (is_array($paymentSetting)) {
            // use format text service
            $paymentSetting = $this->telegramService->formatText($paymentSetting);
        }
        return $paymentSetting;
    }
    public function reverseStatusByKey($key)
    {
        $paymentSetting = new PaymentSetting();
        return $paymentSetting->reverseStatusByKey($key);
    }
    public function setPaymentSettingValueByKey($key, $value)
    {
        $paymentSetting = new PaymentSetting();
        return $paymentSetting->setPaymentSettingValueByKey($key, $value);
    }
    public function setPaymentSettingDescriptionByKey($key, $description)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        $paymentSetting->description = $description;
        $paymentSetting->save();
        return $paymentSetting;
    }
    public function setPaymentSettingStatusByKey($key, $status)
    {
        try {
            $parsedStatus = filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsedStatus === null) {
                $parsedStatus = $status === 1 || $status === '1';
            }

            $paymentSetting = $this->getPaymentSettingByKey($key);
            $paymentSetting->status = $parsedStatus;
            $paymentSetting->save();

            return $paymentSetting;
        } catch (\Throwable $th) {
            \Log::info("PaymentSetting table seeding failed: $th");
            return false;
        }
    }
    public function getPaymentSettingStatusByKey($key)
    {
        $paymentSetting = $this->getPaymentSettingByKey($key);
        if (!$paymentSetting) {
            $this->seed();
            $paymentSetting = $this->getPaymentSettingByKey($key);
        }

        return (bool) ($paymentSetting->status ?? false);
    }

}
