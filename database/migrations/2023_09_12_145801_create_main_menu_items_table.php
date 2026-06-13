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
        Schema::create('main_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('alias_name');
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('position')->default(0)->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('main_menu_items');
    }
};
