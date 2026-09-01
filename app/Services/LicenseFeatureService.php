<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class LicenseFeatureService
{
    public const SILVER_PROMO_MAX = 5;

    /** @var list<string> */
    public const GOLD_ADVANCED_SETTINGS = [
        'bot_auto_set_price_by_dollar_price',
        'bot_calculate_product_category_price_in_dollar_by_toman',
    ];

    public function __construct(
        private readonly LicenseCheckService $licenseCheck = new LicenseCheckService(),
    ) {
    }

    public function current(): string
    {
        return strtolower((string) $this->licenseCheck->getLicenseType());
    }

    public function isGold(): bool
    {
        return $this->current() === 'gold';
    }

    public function isSilverOrAbove(): bool
    {
        return in_array($this->current(), ['silver', 'gold'], true);
    }

    public function isBronzeOrBelow(): bool
    {
        return in_array($this->current(), ['false', 'trial', 'boronze', 'bronze', 'free'], true);
    }

    public function goldRequiredResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'این قابلیت فقط برای لایسنس طلایی فعال است.',
        ], 403);
    }

    public function silverRequiredResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'این قابلیت برای لایسنس نقره‌ای و طلایی فعال است.',
        ], 403);
    }

    public function canUseLoyaltyPoints(): bool
    {
        return $this->isSilverOrAbove();
    }

    public function canCustomizeBotButtons(): bool
    {
        return $this->isSilverOrAbove();
    }

    public function canUseAdvancedSettings(): bool
    {
        return $this->isSilverOrAbove();
    }

    public function canUseAdvancedSetting(string $name): bool
    {
        if (in_array($name, self::GOLD_ADVANCED_SETTINGS, true)) {
            return $this->isGold();
        }

        return $this->isSilverOrAbove();
    }

    public function advancedSettingRequiredResponse(string $name): JsonResponse
    {
        if (in_array($name, self::GOLD_ADVANCED_SETTINGS, true)) {
            return $this->goldRequiredResponse();
        }

        return $this->silverRequiredResponse();
    }

    public function maxPanels(): ?int
    {
        if ($this->isBronzeOrBelow()) {
            return 1;
        }

        if ($this->current() === 'silver') {
            return 2;
        }

        return null;
    }

    public function canAddPanel(int $currentPanelCount): bool
    {
        $max = $this->maxPanels();

        return $max === null || $currentPanelCount < $max;
    }

    public function panelLimitReachedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'به محدودیت افزودن پنل رسیده اید، برای افزودن پنل جدید با پشتیبانی تماس بگیرید و اکانت خود را ارتقا بدهید.',
        ], 403);
    }
}
