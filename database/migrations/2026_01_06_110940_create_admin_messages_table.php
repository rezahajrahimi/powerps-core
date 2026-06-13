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
        Schema::create('admin_messages', function (Blueprint $table) {
            $table->id();
            $table->text('message')->nullable();
            $table->string('image_path')->nullable();
            $table->string('type')->default('text'); // text, photo
            $table->string('status')->default('pending'); // pending, processing, completed, cancelled
            $table->integer('total_users')->default(0);
            $table->integer('sent_users')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_messages');
    }
};
