<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('queue:work --stop-when-empty')
            ->everyMinute();

        $schedule->call('App\Http\Controllers\CronJobController@execute_send_lass_there_than_3_days')
            ->name('cron.send-less-than-3-days')
            ->dailyAt('12:00');

        $schedule->call('App\Http\Controllers\CronJobController@execute_send_expired_products')
            ->name('cron.send-expired-products')
            ->everyFiveMinutes();

        $schedule->call('App\Http\Controllers\CronJobController@execute_send_useage_more_than_85_percent')
            ->name('cron.send-usage-more-than-85-percent')
            ->everyFifteenMinutes();

        $schedule->call('App\Http\Controllers\CronJobController@execute_create_daily_backup')
            ->name('cron.create-daily-backup')
            ->everyThreeHours();

        $schedule->call('App\Http\Controllers\CronJobController@calculate_product_category_price_by_tether')
            ->name('cron.calculate-price-by-tether')
            ->everyFiveMinutes();

        $schedule->call('App\Http\Controllers\CronJobController@calculate_product_category_price_in_dollar_by_toman')
            ->name('cron.calculate-price-in-dollar-by-toman')
            ->everyFiveMinutes();

        $schedule->call('App\Http\Controllers\CronJobController@execute_confirm_pending_swappay')
            ->name('cron.swappay-confirm-pending')
            ->everyFiveMinutes();

        $schedule->call('App\Http\Controllers\CronJobController@execute_auto_delete_expired_configs')
            ->name('cron.auto-delete-expired-configs')
            ->dailyAt('08:02');

        $schedule->call('App\Http\Controllers\CronJobController@clear_laravel_log')
            ->name('cron.clear-laravel-log')
            ->everyTwoHours();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
