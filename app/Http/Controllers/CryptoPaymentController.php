<?php

namespace App\Http\Controllers;

use App\Models\CryptoPayment;
use Illuminate\Http\Request;

class CryptoPaymentController extends Controller
{
    public function seed()
    {
        if (CryptoPayment::where('name', 'nowpayments')->doesntExist()) {
            $this->createNowPaymentData();
        }
        if (CryptoPayment::where('name', 'cryptomus')->doesntExist()) {
            $this->createCryptoPaymentData();
        }
        if (CryptoPayment::where('name', 'swappay')->doesntExist()) {
            $this->createSwapPayData();
        }

        return true;
    }
    public function getCryptoPaymentStatusByKey($key)
    {
        $data = CryptoPayment::where('name', $key)->first();
        if ($data != null) {
            return $data->is_active == true || $data->is_active == 1 ? true : false;
        }
        return false;
    }
    public function createNowPaymentData()
    {
        try {
            $transactionCryptoCntrl = new TransactionCryptoController();
            $data = new CryptoPayment();
            $data->name = 'nowpayments';
            $data->api_key = 'xxxxxxx-xxxxxxx-xxxxxxx-xxxxxxx';
            $data->env = 'live';
            $data->callback_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->email = 'john@gmail.com';
            $data->password = '123456789';
            $data->ipn_callback_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->success_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->cancel_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/cancelpay/";
            $data->partially_paid_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->is_fixed_rate = true;
            $data->is_fee_paid_by_user = true;
            $data->is_active = true;

            $data->save();
            return $data;
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return null;
        }
    }
    public function getNowPaymentID()
    {
        try {
            $data = CryptoPayment::where('name', 'nowpayments')->first();
            if ($data != null) {
                return $data->id;
            }
            return null;
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return null;
        }
    }
    public function getNovPaymentData()
    {
        try {
            $data = CryptoPayment::where('name', 'nowpayments')->first();
            if ($data != null) {
                return $data;
            }
            return $this->createNowPaymentData();
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return null;
        }
    }
    public function updateNowPayment(Request $request)
    {
        try {
            \Log::info('request->is_active : ' . $request->is_active);
            $data = CryptoPayment::where('name', 'nowpayments')->first();

            $data->api_key = $request->api_key;
            $data->email = $request->email;
            $data->password = $request->password;
            $data->is_fee_paid_by_user = $request->is_fee_paid_by_user == 1 || $request->is_fee_paid_by_user == true ? true : false;
            $data->is_active = $request->is_active == 1 || $request->is_active == true ? true : false;

            $data->update();
            return $data;
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return response()->json(null, 500);
        }
    }
    public function getNowPaymentsStatus()
    {
        try {
            $data = CryptoPayment::where('name', 'nowpayments')->first();
            $sataus = $data->is_active == true || $data->is_active == 1 ? true : false;
            return $sataus;
        } catch (\Throwable $th) {
            //throw $th;
            \Log::info('message : ' . $th->getMessage());
            return false;
        }
    }
    public function getCryptoPaymentData()
    {
        $data = CryptoPayment::where('name', 'cryptomus')->first();
        if ($data != null) {
            return $data;
        }
        return $this->createCryptoPaymentData();

    }
    public function createCryptoPaymentData()
    {
        try {
            $transactionCryptoCntrl = new TransactionCryptoController();
            $data = new CryptoPayment();
            $data->name = 'cryptomus';
            $data->api_key = 'xxxxxxx-xxxxxxx-xxxxxxx-xxxxxxx';
            $data->env = 'live';
            $data->callback_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->email = 'john@gmail.com';
            $data->password = 'xxxxxxx-xxxxxxx-xxxxxxx-xxxxxxx';
            $data->ipn_callback_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/payback/";
            $data->save();
            return $data;
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return null;
        }
    }
    public function getCryptoPaymentID()
    {
        $data = CryptoPayment::where('name', 'cryptomus')->first();
        if ($data != null) {
            return $data->id;
        }
        return null;
    }
    public function updateCryptomusPayment(Request $request)
    {
        \Log::info('request->is_active : ' . $request->is_active);
        try {
            $data = CryptoPayment::where('name', 'cryptomus')->first();
            $data->api_key = $request->api_key;
            $data->email = $request->email;
            $data->password = $request->password;
            $data->is_active = $request->is_active == true || $request->is_active == 1 ? true : false;
            $data->update();
            return $data;
        } catch (\Throwable $th) {
            \Log::info('message : ' . $th->getMessage());
            return response()->json(null, 500);
        }
    }

    public function createSwapPayData()
    {
        try {
            $transactionCryptoCntrl = new TransactionCryptoController();
            $data = new CryptoPayment();
            $data->name = 'swappay';
            $data->api_key = 'xxxxxxx-xxxxxxx-xxxxxxx-xxxxxxx';
            $data->env = 'live';
            $data->callback_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/swappay/return";
            $data->email = '';
            $data->password = 'your-application-username';
            $data->ipn_callback_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/swappay/return";
            $data->success_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/swappay/return";
            $data->cancel_url = "https://{$transactionCryptoCntrl->getCurrentUrl()}/cancelpay/";
            $data->is_active = false;
            $data->save();

            return $data;
        } catch (\Throwable $th) {
            \Log::info('createSwapPayData: ' . $th->getMessage());

            return null;
        }
    }

    public function getSwapPayData()
    {
        $data = CryptoPayment::where('name', 'swappay')->first();
        if ($data != null) {
            return $data;
        }

        return $this->createSwapPayData();
    }

    public function updateSwapPayPayment(Request $request)
    {
        try {
            $data = CryptoPayment::where('name', 'swappay')->first();
            if ($data == null) {
                $data = $this->createSwapPayData();
            }
            if ($data == null) {
                return response()->json(null, 500);
            }

            $data->api_key = $request->api_key;
            $data->password = $request->password; // application username
            if ($request->has('email')) {
                $data->email = $request->email;
            }
            $data->is_active = $request->is_active == true || $request->is_active == 1 ? true : false;

            $service = new \App\Services\SwapPayService($data->api_key, $data->password);
            $check = $service->validateCredentials();
            if (! ($check['ok'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => $check['message'] ?? 'تنظیمات SwapPay نامعتبر است.',
                ], 422);
            }

            $data->update();

            return $data;
        } catch (\Throwable $th) {
            \Log::info('updateSwapPayPayment: ' . $th->getMessage());

            return response()->json(null, 500);
        }
    }
}
