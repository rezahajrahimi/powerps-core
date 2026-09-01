<?php

namespace App\Http\Controllers;

use App\Models\TransactionSetting;
use Illuminate\Http\Request;

class TransactionSettingController extends Controller
{
    public function seed()
    {
        if (TransactionSetting::all()->isEmpty()) {
            $data = new TransactionSetting();
            $data->dollar_transaction = false;
            $data->save();
            return true;
        }
        return false;
    }

   public function getDollorTransactionSetting()
   {
    $paymnetSettingCntrl = new PaymentSettingController();
    $dollarTransaction = $paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');
    if ($dollarTransaction == null) {
        $paymnetSettingCntrl->seed();
        $dollarTransaction = $paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');
    }

    return $dollarTransaction;
   }

   public function setDollorTransactionSetting(Request $request)
   {
       $data = TransactionSetting::first();
       if($data != null){
           $data->dollar_transaction = $request->dollar_transaction;
           $data->update();
           return $data->dollar_transaction;
       }else{
          $this->seed();
          return $this->getDollorTransactionSetting();
       }
   }

}
