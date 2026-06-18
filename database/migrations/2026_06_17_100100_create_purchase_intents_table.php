<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_intents', function (Blueprint $table) {
            $table->id();
            $table->string('account_id');
            $table->unsignedBigInteger('product_category_id');
            $table->enum('stage', ['package_selected', 'insufficient_balance', 'payment_started'])->default('package_selected');
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'completed_at']);
            $table->index(['stage', 'reminder_count', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_intents');
    }
};
