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
        Schema::create('channel_lock_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->default('main');
            $table->string('alias_name', 100)->default('برای استفاده از ربات می بایست در کانال های زیر عضو باشید.');
            $table->integer('level')->unsigned()->nullable()->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_lock_menu_items');
    }
};
