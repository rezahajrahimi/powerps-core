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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->unsigned();
            $table->string('username');
            $table->bigInteger('amount');
            $table->bigInteger('payment_type_id')->unsigned();

            $table
                ->boolean('confirmed')
                ->nullable()
                ->default(false);
            $table
                ->string('recipe_number')
                ->nullable()
                ->default('0');
            $table->timestamps();

            $table
                ->foreign('payment_type_id')
                ->references('id')
                ->on('payment_types')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
