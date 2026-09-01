<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BotGeneralController extends Controller
{
    public function __construct()
    {
    }
    /// check  dollarPay is valid or not
    public function checkDollarPay()
    {
        $paymnetSettingCntrl = new PaymentSettingController();
        $dollarTransaction = $paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');
        
        if($dollarTransaction == 1 || $dollarTransaction == true){ 
            \Log::info("dollar transaction is true");
            return true;
        } else {
            \Log::info("dollar transaction is false");
            return false;
        }
    }
    public function how_to_use_menu($chat_id)
    {
        try {
            $opr = [];
            array_push($opr, [
                [
                    'text'          => 'آموزش استفاده',
                    'callback_data' => 'help-faqs',
                ],
            ]);
            array_push($opr, [
                [
                    'text'          => 'برنامه های مورد نیاز',
                    'callback_data' => 'help-appDownload',
                ],
            ]);
            $text   = 'یک گزینه را انتخاب کنید.';
            $result = app('telegram_bot')->commandMessage($opr, $chat_id, $text);

            return $result;
        } catch (\Throwable $th) {
            \Log::info("how_to_use_menu $th");

            return $th;
        }
    }
    public function increase_account_ballance_menu_on_low_balance($chat_id, $estimatedPrice, $estimatedPriceInDollar)
    {
        try {
            $billCntrl           = new BillController();
            $request             = new Request();
            $request->account_id = $chat_id;
            $request->amount     = $estimatedPrice;
            $bill                = $billCntrl->createNewBill($request);

            // sent online payment
            $opr = [];

            // check if zarinpal
            $pymCntrl = new PaymentTypeController();

            $hasZarinPal = $pymCntrl->getZarinpalStatus();
            if ($hasZarinPal == true || $hasZarinPal == 1) {
                // send link

                // $openLink = "https://googloooli.com";
                // $openLink = $pymCntrl->getZarinpalLink();

                /////
                $trCntrl               = new TransactionController();
                $trRequest             = new Request();
                $trRequest->invoiceID  = $bill->bill_id;
                $trRequest->account_id = $chat_id;
                $trRequest->amount     = $estimatedPrice;
                $paymentLink           = $trCntrl->add_order($trRequest);

                $generalCntrl = new GeneralController();
                //  $zarinPal = $generalCntrl->get_zarinpal_payment_link_from_html($paymentLink);

                //

                array_push($opr, [
                    [
                        'text' => "پرداخت آنلاین $estimatedPrice تومان",
                        'url'  => "$paymentLink",
                    ],
                ]);
            }
            // check is dollatpay enabled or not
            if ($this->checkDollarPay() == true || $this->checkDollarPay() == 1) {
                $amount = $estimatedPriceInDollar;

                $request = new Request();

                $request->account_id = $chat_id;
                $request->amount     = $amount;
                $billCntrl           = new BillController();

                $bill = $billCntrl->createNewBillInDollar($request);

                $openLink = $pymCntrl->getNowPaymentsLink();
                ///
                $trCryptoCntrl         = new TransactionCryptoController();
                $trRequest             = new Request();
                $trRequest->invoiceID  = $bill->bill_id;
                $trRequest->account_id = $chat_id;
                $trRequest->amount     = $amount;
                $paymentLink           = $trCryptoCntrl->add_order_crypto_by_nowpayment($trRequest);

                $generalCntrl   = new GeneralController();
                $nowpaymentLink = $generalCntrl->get_nowpayment_payment_link_from_html($paymentLink);
                // ///
                array_push($opr, [
                    [
                        'text' => "پرداخت آنلاین $amount دلار",
                        'url'  => "$nowpaymentLink",
                    ],
                ]);
            }

            // check opr is not empty

            if (count($opr) > 0) {
                $text   = 'یکی از روش‌های پرداخت را انتخاب کنید.';
                $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $chat_id, '');
            }

            // send offline item
            $offlinePayment = $pymCntrl->getAllActiveOfflinePaymentTypes();
            if ($offlinePayment != null) {
                $pymMenCntrl = new PaymentMenuItemController();
                if ($hasZarinPal == true || $hasZarinPal == 1 || $this->checkDollarPay() == true || $this->checkDollarPay() == 1) {
                    $text = 'همچنین می توانید با انتخاب یکی از گزینه های زیر نسبت به پرداخت اقدام نمایید.';
                } else {
                    $mainMenu = $pymMenCntrl->getPaymentTypeMainMenuTitle();
                    $text     = $mainMenu->alias_name;
                }

                $opr = [];

                foreach ($offlinePayment as $key => $value) {
                    array_push($opr, [['text' => "$value->name", 'callback_data' => "subAccountBalance-$value->name "]]);
                }

                $result = app('telegram_bot')->commandMessage($opr, $chat_id, $text);
            }
        } catch (\Throwable $th) {
            \Log::info("increase_account_ballance_menu_on_low_balance $th");

            return $th;
        }
    }
}
