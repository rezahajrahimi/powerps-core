<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('advanced_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('bot_show_configs_by_panels_category')->default(false);
            $table->boolean('bot_auto_set_price_by_dollar_price')->default(false);
            $table->boolean('bot_show_web_app_link_in_telegram_for_all_users')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advanced_settings');
    }
};
