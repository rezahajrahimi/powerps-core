<?php

namespace App\Providers;
use App\Services\ImageDetectText;
use App\Services\TelegramBot;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (class_exists(\sbamtr\LaravelSourceEncrypter\SourceEncryptServiceProvider::class)) {
            $this->app->register(\sbamtr\LaravelSourceEncrypter\SourceEncryptServiceProvider::class);
        }

        // create a singleton telegram_bot.
        $this->app->singleton('telegram_bot',function(){
            return new TelegramBot();
        });

        // create a singleton image_detect_text.
        $this->app->singleton('image_detect_text',function(){
            return new ImageDetectText();
        });


    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
