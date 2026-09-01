<?php

namespace App\Http\Controllers;

use App\Services\LoyaltyPointsService;
use App\Models\ReferralLogs;
use App\Models\User;
use App\Models\ReferralWallet;
use App\Models\ReferralSetting;
use App\Models\Transaction;

use Illuminate\Http\Request;

class ReferralLogsController extends Controller
{
    private function isValidTelegramAccountId($accountId): bool
    {
        return is_numeric($accountId) && (int) $accountId > 0;
    }

    private function isReferralActive(): bool
    {
        $referralSetting = ReferralSetting::first();

        return $referralSetting != null
            && ($referralSetting->is_active === true || $referralSetting->is_active === 1);
    }

    public function get_userId_by_accountId($referralCode)
    {
        try {
            if (!$this->isValidTelegramAccountId($referralCode)) {
                return null;
            }

            $user = User::where('account_id', (string) $referralCode)->first();
            if ($user != null) {
                return $user->id;
            }

            return null;
        } catch (\Throwable $th) {
            \Log::info("Throwable get_userId_by_accountId: $th");

            return null;
        }
    }

    private function findSignupReferralLog($userId): ?ReferralLogs
    {
        return ReferralLogs::where('referral_to_id', $userId)
            ->whereNull('transaction_id')
            ->where('amount', 0)
            ->first();
    }

    public function check_user_is_referred($account_id)
    {
        try {
            $user_id = $this->get_userId_by_accountId($account_id);
            if ($user_id === null) {
                return false;
            }

            return $this->findSignupReferralLog($user_id) != null;
        } catch (\Throwable $th) {
            \Log::info("Throwable check_user_is_referred: $th");

            return false;
        }
    }

    public function check_user_has_referral_and_create($account_id, $referralCode)
    {
        try {
            if (!$this->isReferralActive()) {
                return false;
            }

            if (
                !$this->isValidTelegramAccountId($account_id)
                || !$this->isValidTelegramAccountId($referralCode)
            ) {
                return false;
            }

            $user_id = $this->get_userId_by_accountId($account_id);
            $referral_id = $this->get_userId_by_accountId($referralCode);
            if ($user_id === null || $referral_id === null || $user_id == $referral_id) {
                return false;
            }

            $referralLogs = $this->findSignupReferralLog($user_id);
            if ($referralLogs != null) {
                return true;
            }

            $newReferralLogs = new ReferralLogs();
            $newReferralLogs->referral_user_id = $referral_id;
            $newReferralLogs->referral_to_id = $user_id;
            $newReferralLogs->amount = 0;
            $newReferralLogs->transaction_id = null;
            $newReferralLogs->save();

            \Log::info("newReferralLogs: {$newReferralLogs->referral_user_id} {$newReferralLogs->referral_to_id}");

            $this->notifyReferrer(
                (string) $referralCode,
                'یک کاربر با لینک دعوت شما وارد ربات شد.'
            );

            $referrer = User::find($referral_id);
            if ($referrer != null) {
                $this->addNewBotLog(
                    'referral',
                    'کاربر جدید با لینک دعوت وارد شد.',
                    'signup',
                    (string) $referrer->account_id,
                    (string) ($referrer->name ?? $referrer->username ?? 'referrer')
                );

                (new LoyaltyPointsService())->awardReferralSignupPoints(
                    $referrer->account_id,
                    $account_id
                );
            }

            return true;
        } catch (\Throwable $th) {
            \Log::info("Throwable check_user_has_referral: $th");

            return null;
        }
    }

    public function get_refreal_user_by_account_id($account_id)
    {
        try {
            $user_id = $this->get_userId_by_accountId($account_id);
            if ($user_id === null) {
                return null;
            }

            $referralLogs = $this->findSignupReferralLog($user_id);
            if ($referralLogs != null) {
                return $referralLogs->referral_user_id;
            }

            return null;
        } catch (\Throwable $th) {
            \Log::info("Throwable get_refreal_user_by_account_id: $th");

            return null;
        }
    }

