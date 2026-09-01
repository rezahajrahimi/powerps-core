<?php

namespace App\Http\Controllers;

use App\Models\AccountBallance;
use App\Models\Bill;
use App\Models\CryptoPayment;
use App\Models\TransactionCrypto;
use App\Models\User;
use App\Services\SwapPayService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SwapPayController extends Controller
{
    protected function resolveCredentials(): ?CryptoPayment
    {
        return CryptoPayment::where('name', 'swappay')->first();
    }

    protected function makeService(?CryptoPayment $config = null): ?SwapPayService
    {
        $config ??= $this->resolveCredentials();
        if (! $config) {
            return null;
        }

        $service = new SwapPayService($config->api_key, $config->password);

        return $service->isConfigured() ? $service : null;
    }

    /**
     * Create SwapPay invoice and store local crypto transaction.
     * Returns payment URL string on success, or JsonResponse on failure.
     */
    public function createPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.1',
                'order_id' => 'required|string',
                'account_id' => 'required',
                'preferred_link' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'مبلغ یا اطلاعات فاکتور نامعتبر است.',
            ], 422);
        }

        $config = $this->resolveCredentials();
        $service = $this->makeService($config);
        if (! $service || ! $config) {
            Log::error('SwapPay createPayment: credentials missing');

            return response()->json([
                'success' => false,
                'message' => 'تنظیمات SwapPay کامل نیست.',
            ], 500);
        }

        $settingCntrl = new SettingController();
        $mainUrl = rtrim((string) $settingCntrl->getMainUrl(), '/');

        // Temporary return URL without invoice id; updated after create if needed.
        $returnUrl = $mainUrl . '/swappay/return?order_id=' . urlencode($validated['order_id']);

        $created = $service->createInvoice(
            (float) $validated['amount'],
            $returnUrl,
            (string) $validated['order_id'],
            'شارژ کیف پول #' . $validated['order_id'],
            'account_id=' . $validated['account_id']
        );

        if (! ($created['success'] ?? false) || empty($created['result']['id'])) {
            return response()->json([
                'success' => false,
                'message' => $created['error'] ?? 'خطا در ایجاد فاکتور SwapPay.',
            ], 500);
        }

        $result = $created['result'];
        $invoiceId = (string) $result['id'];
        $paymentLinks = is_array($result['paymentLinks'] ?? null) ? $result['paymentLinks'] : [];

        $preferred = strtoupper((string) ($validated['preferred_link'] ?? 'WEBSITE'));
        $preferredOrder = match ($preferred) {
            'TELEGRAM_BOT' => ['TELEGRAM_BOT', 'TELEGRAM_WEBAPP', 'WEBSITE'],
            'TELEGRAM_WEBAPP' => ['TELEGRAM_WEBAPP', 'TELEGRAM_BOT', 'WEBSITE'],
            default => ['WEBSITE', 'TELEGRAM_WEBAPP', 'TELEGRAM_BOT'],
        };
        $paymentUrl = SwapPayService::pickPaymentUrl($paymentLinks, $preferredOrder);
        if (! SwapPayService::isUsablePaymentUrl($paymentUrl)) {
            Log::error('SwapPay createPayment: no payment link in response', ['result' => $result]);

            return response()->json([
                'success' => false,
                'message' => 'لینک پرداخت SwapPay دریافت نشد.',
            ], 500);
        }

        $user = User::where('account_id', $validated['account_id'])->first();

        TransactionCrypto::updateOrCreate(
            [
                'order_id' => (string) $validated['order_id'],
                'gateway' => 'swappay',
            ],
            [
                'account_id' => $validated['account_id'],
                'username' => $user->username ?? 'admin',
                'crypto_payment_id' => $config->id,
                'amount_dollar' => (float) $validated['amount'],
                'currency' => 'USD',
                'status' => strtolower((string) ($result['status'] ?? 'pending')) === 'active'
                    ? 'pending'
                    : strtolower((string) ($result['status'] ?? 'pending')),
                'payment_id' => $invoiceId,
                'payment_url' => $paymentUrl,
                'confirmed' => false,
                'callback_data' => json_encode($result),
            ]
        );

        return $paymentUrl;
    }

    /**
     * Return URL handler: verify invoice with SwapPay and credit wallet if paid.
     */
    public function handleReturn(Request $request)
    {
        $invoiceId = trim((string) (
            $request->input('invoice_id')
            ?? $request->input('invoiceId')
            ?? $request->input('id')
            ?? data_get($request->all(), 'result.id')
            ?? ''
        ));
        $orderId = trim((string) (
            $request->input('order_id')
            ?? $request->input('orderId')
            ?? $request->input('externalId')
            ?? $request->input('external_id')
            ?? data_get($request->all(), 'result.externalId')
            ?? ''
        ));

        $transaction = null;
        if ($invoiceId !== '') {
            $transaction = TransactionCrypto::where('gateway', 'swappay')
                ->where('payment_id', $invoiceId)
                ->first();
        }
        if (! $transaction && $orderId !== '') {
            $transaction = TransactionCrypto::where('gateway', 'swappay')
                ->where('order_id', $orderId)
                ->first();
        }

        if (! $transaction) {
            Log::warning('SwapPay return: transaction not found', [
                'invoice_id' => $invoiceId,
                'order_id' => $orderId,
            ]);

            return response('تراکنش یافت نشد.', 404);
        }

        $result = $this->confirmPaidTransaction($transaction);
        if ($result === true) {
            return response('پرداخت با موفقیت انجام شد. می‌توانید این پنجره را ببندید و به ربات/پنل برگردید.', 200);
        }
        if ($result === 'already') {
            return response('این پرداخت قبلاً تایید شده است.', 200);
        }

        return response('پرداخت هنوز تایید نشده است. اگر مبلغ را پرداخت کرده‌اید چند لحظه بعد دوباره این صفحه را باز کنید.', 200);
    }

    /**
     * Confirm a SwapPay transaction against the remote API and credit balance.
     *
     * @return true|false|'already'
     */
    public function confirmPaidTransaction(TransactionCrypto $transaction): bool|string
    {
        if (
            $transaction->confirmed
            || in_array(strtolower((string) $transaction->status), ['paid', 'confirmed'], true)
        ) {
            return 'already';
        }

        $service = $this->makeService();
        if (! $service || empty($transaction->payment_id)) {
            return false;
        }

        $remote = $service->getInvoice((string) $transaction->payment_id);
        if (! ($remote['success'] ?? false)) {
            return false;
        }

        $result = $remote['result'];
        $status = (string) ($result['status'] ?? '');
        $transaction->callback_data = json_encode($result);
        $transaction->updated_at = Carbon::now();

        if (! SwapPayService::isPaidStatus($status)) {
            $transaction->status = strtolower($status !== '' ? $status : 'pending');
            $transaction->save();

            return false;
        }

        return $this->creditTransaction($transaction, $result) ? true : false;
    }

    /**
     * Recheck unpaid SwapPay invoices so wallet is credited even if the user
     * never opens the return URL.
     */
    public function confirmPendingPayments(): int
    {
        $confirmed = 0;
        $pending = TransactionCrypto::where('gateway', 'swappay')
            ->where(function ($query) {
                $query->where('confirmed', false)->orWhereNull('confirmed');
            })
            ->whereNotIn('status', ['paid', 'confirmed', 'expired', 'cancelled', 'canceled', 'failed'])
            ->whereNotNull('payment_id')
            ->where('created_at', '>=', Carbon::now()->subDays(2))
            ->orderBy('id')
            ->limit(40)
            ->get();

        foreach ($pending as $transaction) {
            try {
                if ($this->confirmPaidTransaction($transaction) === true) {
                    $confirmed++;
                }
            } catch (\Throwable $th) {
                Log::error('SwapPay confirmPendingPayments', [
                    'transaction_id' => $transaction->id,
                    'error' => $th->getMessage(),
                ]);
            }
        }

        return $confirmed;
    }

    protected function creditTransaction(TransactionCrypto $transaction, array $result): bool
    {
        try {
            return DB::transaction(function () use ($transaction, $result) {
                $locked = TransactionCrypto::where('id', $transaction->id)->lockForUpdate()->first();
                if (! $locked) {
                    return false;
                }
                if (
                    $locked->confirmed
                    || in_array(strtolower((string) $locked->status), ['paid', 'confirmed'], true)
                ) {
                    return true;
                }

                $amountToAdd = (float) ($locked->amount_dollar ?? 0);
                if ($amountToAdd <= 0 && isset($result['amount']['number'])) {
                    $amountToAdd = (float) $result['amount']['number'];
                }
                if ($amountToAdd <= 0) {
                    Log::error('SwapPay credit: invalid amount', ['transaction_id' => $locked->id]);

                    return false;
                }

                $user = User::where('account_id', $locked->account_id)->first();
                if (! $user) {
                    Log::error('SwapPay credit: user not found', ['account_id' => $locked->account_id]);

                    return false;
                }

                $accountBalance = AccountBallance::firstOrCreate(
                    ['account_id' => $user->account_id],
                    ['ballance' => 0, 'account_ballance_in_dollar' => 0]
                );
                $accountBalance->account_ballance_in_dollar = (float) $accountBalance->account_ballance_in_dollar + $amountToAdd;
                $accountBalance->save();

                $bill = Bill::where('bill_id', $locked->order_id)->first();
                if ($bill) {
                    $bill->status = 'paid';
                    $bill->save();
                }

                $locked->status = 'confirmed';
                $locked->confirmed = true;
                $locked->callback_data = json_encode($result);
                $locked->save();

                try {
                    $telegramService = new TelegramService();
                    $telegramService->sendMessage(
                        (string) $user->account_id,
                        "کیف پول شما به مقدار {$amountToAdd} دلار افزایش یافت (SwapPay)"
                    );
                } catch (\Throwable $th) {
                    Log::info('SwapPay telegram notify failed: ' . $th->getMessage());
                }

                Log::info('SwapPay payment confirmed', [
                    'transaction_id' => $locked->id,
                    'account_id' => $user->account_id,
                    'amount' => $amountToAdd,
                ]);

                return true;
            });
        } catch (\Throwable $th) {
            Log::error('SwapPay creditTransaction error', [
                'transaction_id' => $transaction->id,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }
}
