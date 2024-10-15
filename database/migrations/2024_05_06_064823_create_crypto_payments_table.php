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
        Schema::create('crypto_payments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable()->default('nowpayments');
            $table->string('api_key', 255)->nullable();
            $table->string('env', 255)->nullable()->default('live');
            $table->string('callback_url', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('ipn_callback_url', 255)->nullable();
            $table->string('success_url', 255)->nullable();
            $table->string('cancel_url', 255)->nullable();
            $table->string('partially_paid_url', 255)->nullable();
            $table->boolean('is_fixed_rate')->nullable()->default(true);
            $table->boolean('is_fee_paid_by_user')->nullable()->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_payments');
    }
};
