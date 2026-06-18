<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('segment_type');
            $table->json('segment_params')->nullable();
            $table->text('message');
            $table->string('image_path')->nullable();
            $table->string('cta_type')->nullable();
            $table->string('cta_payload')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'processing', 'completed', 'failed'])->default('draft');
            $table->unsignedInteger('total_users')->default(0);
            $table->unsignedInteger('sent_users')->default(0);
            $table->json('recipient_ids')->nullable();
            $table->json('sent_ids')->nullable();
            $table->json('failed_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
