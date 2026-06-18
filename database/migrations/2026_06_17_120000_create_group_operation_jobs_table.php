<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_operation_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->unsignedBigInteger('panel_id');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_configs')->default(0);
            $table->unsignedInteger('processed_configs')->default(0);
            $table->json('success_items')->nullable();
            $table->json('failed_items')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_operation_jobs');
    }
};
