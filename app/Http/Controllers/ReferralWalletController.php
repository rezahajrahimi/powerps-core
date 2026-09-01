<?php

namespace App\Http\Controllers;

use App\Models\ReferralWallet;
use App\Models\User;
use Illuminate\Http\Request;

class ReferralWalletController extends Controller
{
    public function get_auth_user_wallet(Request $request)
    {
        try {
            $user = $request->user();
            $wallet = ReferralWallet::where('referral_user_id', $user->id)->first();
            if ($wallet == null) {
                $wallet = new ReferralWallet();
                $wallet->referral_user_id = $user->id;
                $wallet->amount = 0.0;
                $wallet->save();
            }
            return $wallet;
        } catch (\Throwable $th) {
            \Log::info("Throwable get_auth_user_wallet: $th");
            return response()->json(null, 500);
        }
    }
    public function get_amount_of_ref_wallet_by_account_id($account_id)
    {
        try {
            $user = User::where('account_id', $account_id)->first();
            $wallet = ReferralWallet::where('referral_user_id', $user->id)->first();
            if ($wallet == null) {
                $wallet = new ReferralWallet();
                $wallet->referral_user_id = $user->id;
                $wallet->amount = 0.0;
                $wallet->save();
            }
            return $wallet->amount;
        } catch (\Throwable $th) {
            \Log::info("Throwable get_amount_of_ref_wallet_by_account_id: $th");
            return null;
        }
    }
    public function check_user_has_ref_wallet_ballance($account_id, $amount)
    {
        try {
            $user = User::where('account_id', $account_id)->first();
            if ($user == null) {
                return false;
            }
            $wallet = ReferralWallet::where('referral_user_id', $user->id)->first();
            if ($wallet == null) {
                return false;
            }
            if ($wallet->amount >= $amount) {
                return true;
            }
            return false;
        } catch (\Throwable $th) {
            \Log::info("Throwable check_user_has_ref_wallet_ballance: $th");
            return false;
        }
    }
    public function dec_user_ref_wallet_ballance($account_id, $amount)
    {
        try {
            $user = User::where('account_id', $account_id)->first();
            if ($user == null) {
                return false;
            }
            $wallet = ReferralWallet::where('referral_user_id', $user->id)->first();
            if ($wallet == null) {
                return false;
            }
            $wallet->amount = $wallet->amount - (float) $amount;
            $wallet->update();
            return true;
        } catch (\Throwable $th) {
            \Log::info("Throwable dec_user_ref_wallet_ballance: $th");
            return false;
        }
    }
    public function inc_user_ref_wallet_ballance($account_id, $amount)
    {
        try {
            $user = User::where('account_id', $account_id)->first();
            if ($user == null) {
                return false;
            }
            $wallet = ReferralWallet::where('referral_user_id', $user->id)->first();
            if ($wallet == null) {
                $wallet = new ReferralWallet();
                $wallet->referral_user_id = $user->id;
                $wallet->amount = 0.0;
                $wallet->save();
            }
            $wallet->amount = $wallet->amount + (float) $amount;
            $wallet->update();
            return true;
        } catch (\Throwable $th) {
            \Log::info("Throwable inc_user_ref_wallet_ballance: $th");
            return false;
        }
    }
    public function edit_amount_of_ref_wallet_by_account_id(Request $request)
    {
        try {
            $validated = $request->validate([
                'account_id' => 'required|integer|min:1',
                'amount' => 'required|numeric|min:0',
            ]);

            $user = User::where('account_id', $validated['account_id'])->first();
            if ($user == null) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $wallet = ReferralWallet::where('referral_user_id', $user->id)->first();
            if ($wallet == null) {
                $wallet = new ReferralWallet();
                $wallet->referral_user_id = $user->id;
                $wallet->amount = 0;
                $wallet->save();
            }
            $wallet->amount = $validated['amount'];
            $wallet->update();

            return response()->json(['success' => true], 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable edit_amount_of_ref_wallet_by_account_id: $th");
            return response()->json(null, 500);
        }
    }
}
