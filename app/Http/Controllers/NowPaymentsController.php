<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use PrevailExcel\Nowpayments\Facades\Nowpayments;
class NowPaymentsController extends Controller
{
    public function createCryptoInvoice(Request $request)
    {
        try {
            $data = [
                'price_amount' => $request->amount,
                'price_currency' => 'usd',
                'order_id' => $request->order_id,
                'order_description' => $request->order_description,
                'ipn_callback_url' => $request->ipn_callback_url,
                'success_url' => $request->success_url,
                'cancel_url' => $request->cancel_url,
                'partially_paid_url' => $request->partially_paid_url,
                'is_fixed_rate' => $request->is_fixed_rate == 1 ? true : false,
                'is_fee_paid_by_user' => $request->is_fee_paid_by_user == 1 ? true : false,
            ];
            // $data = [
            //     'price_amount' => 10,
            //     'price_currency' => 'usd',
            //     'order_id' => uniqid(),
            //     'order_description' => 'Apple Macbook Pro 2019 x 1',
            //     'ipn_callback_url' => 'https://nowpayments.io',
            //     'success_url' => 'https://nowpayments.io',
            //     'cancel_url' => 'https://nowpayments.io',
            //     'partially_paid_url' => 'https://nowpayments.io',
            //     'is_fixed_rate' => true,
            //     'is_fee_paid_by_user' => false,
            // ];

            $paymentDetails = Nowpayments::createInvoice($data);

            // dd($paymentDetails);
            // Now you have the payment details,
            // you can then redirect or do whatever you want

            // return Redirect::back()->with(['msg' => 'Payment created successfully', 'type' => 'success'], $paymentDetails['invoice_url']);
            return redirect()->to($paymentDetails['invoice_url']);
            // return Redirect::back()->with($paymentDetails['invoice_url']);
        } catch (\Exception $e) {
            \Log::info("Exception $e");
            return Redirect::back()->withMessage(['msg' => "There's an error in the data", 'type' => 'error']);
        }
    }

}
