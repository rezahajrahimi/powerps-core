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
        Schema::create('inbounds', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('proxy_id')->unsigned();
            $table->string('name', 100);
            $table->string('data', 100);
            $table
                ->boolean('is_active')
                ->nullable()
                ->default(true);

            $table->timestamps();
            $table
                ->foreign('proxy_id')
                ->references('id')
                ->on('proxies')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbounds');
    }
};
