<?php

namespace App\Services;

use App\Http\Controllers\CronJobController;
use App\Models\BotUser;
use App\Models\Product;
use App\Models\AccountBallance;
use App\Models\User;
use Illuminate\Support\Carbon;

class MarketingSegmentService
{
    /**
     * @return array<int, string> account_ids
     */
    public function resolveRecipients(string $segmentType, ?array $params = null): array
    {
        $params = $params ?? [];

        return match ($segmentType) {
            'all' => BotUser::pluck('account_id')->toArray(),
            'selected' => array_values(array_filter($params['account_ids'] ?? [])),
            'no_config' => $this->usersWithoutConfigs(),
            'never_purchased' => $this->usersNeverPurchased(),
            'user_group' => $this->usersInGroup((int) ($params['user_group_id'] ?? 0)),
            'low_balance' => $this->usersWithLowBalance((float) ($params['max_balance'] ?? 10000)),
            'inactive_days' => $this->inactiveUsers((int) ($params['days'] ?? 30)),
            default => [],
        };
    }

    private function usersWithoutConfigs(): array
    {
        $buyers = Product::distinct()->pluck('account_id')->toArray();

        return BotUser::whereNotIn('account_id', $buyers)->pluck('account_id')->toArray();
    }

    private function usersNeverPurchased(): array
    {
        return $this->usersWithoutConfigs();
    }

    private function usersInGroup(int $groupId): array
    {
        if ($groupId <= 0 || ! User::hasUserGroupColumn()) {
            return [];
        }

        return User::where('user_group_id', $groupId)->pluck('account_id')->filter()->toArray();
    }

    private function usersWithLowBalance(float $maxBalance): array
    {
        return AccountBallance::where('ballance', '<', $maxBalance)
            ->pluck('account_id')
            ->toArray();
    }

    private function inactiveUsers(int $days): array
    {
        $since = Carbon::now()->subDays($days);
        $activeBuyers = Product::where('created_at', '>=', $since)->distinct()->pluck('account_id')->toArray();

        return BotUser::whereNotIn('account_id', $activeBuyers)->pluck('account_id')->toArray();
    }

    public function buildCtaButtons(?string $ctaType, ?string $ctaPayload): array
    {
        if ($ctaType === null || $ctaType === '') {
            return [];
        }

        return match ($ctaType) {
            'buy_menu' => [['خرید اشتراک' => 'openBuySubscription']],
            'add_balance' => [['افزایش موجودی' => 'accountAddBalance']],
            'promo_code' => $ctaPayload ? [['استفاده از کد تخفیف' => "applyPromo-{$ctaPayload}"]] : [],
            'recharge_product' => $ctaPayload ? [['تمدید بسته' => "recharge-{$ctaPayload}"]] : [],
            default => [],
        };
    }
}
