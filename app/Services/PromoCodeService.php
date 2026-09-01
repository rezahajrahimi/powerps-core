<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PromoCodeService
{
    public function findByCode(string $code): ?PromoCode
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return null;
        }

        return PromoCode::whereRaw('UPPER(code) = ?', [$normalized])->first();
    }

    /**
     * @return array{valid: bool, message?: string, promo?: PromoCode, discount_toman?: float, discount_dollar?: float, final_price_toman?: float, final_price_dollar?: float}
     */
    public function validate(
        string $code,
        string $accountId,
        int $categoryId,
        float $priceToman,
        float $priceDollar
    ): array {
        $promo = $this->findByCode($code);
        if ($promo === null) {
            return ['valid' => false, 'message' => 'کد تخفیف یافت نشد.'];
        }

        if (! $promo->is_active) {
            return ['valid' => false, 'message' => 'این کد تخفیف غیرفعال است.'];
        }

        $now = Carbon::now();
        if ($promo->starts_at && $now->lt($promo->starts_at)) {
            return ['valid' => false, 'message' => 'این کد تخفیف هنوز فعال نشده است.'];
        }
        if ($promo->expires_at && $now->gt($promo->expires_at)) {
            return ['valid' => false, 'message' => 'این کد تخفیف منقضی شده است.'];
        }
        if ($promo->max_uses !== null && $promo->used_count >= $promo->max_uses) {
            return ['valid' => false, 'message' => 'ظرفیت استفاده از این کد تمام شده است.'];
        }

        $userUsageCount = PromoCodeUsage::where('promo_code_id', $promo->id)
            ->where('account_id', $accountId)
            ->count();
        if ($userUsageCount >= $promo->max_uses_per_user) {
            return ['valid' => false, 'message' => 'شما قبلاً از این کد استفاده کرده‌اید.'];
        }

        $allowedCategories = $promo->allowed_category_ids;
        if (is_array($allowedCategories) && $allowedCategories !== [] && ! in_array($categoryId, $allowedCategories, true)) {
            return ['valid' => false, 'message' => 'این کد برای این بسته قابل استفاده نیست.'];
        }

        if ($promo->min_order_amount !== null && $priceToman < (float) $promo->min_order_amount) {
            return ['valid' => false, 'message' => 'حداقل مبلغ سفارش برای این کد رعایت نشده است.'];
        }

        $allowedGroups = $promo->allowed_user_group_ids;
        if (is_array($allowedGroups) && $allowedGroups !== [] && User::hasUserGroupColumn()) {
            $userGroupId = User::resolveUserGroupIdForAccount($accountId);
            if ($userGroupId === null || ! in_array($userGroupId, $allowedGroups, true)) {
                return ['valid' => false, 'message' => 'این کد برای گروه کاربری شما قابل استفاده نیست.'];
            }
        }

        $discountToman = $this->calculateDiscount($promo, $priceToman, 'toman');
        $discountDollar = $this->calculateDiscount($promo, $priceDollar, 'dollar');

        return [
            'valid' => true,
            'promo' => $promo,
            'discount_toman' => $discountToman,
            'discount_dollar' => $discountDollar,
            'final_price_toman' => max(0, $priceToman - $discountToman),
            'final_price_dollar' => max(0, $priceDollar - $discountDollar),
        ];
    }

    public function calculateDiscount(PromoCode $promo, float $amount, string $currency = 'toman'): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        return match ($promo->type) {
            'percent' => round($amount * ((float) $promo->value / 100), $currency === 'dollar' ? 2 : 0),
            'fixed_dollar' => $currency === 'dollar' ? min($amount, (float) $promo->value) : 0.0,
            default => $currency === 'toman' ? min($amount, (float) $promo->value) : 0.0,
        };
    }

    public function rememberPendingCode(string $accountId, int $categoryId, ?string $code): void
    {
        $normalized = strtoupper(trim((string) $code));
        if ($normalized === '') {
            return;
        }

        Cache::put($this->pendingCodeCacheKey($accountId, $categoryId), $normalized, now()->addHours(12));
    }

    public function pullPendingCode(string $accountId, int $categoryId): ?string
    {
        $code = Cache::pull($this->pendingCodeCacheKey($accountId, $categoryId));

        return is_string($code) && $code !== '' ? $code : null;
    }

    private function pendingCodeCacheKey(string $accountId, int $categoryId): string
    {
        return "pending_promo_{$accountId}_{$categoryId}";
    }

    public function recordUsage(PromoCode $promo, string $accountId, float $discountAmount, ?int $productId = null): void
    {
        PromoCodeUsage::create([
            'promo_code_id' => $promo->id,
            'account_id' => $accountId,
            'product_id' => $productId,
            'discount_amount' => $discountAmount,
            'applied_at' => Carbon::now(),
        ]);

        $promo->increment('used_count');
    }
}
