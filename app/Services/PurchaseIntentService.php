<?php

namespace App\Services;

use App\Models\PurchaseIntent;
use Carbon\Carbon;

class PurchaseIntentService
{
    public function record(string $accountId, int $categoryId, string $stage, ?int $productId = null): PurchaseIntent
    {
        $open = PurchaseIntent::where('account_id', $accountId)
            ->where('product_category_id', $categoryId)
            ->whereNull('completed_at')
            ->latest('id')
            ->first();

        if ($open) {
            $open->update([
                'stage' => $stage,
                'product_id' => $productId ?? $open->product_id,
            ]);
            return $open;
        }

        return PurchaseIntent::create([
            'account_id' => $accountId,
            'product_category_id' => $categoryId,
            'product_id' => $productId,
            'stage' => $stage,
        ]);
    }

    public function completeForAccount(string $accountId, ?int $categoryId = null): void
    {
        $query = PurchaseIntent::where('account_id', $accountId)->whereNull('completed_at');
        if ($categoryId !== null) {
            $query->where('product_category_id', $categoryId);
        }
        $query->update(['completed_at' => Carbon::now()]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseIntent>
     */
    public function getFirstReminders(string $stage, int $hoursSinceCreated)
    {
        return PurchaseIntent::whereNull('completed_at')
            ->where('stage', $stage)
            ->where('reminder_count', 0)
            ->where('created_at', '<=', Carbon::now()->subHours($hoursSinceCreated))
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseIntent>
     */
    public function getFollowUpReminders(string $stage, int $hoursSinceLastReminder)
    {
        return PurchaseIntent::whereNull('completed_at')
            ->where('stage', $stage)
            ->where('reminder_count', 1)
            ->where(function ($q) use ($hoursSinceLastReminder) {
                $q->where('last_reminder_at', '<=', Carbon::now()->subHours($hoursSinceLastReminder))
                    ->orWhere(function ($q2) use ($hoursSinceLastReminder) {
                        $q2->whereNull('last_reminder_at')
                            ->where('created_at', '<=', Carbon::now()->subHours($hoursSinceLastReminder));
                    });
            })
            ->get();
    }

    public function markReminded(PurchaseIntent $intent): void
    {
        $intent->update([
            'reminder_count' => $intent->reminder_count + 1,
            'last_reminder_at' => Carbon::now(),
        ]);
    }
}
