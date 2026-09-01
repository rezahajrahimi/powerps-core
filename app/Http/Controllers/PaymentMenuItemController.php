<?php
namespace App\Http\Controllers;

use App\Models\PaymentMenuItem;
use Illuminate\Http\Request;

class PaymentMenuItemController extends Controller
{
    public function seed()
    {
        if (PaymentMenuItem::all()->isEmpty()) {
            $payment             = new PaymentMenuItem();
            $payment->name       = 'main';
            $payment->alias_name = 'گزینه پرداخت را انتخاب کنید.';
            $payment->level      = 1;
            $payment->save();
            $response             = new PaymentMenuItem();
            $response->name       = 'response';
            $response->alias_name = 'لطفا مبلغ را به شماره زیر واریز کنید و تصویر رسید را در ربات ارسال کنید.';
            $response->level      = 2;
            $response->save();

            return true;
        }
        return false;
    }
    public function getPaymentTypeMainMenuTitle()
    {
        $data = PaymentMenuItem::where('name', 'main')->first();
        if ($data != null) {
            return $data;
        } else {
            $this->seed();
            $data = PaymentMenuItem::where('name', 'main')->first();
            return $data;
        }
    }
    public function getPaymentTypeMainMenuAliasText()
    {
        $data = PaymentMenuItem::where('name', 'main')->first();
        if ($data != null) {
            return $data->alias_name;
        } else {
            $this->seed();
            $data = PaymentMenuItem::where('name', 'main')->first();
            return $data->alias_name;
        }
    }
    public function getAllPaymentTypeMenues()
    {
        $data = PaymentMenuItem::first();
        // \Log::info($data);

        if ($data != null) {
            $data = PaymentMenuItem::all();

            return $data;
        } else {
            $this->getPaymentTypeMainMenuTitle();
            $data = PaymentMenuItem::all();

            return $data;
        }
    }
    public function getResponseOfSelectedOfflineMenu()
    {
        $data = PaymentMenuItem::where('name', 'reponse')->first();
        if ($data != null) {
            return $data->alias_name;
        } else {
            $this->seed();
            $data = PaymentMenuItem::where('name', 'reponse')->first();
            return $data->alias_name;
        }
    }

    public function updatePaymentMenuAlisNameByLevel(Request $request)
    {
        $data = PaymentMenuItem::where('level', $request->level)->first();
        if ($data != null) {
            // \Log::info($request->alias_name);

            $data->alias_name = $request->alias_name;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
}
