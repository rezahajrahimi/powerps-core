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
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('discount')->unsigned()->default(1000);
            $table->integer('count_of_use')->unsigned()->default(1);
            $table->integer('count_of_use_per_user')->unsigned()->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
