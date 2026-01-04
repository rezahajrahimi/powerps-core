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
        if (!Schema::hasTable('cron_logs')) {
            Schema::create('cron_logs', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('cron_id')->unsigned();
                $table->bigInteger('product_id')->unsigned();
                $table->timestamps();
                $table->foreign('cron_id')->references('id')->on('cron_jobs')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_logs');
    }
};
