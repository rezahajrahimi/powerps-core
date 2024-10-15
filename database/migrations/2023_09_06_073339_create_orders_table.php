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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->unsigned();
            $table->bigInteger('product_categories_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->String('order_number')->unique();;
            $table->bigInteger('price');
            $table
                ->foreign('product_categories_id')
                ->references('id')
                ->on('product_categories')->onDelete('cascade');
            $table
                ->foreign('product_id')
                ->references('id')
                ->on('products')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
