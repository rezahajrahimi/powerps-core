<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyTransaction;
use App\Models\LoyaltyWallet;
use App\Models\User;
use Illuminate\Http\Request;

class LoyaltyLogsController extends Controller
{
    public function get_loyalty_logs(Request $request, $account_id)
    {
        try {
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:5|max:50',
            ]);

            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);

            $user = User::with('loyalty_wallet')->where('account_id', $account_id)->first();
            if ($user === null) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'summary' => [
                        'earn_count' => 0,
                        'redeem_count' => 0,
                        'total_earned' => 0,
                        'current_balance' => 0,
                    ],
                ], 200);
            }

            $query = LoyaltyTransaction::where('user_id', $user->id);

            $summary = [
                'earn_count' => (clone $query)->where('points', '>', 0)->count(),
                'redeem_count' => (clone $query)->where('points', '<', 0)->count(),
                'total_earned' => (int) (clone $query)->where('points', '>', 0)->sum('points'),
                'current_balance' => (int) ($user->loyalty_wallet?->balance ?? 0),
            ];

            $paginated = (clone $query)
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'data' => $paginated->items(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'summary' => $summary,
            ]);
        } catch (\Throwable $th) {
            \Log::info("Throwable get_loyalty_logs: $th");

            return response()->json(null, 500);
        }
    }

    public function get_all_loyalty_logs()
    {
        try {
            return LoyaltyTransaction::with('user')
                ->orderByDesc('id')
                ->limit(500)
                ->get();
        } catch (\Throwable $th) {
            \Log::info("Throwable get_all_loyalty_logs: $th");

            return response()->json(null, 500);
        }
    }

    public function get_top_loyalty_users()
    {
        try {
            return LoyaltyWallet::with('user')
                ->where('balance', '>', 0)
                ->orderByDesc('balance')
                ->limit(10)
                ->get()
                ->map(function (LoyaltyWallet $wallet) {
                    return [
                        'account_id' => $wallet->user?->account_id,
                        'name' => $wallet->user?->name ?? $wallet->user?->username ?? 'کاربر',
                        'balance' => (int) $wallet->balance,
                    ];
                });
        } catch (\Throwable $th) {
            \Log::info("Throwable get_top_loyalty_users: $th");

            return response()->json(null, 500);
        }
    }
}
