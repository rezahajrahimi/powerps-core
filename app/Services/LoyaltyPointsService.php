<?php

namespace App\Services;

use App\Models\LoyaltySetting;
use App\Models\LoyaltyTransaction;
use App\Models\LoyaltyWallet;
use App\Models\User;

class LoyaltyPointsService
{
    public function __construct(
        private readonly LicenseFeatureService $license = new LicenseFeatureService(),
    ) {
    }

    public function isLicensed(): bool
    {
        return $this->license->isSilverOrAbove();
    }

    public function getSettings(): ?LoyaltySetting
    {
        return LoyaltySetting::first();
    }

    public function isActive(): bool
    {
        if (! $this->isLicensed()) {
            return false;
        }

        $settings = $this->getSettings();

        return $settings !== null && (bool) $settings->is_active;
    }

    public function canRedeem(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $settings = $this->getSettings();

        return $settings !== null && (bool) $settings->redeem_enabled;
    }

    public function seedDefaultSettings(): LoyaltySetting
    {
        $existing = LoyaltySetting::first();
        if ($existing !== null) {
            return $existing;
        }

        return LoyaltySetting::create([
            'description' => 'با هر خرید، تمدید و واریز امتیاز بگیرید و در خرید بعدی از آن استفاده کنید.',
            'is_active' => false,
            'earn_on_purchase' => true,
            'earn_on_renewal' => true,
            'earn_on_deposit' => true,
            'earn_on_referral' => true,
            'redeem_enabled' => true,
            'purchase_points_per_1000_toman' => 10,
            'renewal_points' => 50,
            'deposit_points_per_1000_toman' => 5,
            'referral_signup_points' => 100,
            'toman_per_point' => 10,
            'min_redeem_points' => 100,
            'max_redeem_percent' => 50,
        ]);
    }

    public function getBalanceByAccountId(int|string $accountId): int
    {
        $user = User::where('account_id', $accountId)->first();
        if ($user === null) {
            return 0;
        }

        return $this->getBalanceByUserId((int) $user->id);
    }

    public function getBalanceByUserId(int $userId): int
    {
        $wallet = LoyaltyWallet::where('user_id', $userId)->first();

        return $wallet !== null ? (int) $wallet->balance : 0;
    }

    public function getOrCreateWallet(int $userId): LoyaltyWallet
    {
        $wallet = LoyaltyWallet::where('user_id', $userId)->first();
        if ($wallet !== null) {
            return $wallet;
        }

        return LoyaltyWallet::create([
            'user_id' => $userId,
            'balance' => 0,
        ]);
    }

    /**
     * @return array{price_toman: float, points_to_redeem: int, toman_discount: float}
     */
    public function applyRedemptionToPrice(
        int|string $accountId,
        float $orderAmountToman,
        bool $usePoints = true,
        ?int $requestedPoints = null,
    ): array {
        $base = [
            'price_toman' => $orderAmountToman,
            'points_to_redeem' => 0,
            'toman_discount' => 0.0,
        ];

        if (! $this->canRedeem() || ! $usePoints || $orderAmountToman <= 0) {
            return $base;
        }

        $settings = $this->getSettings();
        if ($settings === null) {
            return $base;
        }

        $user = User::where('account_id', $accountId)->first();
        if ($user === null) {
            return $base;
        }

        $balance = $this->getBalanceByUserId((int) $user->id);
        if ($balance < (int) $settings->min_redeem_points) {
            return $base;
        }

        $tomanPerPoint = max(1, (int) $settings->toman_per_point);
        $maxPercent = min(100, max(0, (int) $settings->max_redeem_percent));
        $maxTomanDiscount = $orderAmountToman * ($maxPercent / 100);
        $maxPointsByPercent = (int) floor($maxTomanDiscount / $tomanPerPoint);
        $maxPoints = min($balance, $maxPointsByPercent);

        if ($requestedPoints !== null) {
            $maxPoints = min($maxPoints, max(0, $requestedPoints));
        }

        if ($maxPoints < (int) $settings->min_redeem_points) {
            return $base;
        }

        $tomanDiscount = $maxPoints * $tomanPerPoint;
        $tomanDiscount = min($tomanDiscount, $orderAmountToman);

        return [
            'price_toman' => max(0.0, $orderAmountToman - $tomanDiscount),
            'points_to_redeem' => $maxPoints,
            'toman_discount' => $tomanDiscount,
        ];
    }

    public function redeemPoints(
        int|string $accountId,
        int $points,
        string $event,
        ?string $referenceType = null,
        int|string|null $referenceId = null,
        ?string $description = null,
    ): bool {
        if ($points <= 0 || ! $this->canRedeem()) {
            return false;
        }

        $user = User::where('account_id', $accountId)->first();
        if ($user === null) {
            return false;
        }

        $wallet = $this->getOrCreateWallet((int) $user->id);
        if ($wallet->balance < $points) {
            return false;
        }

        $wallet->balance = (int) $wallet->balance - $points;
        $wallet->save();

        LoyaltyTransaction::create([
            'user_id' => $user->id,
            'type' => LoyaltyTransaction::TYPE_REDEEM,
            'event' => $event,
            'points' => -$points,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId !== null ? (int) $referenceId : null,
            'description' => $description ?? 'استفاده از امتیاز در خرید',
        ]);

        return true;
    }