    public function get_referral_logs($account_id)
    {
        try {
            $authUser = auth('sanctum')->user();
            if (
                $authUser == null
                || (
                    $authUser->role !== 'admin'
                    && (string) $authUser->account_id !== (string) $account_id
                )
            ) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if (!$this->isValidTelegramAccountId($account_id)) {
                return response()->json(['message' => 'Invalid account id'], 422);
            }

            $user_id = $this->get_userId_by_accountId($account_id);
            if ($user_id === null) {
                return [];
            }

            return ReferralLogs::where('referral_user_id', $user_id)
                ->with(['referral_to', 'referral_user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Throwable $th) {
            \Log::info("Throwable get_referral_logs: $th");

            return response()->json(null, 500);
        }
    }

    public function get_all_referral_logs()
    {
        try {
            return ReferralLogs::with(['referral_to', 'referral_user'])
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Throwable $th) {
            \Log::info("Throwable get_all_referral_logs: $th");

            return response()->json(null, 500);
        }
    }

    public function get_top_referrers()
    {
        try {
            return ReferralLogs::select('referral_user_id', \DB::raw('count(distinct referral_to_id) as referral_count'))
                ->whereNull('transaction_id')
                ->with(['referral_user'])
                ->groupBy('referral_user_id')
                ->orderBy('referral_count', 'desc')
                ->get();
        } catch (\Throwable $th) {
            \Log::info("Throwable get_top_referrers: $th");

            return response()->json(null, 500);
        }
    }

    public function add_new_referral_logs(Request $request)
    {
        try {
            $referralLogs = new ReferralLogs();
            $referralLogs->referral_user_id = $this->get_refreal_user_by_account_id($request->referral_to_id);
            $referralLogs->referral_to_id = $this->get_userId_by_accountId($request->referral_to_id);
            if (
                $referralLogs->referral_to_id == null
                || $referralLogs->referral_user_id == null
                || $referralLogs->referral_to_id == $referralLogs->referral_user_id
            ) {
                return false;
            }
            $referralLogs->amount = $request->amount;
            $referralLogs->transaction_id = $request->transaction_id ?? null;
            $referralLogs->save();

            return $referralLogs;
        } catch (\Throwable $th) {
            \Log::info("Throwable add_new_referral_logs: $th");

            return response()->json(null, 500);
        }
    }

    public function creditCommissionForDeposit($accountId, $amountToman, $transactionId = null)
    {
        $referral_percent = (new ReferralSettingController())->get_referral_setting_referral_percent();
        $commissionAmount = ReferralSetting::commissionFromAmount(
            (float) $amountToman,
            $referral_percent
        );

        return $this->creditCommissionToReferrer($accountId, $commissionAmount, $transactionId);
    }

    public function add_amount_to_refrerral_user_Log_and_referral_wallet($transaction_id, $amount, $isPaymentBack = false)
    {
        $transaction = Transaction::find($transaction_id);
        if ($transaction == null) {
            return null;
        }

        return $this->creditCommissionToReferrer($transaction->account_id, $amount, $transaction_id);
    }

    public function creditCommissionToReferrer($accountId, $amount, $transactionId = null)
    {
        try {
            if (!$this->isReferralActive()) {
                return null;
            }

            $amount = (float) $amount;
            if ($amount <= 0) {
                return null;
            }

            if ($transactionId) {
                $existingCommission = ReferralLogs::where('transaction_id', $transactionId)
                    ->where('amount', '>', 0)
                    ->first();
                if ($existingCommission != null) {
                    return $existingCommission;
                }
            }

            $referredUser = User::where('account_id', $accountId)->first();
            if ($referredUser == null) {
                return null;
            }

            $signupLog = $this->findSignupReferralLog($referredUser->id);
            if ($signupLog == null) {
                return null;
            }

            $commissionLog = new ReferralLogs();
            $commissionLog->referral_user_id = $signupLog->referral_user_id;
            $commissionLog->referral_to_id = $referredUser->id;
            $commissionLog->amount = $amount;
            $commissionLog->transaction_id = $transactionId;
            $commissionLog->save();

            $referralWallet = ReferralWallet::where('referral_user_id', $commissionLog->referral_user_id)->first();
            if ($referralWallet != null) {
                $referralWallet->amount = $referralWallet->amount + $amount;
                $referralWallet->update();
            } else {
                $referralWallet = new ReferralWallet();
                $referralWallet->referral_user_id = $commissionLog->referral_user_id;
                $referralWallet->amount = $amount;
                $referralWallet->save();
            }

            $user = User::find($commissionLog->referral_user_id);
            if ($user != null) {
                $referralCode = $user->account_id;
                $text = "مقدار {$amount} تومان به کیف همکاری شما افزوده شد.";
                $this->notifyReferrer((string) $referralCode, $text);
                $this->addNewBotLog(
                    'referral',
                    $text,
                    'commission_credit',
                    (string) $referralCode,
                    (string) ($user->name ?? $user->username ?? 'referrer')
                );
            }

            return $commissionLog;
        } catch (\Throwable $th) {
            \Log::info("Throwable add_amount_to_refrerral_user_Log_and_referral_wallet: $th");

            return null;
        }
    }

    public function decrease_amount_to_refrerral_user_Log_and_referral_wallet($transaction_id, $amount)
    {
        try {
            if (!$this->isReferralActive()) {
                return null;
            }

            $referralLog = ReferralLogs::where('transaction_id', $transaction_id)
                ->where('amount', '>', 0)
                ->first();
            if ($referralLog == null) {
                return null;
            }

            $deductAmount = (float) $referralLog->amount;
            $referralWallet = ReferralWallet::where('referral_user_id', $referralLog->referral_user_id)->first();
            if ($referralWallet != null) {
                $referralWallet->amount = max(0, $referralWallet->amount - $deductAmount);
                $referralWallet->save();
            }

            $user = User::find($referralLog->referral_user_id);
            if ($user != null) {
                $referralCode = $user->account_id;
                $text = "مقدار {$deductAmount} تومان از کیف همکاری شما کم شد.";
                $this->notifyReferrer((string) $referralCode, $text);
                $this->addNewBotLog(
                    'referral',
                    $text,
                    'commission_debit',
                    (string) $referralCode,
                    (string) ($user->name ?? $user->username ?? 'referrer')
                );
            }

            $referralLog->delete();

            return true;
        } catch (\Throwable $th) {
            \Log::info("Throwable decrease_amount_to_refrerral_user_Log_and_referral_wallet: $th");

            return null;
        }
    }

    private function notifyReferrer(string $accountId, string $text): void
    {
        try {
            if (app()->bound('telegram_bot')) {
                app('telegram_bot')->sendMessage($text, $accountId, null, 'MarkDown');
            }
        } catch (\Throwable $th) {
            \Log::info('referral telegram notify failed: ' . $th->getMessage());
        }
    }

    public function addNewBotLog($type, $message, $event, $account_id = null, $username = null)
    {
        try {
            $logCtrl = new LogController();
            $logCtrl->addNewLog(
                $type,
                $message,
                $account_id ?? '0',
                $username ?? 'system',
                $event
            );

            return true;
        } catch (\Throwable $th) {
            \Log::info("Throwable addNewBotLog: $th");

            return false;
        }
    }
}
