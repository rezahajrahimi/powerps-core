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
        Schema::create('used_gift_cards', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('gift_cards_id')->unsigned();
            $table->bigInteger('account_id');

            $table->timestamps();

            $table
                ->foreign('gift_cards_id')
                ->references('id')
                ->on('gift_cards')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('used_gift_cards');
    }
};
