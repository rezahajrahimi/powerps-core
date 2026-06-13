<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('bot_name')->default('@v2ray_vip_fast');
            $table->bigInteger('admin_id')->default(00);

            $table->string('bot_token')->default('yukkbihb275Ui1LKeGpXSVw');
            $table->string('panel_address')->default('127.0.0.1:8000/admin');
            $table->string('welcome_message')->default('به ربات ما خوش آمدید.');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
