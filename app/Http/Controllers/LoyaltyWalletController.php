<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use Illuminate\Http\Request;

class LoyaltyWalletController extends Controller
{
    public function __construct(
        private readonly LoyaltyPointsService $loyalty = new LoyaltyPointsService(),
    ) {
    }

    public function get_amount_by_account_id($account_id)
    {
        try {
            return $this->loyalty->getBalanceByAccountId($account_id);
        } catch (\Throwable $th) {
            \Log::info("Throwable get_loyalty_amount_by_account_id: $th");

            return 0;
        }
    }

    public function edit_points_by_account_id(Request $request)
    {
        try {
            $validated = $request->validate([
                'account_id' => 'required|integer|min:1',
                'balance' => 'required|integer|min:0',
            ]);

            $user = User::where('account_id', $validated['account_id'])->first();
            if ($user === null) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $this->loyalty->setBalanceByAccountId($validated['account_id'], (int) $validated['balance']);

            return response()->json(['success' => true], 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable edit_loyalty_points_by_account_id: $th");

            return response()->json(null, 500);
        }
    }

    public function get_auth_user_loyalty(Request $request)
    {
        try {
            $user = $request->user();
            $balance = $this->loyalty->getBalanceByUserId((int) $user->id);
            $settings = $this->loyalty->getSettings();

            return response()->json([
                'balance' => $balance,
                'is_active' => $this->loyalty->isActive(),
                'redeem_enabled' => $this->loyalty->canRedeem(),
                'toman_per_point' => $settings?->toman_per_point ?? 10,
                'min_redeem_points' => $settings?->min_redeem_points ?? 0,
                'max_redeem_percent' => $settings?->max_redeem_percent ?? 0,
                'description' => $settings?->description,
            ]);
        } catch (\Throwable $th) {
            \Log::info("Throwable get_auth_user_loyalty: $th");

            return response()->json(null, 500);
        }
    }

    public function validate_redemption(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_amount_toman' => 'required|numeric|min:0',
                'use_loyalty_points' => 'nullable|boolean',
                'loyalty_points' => 'nullable|integer|min:0',
            ]);

            $accountId = $request->user()->account_id;
            $usePoints = $validated['use_loyalty_points'] ?? true;

            $result = $this->loyalty->applyRedemptionToPrice(
                $accountId,
                (float) $validated['order_amount_toman'],
                (bool) $usePoints,
                isset($validated['loyalty_points']) ? (int) $validated['loyalty_points'] : null,
            );

            return response()->json([
                'success' => true,
                'balance' => $this->loyalty->getBalanceByAccountId($accountId),
                'points_to_redeem' => $result['points_to_redeem'],
                'toman_discount' => $result['toman_discount'],
                'final_price_toman' => $result['price_toman'],
            ]);
        } catch (\Throwable $th) {
            \Log::info("Throwable validate_loyalty_redemption: $th");

            return response()->json(['success' => false, 'message' => 'خطا در محاسبه امتیاز'], 500);
        }
    }
}
