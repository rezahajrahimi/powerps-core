<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\ServiceProvider;

class CarbonServiceProvider extends ServiceProvider
{
    public function register()
    {
        Carbon::macro('getDaysFromStartOfWeek', function ($weekStartsAt = null) {
            return $this->copy()->startOfWeek($weekStartsAt)->diffInDays($this);
        });

        CarbonImmutable::macro('getDaysFromStartOfWeek', function ($weekStartsAt = null) {
            return $this->startOfWeek($weekStartsAt)->diffInDays($this);
        });
    }
} 