    public function refundRedeemedPoints(
        int|string $accountId,
        int $points,
        ?string $description = null,
    ): bool {
        if ($points <= 0) {
            return false;
        }

        $user = User::where('account_id', $accountId)->first();
        if ($user === null) {
            return false;
        }

        $wallet = $this->getOrCreateWallet((int) $user->id);
        $wallet->balance = (int) $wallet->balance + $points;
        $wallet->save();

        LoyaltyTransaction::create([
            'user_id' => $user->id,
            'type' => LoyaltyTransaction::TYPE_REFUND,
            'event' => 'checkout',
            'points' => $points,
            'description' => $description ?? 'بازگشت امتیاز پس از شکست خرید',
        ]);

        return true;
    }

    public function awardPurchasePoints(int|string $accountId, float $amountToman, int|string|null $referenceId = null): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        $settings = $this->getSettings();
        if ($settings === null || ! $settings->earn_on_purchase || $amountToman <= 0) {
            return 0;
        }

        $points = (int) floor($amountToman / 1000 * (int) $settings->purchase_points_per_1000_toman);
        if ($points <= 0) {
            return 0;
        }

        return $this->awardPoints(
            $accountId,
            $points,
            'purchase',
            'product',
            $referenceId,
            "امتیاز خرید به مبلغ {$amountToman} تومان"
        );
    }

    public function awardRenewalPoints(int|string $accountId, int|string|null $referenceId = null): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        $settings = $this->getSettings();
        if ($settings === null || ! $settings->earn_on_renewal) {
            return 0;
        }

        $points = (int) $settings->renewal_points;
        if ($points <= 0) {
            return 0;
        }

        return $this->awardPoints(
            $accountId,
            $points,
            'renewal',
            'product',
            $referenceId,
            'امتیاز تمدید اشتراک'
        );
    }

    public function awardDepositPoints(int|string $accountId, float $amountToman, int|string|null $transactionId = null): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        $settings = $this->getSettings();
        if ($settings === null || ! $settings->earn_on_deposit || $amountToman <= 0) {
            return 0;
        }

        $points = (int) floor($amountToman / 1000 * (int) $settings->deposit_points_per_1000_toman);
        if ($points <= 0) {
            return 0;
        }

        return $this->awardPoints(
            $accountId,
            $points,
            'deposit',
            'transaction',
            $transactionId,
            "امتیاز واریز به مبلغ {$amountToman} تومان"
        );
    }

    public function awardReferralSignupPoints(int|string $referrerAccountId, int|string $referredAccountId): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        $settings = $this->getSettings();
        if ($settings === null || ! $settings->earn_on_referral) {
            return 0;
        }

        $points = (int) $settings->referral_signup_points;
        if ($points <= 0) {
            return 0;
        }

        return $this->awardPoints(
            $referrerAccountId,
            $points,
            'referral_signup',
            'user',
            $referredAccountId,
            'امتیاز معرفی کاربر جدید'
        );
    }

    public function setBalanceByAccountId(int|string $accountId, int $balance): bool
    {
        $user = User::where('account_id', $accountId)->first();
        if ($user === null) {
            return false;
        }

        $wallet = $this->getOrCreateWallet((int) $user->id);
        $oldBalance = (int) $wallet->balance;
        $wallet->balance = max(0, $balance);
        $wallet->save();

        $diff = (int) $wallet->balance - $oldBalance;
        if ($diff !== 0) {
            LoyaltyTransaction::create([
                'user_id' => $user->id,
                'type' => LoyaltyTransaction::TYPE_ADMIN,
                'event' => 'admin',
                'points' => $diff,
                'description' => 'تغییر دستی امتیاز توسط مدیر',
            ]);
        }

        return true;
    }

    /**
     * @return array{
     *     charge_price_toman: float,
     *     points_to_redeem: int,
     *     toman_discount: float,
     *     can_proceed: bool,
     *     has_balance: bool,
     *     has_ref_balance: bool,
     *     points_only: bool
     * }
     */
    public function resolveCheckout(
        int|string $accountId,
        float $productPriceToman,
        float $productPriceInDollar,
        callable $hasBalanceChecker,
        callable $hasReferralBalanceChecker,
        bool $usePoints = true,
    ): array {
        $redemption = $this->applyRedemptionToPrice($accountId, $productPriceToman, $usePoints);
        $chargePriceToman = (float) $redemption['price_toman'];
        $pointsToRedeem = (int) $redemption['points_to_redeem'];
        $hasBalance = (bool) $hasBalanceChecker($accountId, $chargePriceToman, $productPriceInDollar);
        $hasRefBalance = $chargePriceToman > 0
            ? (bool) $hasReferralBalanceChecker($accountId, $chargePriceToman)
            : false;
        $pointsOnly = $chargePriceToman <= 0 && $pointsToRedeem > 0;

        return [
            'charge_price_toman' => $chargePriceToman,
            'points_to_redeem' => $pointsToRedeem,
            'toman_discount' => (float) $redemption['toman_discount'],
            'can_proceed' => $pointsOnly || $hasBalance || $hasRefBalance,
            'has_balance' => $hasBalance,
            'has_ref_balance' => $hasRefBalance,
            'points_only' => $pointsOnly,
        ];
    }

    private function awardPoints(
        int|string $accountId,
        int $points,
        string $event,
        ?string $referenceType = null,
        int|string|null $referenceId = null,
        ?string $description = null,
    ): int {
        if ($points <= 0) {
            return 0;
        }

        $user = User::where('account_id', $accountId)->first();
        if ($user === null) {
            return 0;
        }

        $wallet = $this->getOrCreateWallet((int) $user->id);
        $wallet->balance = (int) $wallet->balance + $points;
        $wallet->save();

        LoyaltyTransaction::create([
            'user_id' => $user->id,
            'type' => LoyaltyTransaction::TYPE_EARN,
            'event' => $event,
            'points' => $points,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId !== null ? (int) $referenceId : null,
            'description' => $description,
        ]);

        return $points;
    }
}
