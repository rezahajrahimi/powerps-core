<?php

namespace App\Http\Controllers;

use App\Models\ReferralSetting;
use Illuminate\Http\Request;

class ReferralSettingController extends Controller
{
    public function get_referral_setting()
    {
        try {
            $referralSetting = ReferralSetting::first();
            if ($referralSetting != null) {
                return $referralSetting;
            } else {
                // creater referral setting
                $referralSetting = new ReferralSetting();
                $referralSetting->description = 'با ارسال این لینک به دوستان خود، با هر بار واریزی آنها، امتیاز بگیرید.';
                $referralSetting->visit_card_text = '🔥فروش پروکسی اختصاصی با بروزترین پروتکل ها \r\n 🏐 قابل استفاده در تلگرام و تمامی دستگاه ها به عنوان فیلترشکن \r\n ⏰ تجهیز شده با کانکشن هوشمند (بیش از 20 سرور برای هر کاربر) \r\n 📬فاقد هر گونه تبلیغات! \r\n ✔️پشتیبانی ۲۴/۷ \r\n ♾بدون قطعی و کندی سرعت \r\n💰 خرید: \r\n';

                $referralSetting->referral_percent = 10.0;
                $referralSetting->is_active = true;

                $referralSetting->save();
                return $referralSetting;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable get_referral_setting: $th");
            return response()->json(null, 500);
        }
    }
    public function update_referral_setting(Request $request)
    {
        try {
            $validated = $request->validate([
                'description' => 'required|string|max:4000',
                'visit_card_text' => 'required|string|max:4000',
                'referral_percent' => 'required|numeric|min:0|max:100',
                'is_active' => 'required|boolean',
            ]);

            $referralSetting = ReferralSetting::first();
            if ($referralSetting != null) {
                $referralSetting->description = $validated['description'];
                $referralSetting->visit_card_text = $validated['visit_card_text'];
                $referralSetting->referral_percent = $validated['referral_percent'];
                $referralSetting->is_active = $validated['is_active'];
                $referralSetting->update();

                return $referralSetting;
            } else {
                return response()->json(null, 404);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable update_referral_setting: $th");
            return response()->json(null, 500);
        }
    }
    public function check_referral_setting_is_active()
    {
        try {
            $referralSetting = ReferralSetting::first();
            if ($referralSetting != null) {
                if ($referralSetting->is_active == true || $referralSetting->is_active == 1) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable check_referral_setting: $th");
            return response()->json(null, 500);
        }
    }
    public function get_referral_setting_description()
    {
        try {
            $referralSetting = ReferralSetting::first();
            if ($referralSetting != null) {
                return $referralSetting->description;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable get_referral_setting_description: $th");
            return response()->json(null, 500);
        }
    }
    public function get_referral_setting_visit_card_text()
    {
        try {
            $referralSetting = ReferralSetting::first();
            if ($referralSetting != null) {
                return $referralSetting->visit_card_text;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable get_referral_setting_description: $th");
            return response()->json(null, 500);
        }
    }
    public function get_referral_setting_referral_percent()
    {
        try {
            $referralSetting = ReferralSetting::first();
            if ($referralSetting != null) {
                return $referralSetting->referral_percent;
            } else {
                return 0;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable get_referral_setting_referral_percent: $th");
            return response()->json(null, 500);
        }
    }
    public function change_referral_setting_activity()
    {
        try {
            $referralSetting = ReferralSetting::first();
            if ($referralSetting != null) {
                return $referralSetting->is_active;
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable get_referral_setting_is_active: $th");
            return response()->json(null, 500);
        }
    }
}
