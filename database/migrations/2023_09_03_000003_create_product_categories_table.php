<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** service_types
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pannel_id')->unsigned();
            $table->string('category_name', 100);
            $table
                ->bigInteger('price')
                ->unsigned()
                ->nullable()
                ->default(0);
            $table
                ->integer('expire_day')
                ->unsigned()
                ->default(30);
                $table->double('volume', 15, 2)->nullable()->default(0.5);


            $table->timestamps();
            $table
                ->foreign('pannel_id')
                ->references('id')
                ->on('pannels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
