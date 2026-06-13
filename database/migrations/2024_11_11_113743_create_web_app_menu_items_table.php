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
        Schema::create('web_app_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('title');
            $table->string('subtitle');
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('position')->default(0)->unique();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_app_menu_items');
    }
};
