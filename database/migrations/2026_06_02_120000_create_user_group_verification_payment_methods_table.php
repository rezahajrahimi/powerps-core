<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_group_verification_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->boolean('is_verified');
            $table->string('payment_key', 50);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_group_id', 'is_verified', 'payment_key'], 'ugvpm_group_verified_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_group_verification_payment_methods');
    }
};
