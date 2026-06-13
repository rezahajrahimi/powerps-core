<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** product_categories
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_categories_id')->unsigned();
            $table->bigInteger('account_id')->unsigned()->nullable();
            $table->text('configs')->nullable();
            $table->text('subscription_link')->nullable();
            $table->text('panel_link')->nullable();
            $table->boolean('isActive')->default(true);
            $table
                ->foreign('product_categories_id')
                ->references('id')
                ->on('product_categories')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
