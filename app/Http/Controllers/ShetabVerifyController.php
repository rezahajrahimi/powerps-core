<?php
namespace App\Http\Controllers;

use App\Jobs\ProcessSubscriptionPurchase;
use App\Models\BotUser;
use App\Models\PaymentSetting;
use App\Models\PaymentType;
use App\Models\ProductCategory;
use App\Models\ShetabVerify;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use Illuminate\Http\Request;
use App\Services\TelegramService;
use App\Services\SubscriptionPurchaseLock;
use Illuminate\Support\Facades\Log;

class ShetabVerifyController extends Controller
{
    private PaymentSettingController $paymnetSettingCntrl;
    private CustomTextController $customTextCtrl;

    public function __construct()
    {
        $this->paymnetSettingCntrl = new PaymentSettingController();
        $this->customTextCtrl = new CustomTextController();
    }

    public function check_shetab_verify_status()
    {
        return $this->paymnetSettingCntrl->getPaymentSettingStatusByKey('shetab_verify');
    }

    public function create_new_shetab_verify(Request $request)
    {
        $baseAmount = (int) ceil((float) $request->amount);
        $productCategoryId = $request->product_category_id ? (int) $request->product_category_id : null;

        if ($baseAmount <= 0) {
            Log::warning('Shetab verify create rejected: invalid base amount', [
                'user_id' => $request->user_id ?? null,
                'amount' => $request->amount,
            ]);

            return null;
        }

        $existingPending = ShetabVerify::where('user_id', $request->user_id)
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(10))
            ->first();

        if ($existingPending && $this->pendingIntentMatches($existingPending, $baseAmount, $productCategoryId)) {
            Log::info('Shetab verify reusing existing pending invoice', [
                'user_id' => $request->user_id,
                'amount' => $existingPending->amount,
                'product_category_id' => $existingPending->product_category_id,
            ]);

            return $existingPending->amount;
        }

        $uniqueAmount = $this->create_uniqe_amount($baseAmount);

        $shetabVerify = ShetabVerify::create([
            'amount' => (string) $uniqueAmount,
            'base_amount' => (string) $baseAmount,
            'user_id' => $request->user_id,
            'product_category_id' => $productCategoryId,
            'status' => 'pending',
        ]);

        $user = User::find($request->user_id);
        $botUser = $user ? BotUser::where('account_id', $user->account_id)->first() : null;
        $logMessage = $productCategoryId
            ? "صدور فاکتور تایید خودکار شتاب برای خرید بسته (پایه: {$baseAmount}، مبلغ واریز: {$uniqueAmount})"
            : "صدور فاکتور تایید خودکار شتاب (پایه: {$baseAmount}، مبلغ واریز: {$uniqueAmount})";

        if ($user) {
            $this->addNewBotLog(
                'shetab_verify',
                $logMessage,
                $user->account_id,
                $botUser?->username ?? '',
                $productCategoryId ? 'auto_purchase_invoice' : 'balance_invoice'
            );
        }

        Log::info('Shetab verify invoice created', [
            'user_id' => $request->user_id,
            'base_amount' => $baseAmount,
            'unique_amount' => $shetabVerify->amount,
            'product_category_id' => $productCategoryId,
        ]);

