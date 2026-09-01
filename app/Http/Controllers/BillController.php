<?php

namespace App\Http\Controllers;
use App\Models\Bill;

use Illuminate\Http\Request;

class BillController extends Controller
{
    public function isBillIdExist($billId)
    {
        $data = Bill::where('bill_id', $billId)->first();
        if ($data != null) {
            return false;
        } else {
            return true;
        }
    }

    public function createNewBill(Request $request)
    {
        $bill = new Bill();
        $bill->account_id = $request->account_id;
        $bill->bill_id = abs(crc32(uniqid()));
        $bill->amount = $request->amount;
        $bill->amount_dollar = 0.0;
        if ($bill->save()) {
            return $bill;
        } else {
            return null;
        }
    }
    public function createNewBillInDollar(Request $request)
    {
        $bill = new Bill();
        $bill->account_id = $request->account_id;
        $bill->bill_id = abs(crc32(uniqid()));
        $bill->amount = 0;
        $bill->amount_dollar = $request->amount;
        if ($bill->save()) {
            return $bill;
        } else {
            return null;
        }
    }
    public function getBillAmountByBillId($billId)
    {
        $data = Bill::where('bill_id', $billId)->first();
        \Log::info("billllllll $data");

        if ($data != null) {
            return $data->amount;
        } else {
            return null;
        }
    }
    public function getBillAmountDollarByBillId($billId)
    {
        $data = Bill::where('bill_id', $billId)->first();
        if ($data != null) {
            return $data->amount_dollar;
        } else {
            return null;
        }
    }

    /// Agent Functions

    public function createNewAgentTomanBillUrl($amount)
    {
        $account_id = auth('sanctum')->user()->account_id;

        $bill = new Bill();
        $bill->account_id = $account_id;
        $bill->bill_id = abs(crc32(uniqid()));
        $bill->amount = $amount;
        $bill->amount_dollar = 0.0;
        if ($bill->save()) {
            $trCntrl = new TransactionController();
            $trRequest = new Request();
            $trRequest->invoiceID = $bill->bill_id;
            $trRequest->account_id = $account_id;
            $trRequest->amount = $amount;
            $paymentLink = $trCntrl->add_order($trRequest);
            return $paymentLink;
            // $pymCntrl = new PaymentTypeController();

            // $openLink = $pymCntrl->getZarinpalLink();

            // return "$openLink/$account_id/$bill->bill_id/$bill->amount";
        } else {
            return null;
        }
    }
    public function createNewAgentDollarBillUrl($amount)
    {
        $account_id = auth('sanctum')->user()->account_id;

        $bill = new Bill();
        $bill->account_id = $account_id;
        $bill->bill_id = abs(crc32(uniqid()));
        $bill->amount = 0;
        $bill->amount_dollar = $amount;
        if ($bill->save()) {
            $trCryptoCntrl = new TransactionCryptoController();
                $trRequest = new Request();
                $trRequest->invoiceID = $bill->bill_id;
                $trRequest->account_id = $account_id;
                $trRequest->amount = $amount;
                $paymentLink = $trCryptoCntrl->add_order_crypto_by_nowpayment($trRequest);

                $generalCntrl = new GeneralController();
                $nowpaymentLink = $generalCntrl->get_nowpayment_payment_link_from_html($paymentLink);
                return $nowpaymentLink;
            // $pymCntrl = new PaymentTypeController();

            // $openLink = $pymCntrl->getNowPaymentsLink();

            // return "$openLink/$account_id/$bill->bill_id/$bill->amount_dollar";
        } else {
            return null;
        }
    }

    public function createNewAgentSwapPayBillUrl($amount)
    {
        $account_id = auth('sanctum')->user()->account_id;

        $bill = new Bill();
        $bill->account_id = $account_id;
        $bill->bill_id = abs(crc32(uniqid()));
        $bill->amount = 0;
        $bill->amount_dollar = $amount;
        if ($bill->save()) {
            $trCryptoCntrl = new TransactionCryptoController();
            $trRequest = new Request([
                'gateway' => 'swappay',
                'invoiceID' => $bill->bill_id,
                'account_id' => $account_id,
                'preferred_link' => 'WEBSITE',
            ]);
            $paymentLink = $trCryptoCntrl->initiateCryptoPayment($trRequest);

            if (is_string($paymentLink) && str_starts_with($paymentLink, 'http')) {
                return $paymentLink;
            }

            return null;
        }

        return null;
    }
}
