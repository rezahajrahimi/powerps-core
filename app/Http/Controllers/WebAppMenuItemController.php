<?php

namespace App\Http\Controllers;

use App\Models\WebAppMenuItem;
use App\Http\Requests\StoreWebAppMenuItemRequest;
use App\Http\Requests\UpdateWebAppMenuItemRequest;

class WebAppMenuItemController extends Controller
{
    private function defaultMenuItems(): array
    {
        return [
            [
                'key' => 'buy_subscription',
                'title' => 'خرید اشتراک',
                'subtitle' => 'خرید کانفیگ جدید',
                'is_active' => true,
                'position' => 1,
            ],
            [
                'key' => 'subscription_history',
                'title' => 'سابقه خرید',
                'subtitle' => 'مشاهده کانفیگ های خریداری شده',
                'is_active' => true,
                'position' => 2,
            ],
            [
                'key' => 'wallet',
                'title' => 'کیف پول',
                'subtitle' => 'مشاهده موجودی حساب و افزایش آن',
                'is_active' => true,
                'position' => 3,
            ],
            [
                'key' => 'how_to_use',
                'title' => 'آموزش استفاده',
                'subtitle' => 'نحوه از استفاده از کانفیگ خریداری شده و پاسخ به سوالات متداول',
                'is_active' => true,
                'position' => 4,
            ],
            [
                'key' => 'support',
                'title' => 'پشتیبانی',
                'subtitle' => 'ارتباط با پشتیبان',
                'is_active' => true,
                'position' => 5,
            ],
            [
                'key' => 'trial_account',
                'title' => 'اکانت آزمایشی',
                'subtitle' => 'دریافت اکانت آزمایشی برای تست سرویس ما',
                'is_active' => true,
                'position' => 6,
            ],
            [
                'key' => 'gift_card',
                'title' => 'گیفت کارت',
                'subtitle' => 'ثبت کد گیفت کارت',
                'is_active' => true,
                'position' => 7,
            ],
            [
                'key' => 'app_download',
                'title' => 'دانلود برنامه',
                'subtitle' => 'دانلود برنامه ها و اپلیکیشن های مورد نیاز',
                'is_active' => true,
                'position' => 8,
            ],
            [
                'key' => 'referral',
                'title' => 'کسب درآمد',
                'subtitle' => 'دریافت لینک دعوت و اشتراک گذاری آن',
                'is_active' => true,
                'position' => 9,
            ],
        ];
    }

    private function ensureDefaultMenuItems(): void
    {
        if (WebAppMenuItem::count() > 0) {
            return;
        }

        foreach ($this->defaultMenuItems() as $item) {
            WebAppMenuItem::create($item);
        }
    }

    public function seed()
    {
        $this->ensureDefaultMenuItems();

        return $this->get_web_app_menu_items();
    }

    public function get_web_app_menu_items()
    {
        try {
            $this->ensureDefaultMenuItems();

            return WebAppMenuItem::orderBy('position', 'asc')->get();
        } catch (\Throwable $th) {
            \Log::info("get_web_app_menu_items: $th");

            return response()->json(null, 500);
        }
    }

    public function get_all_active_web_app_menu_items()
    {
        try {
            $this->ensureDefaultMenuItems();

            return WebAppMenuItem::where('is_active', true)
                ->orderBy('position', 'asc')
                ->get();
        } catch (\Throwable $th) {
            \Log::info("get_all_active_web_app_menu_items: $th");

            return response()->json($this->defaultMenuItems(), 200);
        }
    }

    public function update_web_app_menu_item_by_key(Request $request){
        try {
            $webAppMenuItem = WebAppMenuItem::where('key', $request->key)->first();
            $webAppMenuItem->is_active = $request->is_active;
            $webAppMenuItem->position = $request->position;
            $webAppMenuItem->title = $request->title;
            $webAppMenuItem->subtitle = $request->subtitle;
            $webAppMenuItem->save();
            return $webAppMenuItem;
        } catch (\Throwable $th) {
            \Log::info("update_web_app_menu_item_by_key: $th");
            return response()->json(null, 500);
        }
    }
    public function change_web_app_menu_item_status(Request $request){
        try {
            $webAppMenuItem = WebAppMenuItem::where('key', $request->key)->first();
            $webAppMenuItem->is_active = !$webAppMenuItem->is_active;
            $webAppMenuItem->save();
            return $webAppMenuItem;
        } catch (\Throwable $th) {
            \Log::info("change_web_app_menu_item_status: $th");
            return response()->json(null, 500);
        }
    }
    public function change_web_app_menu_item_position(Request $request){
        try {
            $webAppMenuItem = WebAppMenuItem::where('key', $request->key)->first();
            $checkAvilabel = MainMenuItem::where('position', $request->position)->first();
            if ($checkAvilabel != null) {
                return false;
            }
            $webAppMenuItem->position = $request->position;
            $webAppMenuItem->save();
            return $webAppMenuItem;
        } catch (\Throwable $th) {
            \Log::info("change_web_app_menu_item_position: $th");
            return response()->json(null, 500);
        }
    }
}
