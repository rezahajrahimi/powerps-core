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
        Schema::create('test_account_menus', function (Blueprint $table) {
            $table->id();
            $table->string('approved', 100)->default('اکانت آزمایشی شما با موفقیت فعال شد.');
            $table->string('denied', 100)->default('اکانت آزمایشی از قبل برای شما فعال شده است، می توانید از سابقه خرید به اطلاعات آن دسترسی داشته باشید.');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_account_menus');
    }
};
