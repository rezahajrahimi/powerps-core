<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class SubscriptionPurchaseLock
{
    public const TTL_SECONDS = 600;

    public static function flagKey(int|string $chatId): string
    {
        return 'subscription_purchase_in_progress:' . $chatId;
    }

    public static function lockKey(int|string $chatId): string
    {
        return 'subscription_purchase_lock:' . $chatId;
    }

    public static function isInProgress(int|string $chatId): bool
    {
        return Cache::has(self::flagKey($chatId));
    }

    public static function markInProgress(int|string $chatId): void
    {
        Cache::put(self::flagKey($chatId), true, self::TTL_SECONDS);
    }

    public static function clear(int|string $chatId): void
    {
        Cache::forget(self::flagKey($chatId));
    }

    public static function acquire(int|string $chatId): ?Lock
    {
        $lock = Cache::lock(self::lockKey($chatId), self::TTL_SECONDS);

        return $lock->get() ? $lock : null;
    }
}
