<?php

namespace App\Services;

use App\Http\Controllers\AccountBallanceController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PaymentSettingController;
use App\Http\Controllers\ReferralWalletController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPaymentService
{
    public const SOURCE_TOMAN = 'toman';

    public const SOURCE_DOLLAR = 'dollar';

    public const SOURCE_REFERRAL = 'referral';

    public function __construct(
        private readonly AccountBallanceController $accBlCtrl = new AccountBallanceController(),
        private readonly PaymentSettingController $paymentSettingCtrl = new PaymentSettingController(),
        private readonly ReferralWalletController $referralCtrl = new ReferralWalletController(),
        private readonly LogController $logCtrl = new LogController(),
    ) {
    }

    /**
     * @return array{success: bool, source: ?string, amount_toman: float, amount_dollar: float}
     */
    public function charge(
        int|string $chatId,
        float $productPriceToman,
        float $productPriceDollar,
        bool $hasReferralBalance,
        string $username = '',
        string $logContext = 'subscription',
    ): array {
        $request = new Request();
        $request->userID = $chatId;
        $request->ballance = $productPriceToman;
        $request->type = self::SOURCE_TOMAN;

        $balance = $this->accBlCtrl->decreaseUserAccuntBalanceByUserID($request);
        \Log::info('processPayment balance: ' . var_export($balance, true));

        if ($this->isDecreaseSuccessful($balance)) {
            $this->logCtrl->addNewLog(
                $logContext,
                'کسر موجودی از کیف پول کاربر به مقدار ' . $productPriceToman . ' تومان',
                $chatId,
                $username,
                'success'
            );

            return $this->chargeResult(true, self::SOURCE_TOMAN, $productPriceToman, 0.0);
        }

        $dollarTransaction = $this->paymentSettingCtrl->getPaymentSettingStatusByKey('usd_transaction');
        \Log::info('dollarTransaction: ' . var_export($dollarTransaction, true));

        if ($dollarTransaction == true || $dollarTransaction == 1) {
            $request->ballance = $productPriceDollar;
            $request->type = self::SOURCE_DOLLAR;
            $balance = $this->accBlCtrl->decreaseUserAccuntBalanceByUserID($request);

            if ($this->isDecreaseSuccessful($balance)) {
                $this->logCtrl->addNewLog(
                    $logContext,
                    'کسر موجودی از کیف پول کاربر به مقدار ' . $productPriceDollar . ' دلار',
                    $chatId,
                    $username,
                    'success'
                );

                return $this->chargeResult(true, self::SOURCE_DOLLAR, 0.0, $productPriceDollar);
            }
        }

        if ($hasReferralBalance == true || $hasReferralBalance == 1) {
            $balance = $this->referralCtrl->dec_user_ref_wallet_ballance($chatId, $productPriceToman);
            \Log::info('processPayment referral balance: ' . var_export($balance, true));

            if ($balance === true) {
                $this->logCtrl->addNewLog(
                    $logContext,
                    'کسر موجودی از کیف پول همکاری به مقدار ' . $productPriceToman . ' تومان',
                    $chatId,
                    $username,
                    'success'
                );

                return $this->chargeResult(true, self::SOURCE_REFERRAL, $productPriceToman, 0.0);
            }
        }

        return $this->chargeResult(false, null, 0.0, 0.0);
    }

    /**
     * @param  array{success: bool, source: ?string, amount_toman: float, amount_dollar: float}  $chargeResult
     */
    public function refund(
        int|string $chatId,
        array $chargeResult,
        string $username = '',
        string $logContext = 'subscription',
    ): bool {
        if (! ($chargeResult['success'] ?? false)) {
            return false;
        }

        $source = $chargeResult['source'] ?? null;
        $amountToman = (float) ($chargeResult['amount_toman'] ?? 0);
        $amountDollar = (float) ($chargeResult['amount_dollar'] ?? 0);

        if ($source === self::SOURCE_REFERRAL) {
            $refunded = $this->referralCtrl->inc_user_ref_wallet_ballance($chatId, $amountToman);
            if ($refunded) {
                $this->logCtrl->addNewLog(
                    $logContext,
                    'بازگشت مبلغ ' . $amountToman . ' تومان به کیف پول همکاری پس از شکست خرید',
                    $chatId,
                    $username,
                    'edit'
                );
            }

            return (bool) $refunded;
        }

        if ($source === self::SOURCE_DOLLAR) {
            $refunded = $this->accBlCtrl->incUserAccuntBalanceInDollar($chatId, $amountDollar);
            if ($refunded) {
                $this->logCtrl->addNewLog(
                    $logContext,
                    'بازگشت مبلغ ' . $amountDollar . ' دلار به کیف پول کاربر پس از شکست خرید',
                    $chatId,
                    $username,
                    'edit'
                );
            }

            return (bool) $refunded;
        }

        if ($source === self::SOURCE_TOMAN) {
            $refunded = $this->accBlCtrl->incUserAccuntBalance($chatId, $amountToman);
            if ($refunded) {
                $this->logCtrl->addNewLog(
                    $logContext,
                    'بازگشت مبلغ ' . $amountToman . ' تومان به کیف پول کاربر پس از شکست خرید',
                    $chatId,
                    $username,
                    'edit'
                );
            }

            return (bool) $refunded;
        }

        return false;
    }

    /**
     * @param  array{success: bool, source: ?string, amount_toman: float, amount_dollar: float}  $chargeResult
     */
    public function wasCharged(array $chargeResult): bool
    {
        return (bool) ($chargeResult['success'] ?? false);
    }

    private function isDecreaseSuccessful(mixed $result): bool
    {
        if ($result === false || $result === null) {
            return false;
        }

        if ($result instanceof JsonResponse) {
            return false;
        }

        return true;
    }

    /**
     * @return array{success: bool, source: ?string, amount_toman: float, amount_dollar: float}
     */
    private function chargeResult(
        bool $success,
        ?string $source,
        float $amountToman,
        float $amountDollar,
    ): array {
        return [
            'success' => $success,
            'source' => $source,
            'amount_toman' => $amountToman,
            'amount_dollar' => $amountDollar,
        ];
    }
}
