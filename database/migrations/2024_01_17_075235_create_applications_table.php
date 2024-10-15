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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('download_link')->nullable();
            $table->string('file_src')->nullable();
            $table->string('os')->nullable()->default('android');
            $table->text('how_to_use')->nullable();
            $table->text('description')->nullable();
            $table->text('youtube_link')->nullable();
            $table
                ->boolean('is_active')
                ->nullable()
                ->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
