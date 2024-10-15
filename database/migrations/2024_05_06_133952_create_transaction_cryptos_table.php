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
        Schema::create('transaction_cryptos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->unsigned();
            $table->string('username');
            $table->double('amount_dollar', 15, 2)->nullable()->default(0.00);
            $table->bigInteger('crypto_payment_id')->unsigned();

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
                ->foreign('crypto_payment_id')
                ->references('id')
                ->on('crypto_payments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_cryptos');
    }
};
