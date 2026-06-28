<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('earn_on_purchase')->default(true);
            $table->boolean('earn_on_renewal')->default(true);
            $table->boolean('earn_on_deposit')->default(true);
            $table->boolean('earn_on_referral')->default(true);
            $table->boolean('redeem_enabled')->default(true);
            $table->unsignedInteger('purchase_points_per_1000_toman')->default(10);
            $table->unsignedInteger('renewal_points')->default(50);
            $table->unsignedInteger('deposit_points_per_1000_toman')->default(5);
            $table->unsignedInteger('referral_signup_points')->default(100);
            $table->unsignedInteger('toman_per_point')->default(10);
            $table->unsignedInteger('min_redeem_points')->default(100);
            $table->unsignedTinyInteger('max_redeem_percent')->default(50);
            $table->timestamps();
        });

        Schema::create('loyalty_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20);
            $table->string('event', 30)->nullable();
            $table->bigInteger('points');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_wallets');
        Schema::dropIfExists('loyalty_settings');
    }
};
