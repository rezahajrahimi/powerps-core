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
        Schema::create('referral_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_user_id');
            $table->integer('amount')->unsigned()->default(0);
            $table->timestamps();
            $table->foreign('referral_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_wallets');
    }
};
