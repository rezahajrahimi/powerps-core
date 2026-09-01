<?php

namespace App\Http\Controllers;

use App\Services\LoyaltyPointsService;
use App\Services\ZarinpalService;
use Illuminate\Support\Facades\Config;

use App\Models\Transaction;
use App\Models\PaymentType;
use App\Models\CryptoPayment;
use App\Models\TransactionImage;
use App\Models\Bill;

use Illuminate\Http\Request;
class TransactionController extends Controller
{
    public $account_id;
    public $amount;
    public $amount_dollar;
    public function add_order(Request $request)
    {
        $settingCntrl = new SettingController();
        $mainUrl = $settingCntrl->getMainUrl();

        // Get amount from bill
        $bill = Bill::where('bill_id', $request->invoiceID)->first();

        $this->amount = $bill->amount;
        $this->account_id = $request->account_id;

        if ($this->amount != null) {
            $zarinpalPayment = PaymentType::where('name', 'زرین پال')->first();
            $zarinpalMerchentID = $zarinpalPayment?->merchant_id;
            if ($zarinpalMerchentID == null) {
                return 'ZARINPAL_MERCHANT_ID is not set';
            }

            $callbackUrl = PaymentType::resolveZarinpalCallbackUrl(
                $zarinpalPayment->callback_url ?? null,
                $mainUrl
            );
            $isSandbox = (bool) ($zarinpalPayment->is_sandbox ?? false);

            // Use custom ZarinpalService
            $zarinpal = new ZarinpalService($zarinpalMerchentID, $isSandbox, $callbackUrl);
            $response = $zarinpal->request($this->amount, 'خرید کالا');

            \Log::info("Zarinpal payment link created: " . ($response['success'] ? $response['authority'] : $response['error']));

            if (!$response['success']) {
                return $response['error'];
            }

            $authority = $response['authority'];

            // Save authority in db as new bill transaction_id
            $transaction = new Transaction();
            $transaction->account_id = $this->account_id;
            $transaction->username = '';
            $transaction->amount = $this->amount;
            $transaction->confirmed = 0;
            $transaction->recipe_number = $authority;
            $transaction->payment_type_id = $zarinpalPayment->id;

            $transaction->save();

            return $response['url'];
        } else {
            return 'این صورتحساب موجود نمی باشد.';
        }
    }
    public function order(Request $request)
    {
        try {
            $authority = $request->Authority; // Zarinpal returns Authority in camelCase or depends on driver, v4 uses Authority
            $status = $request->Status;

            if ($status !== 'OK') {
                return 'پرداخت توسط کاربر لغو شد یا ناموفق بود.';
            }

            $amount = $this->getAmountByRecipeNUmber($authority);

            // Get transaction with $authority
            $transaction = Transaction::where('recipe_number', $authority)->first();

            if (!$transaction) {
                return 'تراکنش یافت نشد.';
            }

            // Check if transaction was confirmed before
            if ($transaction->confirmed == true) {
                return 'تراکنش قبلاً تایید شده است.';
            }

            $zarinpalPayment = PaymentType::where('name', 'زرین پال')->first();
            $zarinpalMerchentID = $zarinpalPayment?->merchant_id;
            $isSandbox = (bool) ($zarinpalPayment->is_sandbox ?? false);

            // Use custom ZarinpalService for verification
            $zarinpal = new ZarinpalService($zarinpalMerchentID, $isSandbox);
            $response = $zarinpal->verify($authority, (int)$amount);

            if (!$response['success']) {
                return $response['error'];
            }

            $confirmReq = new Request();
            $confirmReq->id = $transaction->id;
            $confirmReq->confirmed = 1;
            $confirmReq->amount = $transaction->amount;
            $confirmReq->account_id = $transaction->account_id;
            $confirmReq->recipeNUmber = $transaction->recipe_number;
            $confirmReq->paymentTypeId = $transaction->payment_type_id;
            $confirmReq->isPaymntBack = true;

            $this->editUserTranaction($confirmReq);

            return 'پرداخت با موفقیت انجام شد. می توانید این پنجره را ببندید.';
        } catch (\Exception $exception) {
            $authority = $request->Authority;

            $transaction = Transaction::where('recipe_number', $authority)->first();

            if ($transaction) {
                $this->removeUnconfirmedTransaction($transaction->id);
            }
            \Log::error("Zarinpal callback error: " . $exception->getMessage());
            return 'خطا در پردازش بازگشت از درگاه';
        }
    }
    public function getUserTranaction($userID)
    {
        $data = Transaction::where('account_id', $userID)->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function addUserTranaction($userID, $amount, $recipeNUmber, $paymentTypeId)
    {
        try {
            if ($paymentTypeId == 0 || $paymentTypeId == null) {
                $pay = PaymentType::where('is_active', true)->where('type', 'offline')->first();
                if ($pay == null) {
                    $pay = PaymentType::where('type', 'offline')->first();
                }
                if ($pay == null) {
                    \Log::error('addUserTranaction failed: no offline payment type found');
                    return null;
                }
                $paymentTypeId = $pay->id;
            }
            $transaction = new Transaction();
            $transaction->account_id = $userID;
            $transaction->username = '';
            $transaction->amount = $amount;
            $transaction->recipe_number = $recipeNUmber;
            $transaction->payment_type_id = $paymentTypeId;
            $transaction->save();

            return $transaction->id;
        } catch (\Throwable $th) {
            \Log::info("Throwable  $th");
            return null;
        }
    }
    public function removeUnconfirmedTransaction($id)
    {
        try {
            $transaction = Transaction::find($id);
            if ($transaction->confirmed == false || $transaction->confirmed == 0) {
                // remove transaction image on disk
                $transactionImage = TransactionImage::where('transaction_id', $id)->first();
                if ($transactionImage != null) {
                    $path = public_path() . '' . $transactionImage->img_src;
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }

                $transaction->delete();
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            \Log::info("$th");

            return response()->json('NO Data Founded', 404);
        }
    }
    public function editUserTranaction(Request $request)
    {
        try {
            $transaction = Transaction::find($request->id);
            if ($transaction != null) {
                $isConfirmed = $request->confirmed == 1 || $request->confirmed == true ? true : false;

                // if ($transaction->amount != $request->amount && $isConfirmed == true) {
                if ($isConfirmed == true) {
                    $accBlCtrl = new AccountBallanceController();
                    if ($transaction->amount > $request->amount) {
                        $accBlCtrl->decUserAccuntBalance($transaction->account_id, $transaction->amount - $request->amount);
                    } else {
                        $accBlCtrl->incUserAccuntBalance($transaction->account_id, $request->amount);
                    }

                    $transaction->amount = $request->amount;
                }
                $transaction->recipe_number = $request->recipeNUmber;
                $transaction->payment_type_id = $request->paymentTypeId;
                $transaction->confirmed = $isConfirmed;

                if ($transaction->update()) {
                    $referralLogsCntrl = new ReferralLogsController();
                    $referralSettingCntrl = new ReferralSettingController();

                    $referral_percent = $referralSettingCntrl->get_referral_setting_referral_percent();
                    $amount = \App\Models\ReferralSetting::commissionFromAmount(
                        (float) $transaction->amount,
                        $referral_percent
                    );
                    if ($isConfirmed) {
                        $result = app('telegram_bot')->sendMessage("تراکنش شما با موفقیت ثبت شد و مبلغ {$transaction->amount} به حساب شما افزوده شد.", $transaction->account_id, null, 'MarkDown');
                        (new LoyaltyPointsService())->awardDepositPoints(
                            $transaction->account_id,
                            (float) $transaction->amount,
                            $transaction->id
                        );
                        // set referral wallet
                        if ($request->isPaymntBack == true) {
                            $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id, $amount, true);
                        } else {
                            $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id, $amount, false);
                        }
                    } else {
                        $result = app('telegram_bot')->sendMessage('تراکنش شما مورد تایید نمی باشد.', $transaction->account_id, null, 'MarkDown');
                        // set referral wallet
                        $referralLogsCntrl = new ReferralLogsController();
                        $referralLogsCntrl->decrease_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id, $amount);
                    }

                    return response()->json($transaction, 200);
                } else {
                    \Log::info('Failed to update transaction');

                    return response()->json($transaction, 404);
                }
            } else {
                \Log::info('NO Data Founded');

                return response()->json('NO Data Founded', 404);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable  $th");
        }
    }
    public function getAmountByRecipeNUmber($recipeNUmber)
    {
        $data = Transaction::where('recipe_number', $recipeNUmber)->first();
        if ($data != null) {
            return $data->amount;
        } else {
            return 0;
        }
    }
    public function setConfirmedTransaction($recipeNUmber)
    {
        $data = Transaction::where('recipe_number', $recipeNUmber)->first();
        if ($data != null) {
            $data->confirmed = true;
            $data->update();

            $result = app('telegram_bot')->sendMessage("تراکنش شما با موفقیت ثبت شد و مبلغ {$data->amount} به حساب شما افزوده شد.", $data->account_id, null, 'MarkDown');
            $referralLogsCntrl = new ReferralLogsController();
            $referralSettingCntrl = new ReferralSettingController();
            $referral_percent = $referralSettingCntrl->get_referral_setting_referral_percent();
            $commissionAmount = \App\Models\ReferralSetting::commissionFromAmount(
                (float) $data->amount,
                $referral_percent
            );
            $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($data->id, $commissionAmount, false);

            return true;
        } else {
            return false;
        }
    }
    public function getUserAccountIDByTransactionId($recipeNUmber)
    {
        $data = Transaction::where('recipe_number', $recipeNUmber)->first();
        if ($data != null) {
            return $data->account_id;
        } else {
            return null;
        }
    }

    public function getConfirmedTransactions(Request $request, $count = 10)
    {
        try {
            return Transaction::where('confirmed', true)
                ->with(['payment_types', 'transaction_image', 'user'])
                ->orderBy('id', 'desc')
                ->paginate($count);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
    public function getUnConfirmedTransactions(Request $request, $count = 10)
    {
        try {
            return Transaction::where('confirmed', false)
                ->with(['payment_types', 'transaction_image', 'user'])
                ->orderBy('id', 'desc')
                ->paginate($count);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }
}
