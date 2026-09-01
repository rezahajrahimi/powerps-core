<?php

namespace App\Http\Controllers;

use App\Models\AdvancedSetting;
use Illuminate\Http\Request;

class AdvancedSettingController extends Controller
{
    public function advancedSetting(): AdvancedSetting
    {
        try {
            // return first advanced setting or create a new one
            $advancedSetting = AdvancedSetting::first();
            if ($advancedSetting == null) {
                $advancedSetting = new AdvancedSetting();
                $advancedSetting->save();
            }

            return $advancedSetting;
        } catch (\Throwable $th) {
            \Log::info("advancedSetting: $th");

            return response()->json(null, 500);
        }
    }
    public function update_advanced_setting(Request $request): bool
    {
        try {
            // check account license
            $authCntrl = new AuthController();
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                return false;
            }

            $advancedSetting = $this->advancedSetting();
            $key = $request->key;
            $value = $request->value;
            if ($key == 'bot_show_configs_by_panels_category') {
                $advancedSetting->bot_show_configs_by_panels_category = $value;
            } elseif ($key == 'bot_auto_set_price_by_dollar_price') {
                $advancedSetting->bot_auto_set_price_by_dollar_price = $value;
            } elseif ($key == 'bot_calculate_product_category_price_in_dollar_by_toman') {
                $advancedSetting->bot_calculate_product_category_price_in_dollar_by_toman = $value;
            } elseif ($key == 'bot_show_one_row_config') {
                $advancedSetting->bot_show_one_row_config = $value;
            }

            $advancedSetting->update();
            return true;
        } catch (\Throwable $th) {
            \Log::info("update_advanced_setting: $th");

            return response()->json(null, 500);
        }
    }
    // public function get_bot_show_configs_by_panels_category(): bool
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         return $advancedSetting->bot_show_configs_by_panels_category;
    //     } catch (\Throwable $th) {
    //         \Log::info("get_bot_show_configs_by_panels_category: $th");

    //         return response()->json(null, 500);
    //     }
    // }
    // public function change_bot_show_configs_by_panels_category($value): bool
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         $advancedSetting->bot_show_configs_by_panels_category = $value;
    //         $advancedSetting->save();

    //         return true;
    //     } catch (\Throwable $th) {
    //         \Log::info("change_bot_show_configs_by_panels_category: $th");

    //         return response()->json(null, 500);
    //     }
    // }
    // public function get_bot_auto_set_price_by_dollar_price(): bool
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         return $advancedSetting->bot_auto_set_price_by_dollar_price;
    //     } catch (\Throwable $th) {
    //         \Log::info("get_bot_auto_set_price_by_dollar_price: $th");

    //         return response()->json(null, 500);
    //     }
    // }
    // public function change_bot_auto_set_price_by_dollar_price($value): bool
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         $advancedSetting->bot_auto_set_price_by_dollar_price = $value;
    //         $advancedSetting->save();

    //         return true;
    //     } catch (\Throwable $th) {
    //         \Log::info("change_bot_auto_set_price_by_dollar_price: $th");

    //         return response()->json(null, 500);
    //     }
    // }
    // public function get_bot_show_web_app_link_in_telegram_for_all_users()
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         return $advancedSetting->bot_show_web_app_link_in_telegram_for_all_users;
    //     } catch (\Throwable $th) {
    //         \Log::info("get_bot_show_web_app_link_in_telegram_for_all_users: $th");

    //         return response()->json(null, 500);
    //     }
    // }
    // public function change_bot_show_web_app_link_in_telegram_for_all_users($value): bool
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         $advancedSetting->bot_show_web_app_link_in_telegram_for_all_users = $value;
    //         $advancedSetting->save();

    //         return true;
    //     } catch (\Throwable $th) {
    //         \Log::info("change_bot_show_web_app_link_in_telegram_for_all_users: $th");

    //         return response()->json(null, 500);
    //     }
    // }
    // public function get_bot_calculate_product_category_price_in_dollar_by_toman(): bool
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         return $advancedSetting->bot_calculate_product_category_price_in_dollar_by_toman;
    //     } catch (\Throwable $th) {
    //         \Log::info("get_bot_calculate_product_category_price_in_dollar_by_toman: $th");

    //         return response()->json(null, 500);
    //     }
    // }
    // public function change_bot_calculate_product_category_price_in_dollar_by_toman($value): bool
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         $advancedSetting->bot_calculate_product_category_price_in_dollar_by_toman = $value;
    //         $advancedSetting->save();

    //         return true;
    //     } catch (\Throwable $th) {
    //         \Log::info("change_bot_calculate_product_category_price_in_dollar_by_toman: $th");

    //         return response()->json(null, 500);
    //     }
    // }
    // public function get_bot_show_one_row_config(): bool
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         return $advancedSetting->bot_show_one_row_config;
    //     } catch (\Throwable $th) {
    //         \Log::info("get_bot_show_one_row_config: $th");

    //         return false;
    //     }
    // }
    // public function change_bot_show_one_row_config($value): bool
    // {
    //     try {
    //         $advancedSetting = $this->advancedSetting();
    //         $advancedSetting->bot_show_one_row_config = $value;
    //         $advancedSetting->save();

    //         return true;
    //     } catch (\Throwable $th) {
    //         \Log::info("change_bot_show_one_row_config: $th");

    //         return false;        }
    // }
}
