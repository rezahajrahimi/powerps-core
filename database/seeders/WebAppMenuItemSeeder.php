<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WebAppMenuItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\WebAppMenuItem::factory()->create([
            'key' => 'buy_subscription',
            'title' => 'خرید اشتراک',
            'subtitle' => 'خرید کانفیگ جدید',
            'is_active' => true,
            'position' => 1
        ]);
        \App\Models\WebAppMenuItem::factory()->create([
            'key' => 'subscription_history',
            'title' => 'سابقه خرید',
            'subtitle' => 'مشاهده کانفیگ های خریداری شده',
            'is_active' => true,
            'position' => 2
        ]);
        \App\Models\WebAppMenuItem::factory()->create([
            'key' => 'wallet',
            'title' => 'کیف پول',
            'subtitle' => 'مشاهده موجودی حساب و افزایش آن',
            'is_active' => true,
            'position' => 3
        ]);
        \App\Models\WebAppMenuItem::factory()->create([
            'key' => 'how_to_use',
            'title' => 'آموزش استفاده',
            'subtitle' => 'نحوه از استفاده از کانفیگ خریداری شده و پاسخ به سوالات متداول',
            'is_active' => true,
            'position' => 4
        ]);
        \App\Models\WebAppMenuItem::factory()->create([
            'key' => 'support',
            'title' => 'پشتیبانی',
            'subtitle' => 'ارتباط با پشتیبان',
            'is_active' => true,
            'position' => 5
        ]);
        \App\Models\WebAppMenuItem::factory()->create([
            'key' => 'trial_account',
            'title' => 'اکانت آزمایشی',
            'subtitle' => 'دریافت اکانت آزمایشی برای تست سرویس ما',
            'is_active' => true,
            'position' => 6
        ]);
        \App\Models\WebAppMenuItem::factory()->create([
            'key' => 'gift_card',
            'title' => 'گیفت کارت',
            'subtitle' => 'ثبت کد گیفت کارت',
            'is_active' => true,
            'position' => 7
        ]);
        \App\Models\WebAppMenuItem::factory()->create([
            'key' => 'app_download',
            'title' => 'دانلود برنامه',
            'subtitle' => 'دانلود برنامه ها و اپلیکیشن های مورد نیاز',
            'is_active' => true,
            'position' => 8
        ]);
        \App\Models\WebAppMenuItem::factory()->create([
            'key' => 'referral',
            'title' => 'کسب درآمد',
            'subtitle' => 'دریافت لینک دعوت و اشتراک گذاری آن',
            'is_active' => true,
            'position' => 9
        ]);
    }
}
