<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $accountDetailsDefault = json_encode([
            ['type' => 'bold', 'text' => 'اطلاعات حساب شما:'],
            ['type' => 'newline'],
            ['type' => 'text', 'text' => 'نام کاربری: {username}'],
            ['type' => 'newline'],
            ['type' => 'text', 'text' => 'نام: {name} {last_name}'],
            ['type' => 'newline'],
            ['type' => 'text', 'text' => 'آیدی عددی: {account_id}'],
            ['type' => 'newline'],
            ['type' => 'text', 'text' => 'موجودی کیف پول: {balance}'],
            ['type' => 'newline'],
            ['type' => 'text', 'text' => 'موجودی دلاری: {balance_in_dollar}'],
            ['type' => 'newline'],
            ['type' => 'text', 'text' => 'موجودی کیف همکاری: {referral_balance}'],
            ['type' => 'newline'],
            ['type' => 'text', 'text' => 'امتیاز باشگاه مشتریان: {loyalty_balance}'],
        ], JSON_UNESCAPED_UNICODE);

        DB::table('custom_texts')
            ->where('key', 'action.account.details')
            ->whereNull('custom_text')
            ->update([
                'default_text' => $accountDetailsDefault,
                'description' => 'متن اطلاعات حساب شما: - پارامترها: {username} {name} {last_name} {account_id} {balance} {balance_in_dollar} {referral_balance} {loyalty_balance}',
                'updated_at' => now(),
            ]);

        $newKeys = [
            [
                'key' => 'action.account.additional_options.loyalty_history',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'تاریخچه امتیاز ⭐'],
                ], JSON_UNESCAPED_UNICODE),
                'custom_text' => null,
                'description' => 'دکمه تاریخچه امتیاز باشگاه مشتریان',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'action.account.loyalty_history.title',
                'default_text' => json_encode([
                    ['type' => 'bold', 'text' => 'باشگاه مشتریان'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'موجودی امتیاز شما: {balance}'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'ارزش هر امتیاز: {toman_per_point} تومان'],
                    ['type' => 'newline'],
                    ['type' => 'text', 'text' => 'آخرین فعالیت‌ها:'],
                ], JSON_UNESCAPED_UNICODE),
                'custom_text' => null,
                'description' => 'عنوان تاریخچه امتیاز — پارامترها: {balance} {toman_per_point}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'action.account.loyalty_history.no_records',
                'default_text' => json_encode([
                    ['type' => 'text', 'text' => 'هنوز هیچ امتیازی برای شما ثبت نشده است.'],
                ], JSON_UNESCAPED_UNICODE),
                'custom_text' => null,
                'description' => 'متن خالی بودن تاریخچه امتیاز',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($newKeys as $row) {
            if (! DB::table('custom_texts')->where('key', $row['key'])->exists()) {
                DB::table('custom_texts')->insert($row);
            }
        }
    }

    public function down(): void
    {
        DB::table('custom_texts')->whereIn('key', [
            'action.account.additional_options.loyalty_history',
            'action.account.loyalty_history.title',
            'action.account.loyalty_history.no_records',
        ])->delete();
    }
};
