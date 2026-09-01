<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyTransaction;
use App\Services\LicenseFeatureService;
use App\Services\LoyaltyPointsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoyaltySettingController extends Controller
{
    public function __construct(
        private readonly LicenseFeatureService $license,
        private readonly LoyaltyPointsService $loyalty,
    ) {
    }

    public function get_loyalty_setting()
    {
        try {
            if ($this->license->isBronzeOrBelow()) {
                return $this->license->silverRequiredResponse();
            }

            return $this->loyalty->seedDefaultSettings();
        } catch (\Throwable $th) {
            \Log::error('get_loyalty_setting: ' . $th->getMessage());

            return response()->json([
                'success' => false,
                'message' => $this->resolveLoyaltyErrorMessage($th),
            ], 500);
        }
    }

    public function update_loyalty_setting(Request $request)
    {
        if ($this->license->isBronzeOrBelow()) {
            return $this->license->silverRequiredResponse();
        }

        try {
            $validated = $request->validate([
                'description' => 'nullable|string|max:4000',
                'is_active' => 'required|boolean',
                'earn_on_purchase' => 'required|boolean',
                'earn_on_renewal' => 'required|boolean',
                'earn_on_deposit' => 'required|boolean',
                'earn_on_referral' => 'required|boolean',
                'redeem_enabled' => 'required|boolean',
                'purchase_points_per_1000_toman' => 'required|integer|min:0|max:100000',
                'renewal_points' => 'required|integer|min:0|max:1000000',
                'deposit_points_per_1000_toman' => 'required|integer|min:0|max:100000',
                'referral_signup_points' => 'required|integer|min:0|max:1000000',
                'toman_per_point' => 'required|integer|min:1|max:1000000',
                'min_redeem_points' => 'required|integer|min:0|max:10000000',
                'max_redeem_percent' => 'required|integer|min:0|max:100',
            ]);

            $settings = $this->loyalty->seedDefaultSettings();
            $settings->fill($validated);
            $settings->save();

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'داده‌های ورودی نامعتبر است.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            \Log::error('update_loyalty_setting: ' . $th->getMessage());

            return response()->json([
                'success' => false,
                'message' => $this->resolveLoyaltyErrorMessage($th),
            ], 500);
        }
    }

    private function resolveLoyaltyErrorMessage(\Throwable $th): string
    {
        $message = $th->getMessage();
        if (str_contains($message, 'loyalty_settings') || str_contains($message, "doesn't exist")) {
            return 'جدول باشگاه مشتریان در دیتابیس وجود ندارد. ابتدا php artisan migrate --force را اجرا کنید.';
        }

        return 'خطا در ذخیره تنظیمات باشگاه مشتریان.';
    }

    public function check_loyalty_is_active(): bool
    {
        return $this->loyalty->isActive();
    }
}
