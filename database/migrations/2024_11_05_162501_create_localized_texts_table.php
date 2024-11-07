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
        Schema::create('localized_texts', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('text');
            $table->string('locale', 5)->default('fa');
            $table->string('group', 100)->default('general');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('localized_texts');
    }
};