        return $shetabVerify->amount;
    }

    private function pendingIntentMatches(ShetabVerify $pending, int $baseAmount, ?int $productCategoryId): bool
    {
        return (int) ($pending->base_amount ?? 0) === $baseAmount
            && (int) ($pending->product_category_id ?? 0) === (int) ($productCategoryId ?? 0);
    }

    public function create_uniqe_amount(int $baseAmount): int
    {
        $baseAmount = max(1, $baseAmount);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $prefix = intdiv($baseAmount, 100);
            $suffix = random_int(0, 99);
            $candidate = ($prefix * 100) + $suffix;

            if ($candidate < $baseAmount) {
                $candidate = $baseAmount + random_int(1, 99);
            }

            $exists = ShetabVerify::where('amount', (string) $candidate)
                ->where('status', 'pending')
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        $fallback = $baseAmount + random_int(100, 999);
        while (ShetabVerify::where('amount', (string) $fallback)->where('status', 'pending')->exists()) {
            $fallback++;
        }

        return $fallback;
    }

    public function validate_shetab_verify(Request $request)
    {
        $rawAmount = preg_replace('/[^0-9]/', '', (string) ($request->amount ?? ''));
        $amountToman = $rawAmount !== '' ? (int) round(((int) $rawAmount) / 10) : 0;

        Log::info('Shetab verify callback received', [
            'from' => $request->from,
            'amount_rials' => $rawAmount,
            'amount_toman' => $amountToman,
            'date' => $request->date,
        ]);

        $apiKeyHeader = trim((string) $request->header('Authorization'));
        if ($apiKeyHeader === '') {
            Log::warning('Shetab verify rejected: missing api key');
            $this->addAnonymousFailureLog('درخواست تایید شتاب بدون API key رد شد.');

            return response()->json(['message' => 'Api key is required'], 401);
        }

        $apiKeyConfig = trim((string) (PaymentSetting::where('key', 'shetab_verify')->first()?->value ?? ''));
        if ($apiKeyConfig === '' || ! hash_equals($apiKeyConfig, $apiKeyHeader)) {
            Log::warning('Shetab verify rejected: invalid api key');
            $this->addAnonymousFailureLog('درخواست تایید شتاب با API key نامعتبر رد شد.');

            return response()->json(['message' => 'Api key is invalid'], 401);
        }

        if ($amountToman <= 0) {
            Log::warning('Shetab verify rejected: invalid parsed amount', [
                'raw_amount' => $request->amount,
            ]);
            $this->addAnonymousFailureLog('درخواست تایید شتاب با مبلغ نامعتبر رد شد.');

            return response()->json(['message' => 'Invalid amount'], 422);
        }

        $shetabVerify = ShetabVerify::where('amount', (string) $amountToman)
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(10))
            ->first();

        if (! $shetabVerify) {
            Log::warning('Shetab verify not found', [
                'amount_toman' => $amountToman,
                'pending_count' => ShetabVerify::where('status', 'pending')->count(),
            ]);
            $this->addAnonymousFailureLog("فاکتور تایید شتاب برای مبلغ {$amountToman} تومان یافت نشد.");

            return response()->json(['message' => 'Shetab verify not found'], 404);
        }

        if ($shetabVerify->status !== 'pending') {
            Log::warning('Shetab verify already processed', [
                'amount_toman' => $amountToman,
                'status' => $shetabVerify->status,
            ]);

            return response()->json(['message' => 'Shetab verify is not verified'], 400);
        }

        $shetabVerify->status = 'verified';
        $shetabVerify->save();

        $telegramService = new TelegramService();
        $user = User::find($shetabVerify->user_id);
        if (! $user) {
            Log::error('Shetab verify user missing', ['user_id' => $shetabVerify->user_id]);

            return response()->json(['message' => 'User not found'], 500);
        }

        $botUser = BotUser::where('account_id', $user->account_id)->first();
        $username = $botUser?->username ?? '';

        $accountBallanceCtrl = new AccountBallanceController();
        $accountBallanceCtrl->incUserAccuntBalance($user->account_id, $amountToman);
        $this->creditShetabDepositRewards($user, $amountToman, $shetabVerify);

        $this->addNewBotLog(
            'shetab_verify',
            "تایید خودکار شتاب موفق - شارژ کیف پول به مبلغ {$amountToman} تومان",
            $user->account_id,
            $username,
            'verified'
        );

        $text = $this->customTextCtrl->getText('action.account.balance_added', ['amount' => $amountToman]);
        $telegramService->sendMessage($user->account_id, $text);

        $admin = User::where('role', 'admin')->first();
        if ($admin) {
            $adminText = "شارژ کیف پول از طریق کارت به کارت (شتاب) بوسیله کاربر {$user->account_id} با مبلغ {$amountToman} تومان انجام شد.";
            $telegramService->sendMessage($admin->account_id, $adminText);
        }

        if ($shetabVerify->product_category_id) {
            $productCategory = ProductCategory::find($shetabVerify->product_category_id);
            $categoryName = $productCategory?->category_name ?? (string) $shetabVerify->product_category_id;

            $this->addNewBotLog(
                'shetab_verify',
                "شروع خرید خودکار اشتراک «{$categoryName}» پس از تایید شتاب",
                $user->account_id,
                $username,
                'auto_purchase_start'
            );

            if (SubscriptionPurchaseLock::isInProgress($user->account_id)) {
                $telegramService->sendMessage(
                    $user->account_id,
                    'پرداخت شما تایید شد. خرید قبلی شما هنوز در حال پردازش است، لطفاً چند لحظه صبر کنید...'
                );
            } else {
                SubscriptionPurchaseLock::markInProgress($user->account_id);

                ProcessSubscriptionPurchase::dispatch(
                    $user->account_id,
                    $shetabVerify->product_category_id,
                    (new \App\Services\PromoCodeService())->pullPendingCode(
                        (string) $user->account_id,
                        (int) $shetabVerify->product_category_id
                    )
                );

                $telegramService->sendMessage(
                    $user->account_id,
                    'پرداخت شما تایید شد. در حال تکمیل خرید اشتراک هستید، لطفاً چند لحظه صبر کنید...'
                );
            }

            Log::info('Shetab verify auto purchase dispatched', [
                'user_id' => $user->id,
                'account_id' => $user->account_id,
                'product_category_id' => $shetabVerify->product_category_id,
                'amount_toman' => $amountToman,
            ]);
        }

        Log::info('Shetab verify successful', [
            'user_id' => $user->id,
            'account_id' => $user->account_id,
            'amount_toman' => $amountToman,
            'product_category_id' => $shetabVerify->product_category_id,
        ]);

        return response()->json(['message' => 'Shetab verify is verified'], 200);
    }

    public function creditShetabDepositRewards(User $user, int $amountToman, ShetabVerify $shetabVerify): void
    {
        try {
            $transactionId = $this->recordConfirmedShetabTransaction($user, $amountToman, $shetabVerify);
            (new LoyaltyPointsService())->awardDepositPoints(
                $user->account_id,
                (float) $amountToman,
                $transactionId
            );
            (new ReferralLogsController())->creditCommissionForDeposit(
                $user->account_id,
                $amountToman,
                $transactionId
            );
        } catch (\Throwable $th) {
            Log::error('Shetab deposit rewards failed: ' . $th->getMessage(), [
                'account_id' => $user->account_id,
                'amount_toman' => $amountToman,
            ]);
        }
    }

    public function recordConfirmedShetabTransaction(User $user, int $amountToman, ShetabVerify $shetabVerify): ?int
    {
        $paymentType = PaymentType::where('is_active', true)->where('type', 'offline')->first()
            ?? PaymentType::where('type', 'offline')->first()
            ?? PaymentType::first();

        if ($paymentType == null) {
            $paymentType = PaymentType::create([
                'name' => 'شتاب',
                'type' => 'offline',
                'is_active' => true,
            ]);
        }

        $transaction = new Transaction();
        $transaction->account_id = $user->account_id;
        $transaction->username = '';
        $transaction->amount = $amountToman;
        $transaction->recipe_number = 'SHETAB-' . $shetabVerify->id;
        $transaction->payment_type_id = $paymentType->id;
        $transaction->confirmed = true;
        $transaction->save();

        return $transaction->id;
    }

    private function addNewBotLog($type, $message, $chatId, $username, $event)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $chatId, $username, $event);

        return true;
    }

    private function addAnonymousFailureLog(string $message): void
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog('shetab_verify', $message, 0, '', 'failed');
    }
}
