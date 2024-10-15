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
        Schema::create('proxies', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pannel_id')->unsigned();
            $table
                ->string('type', 100)
                ->nullable()
                ->default('vless');
            $table
                ->boolean('is_active')
                ->nullable()
                ->default(true);
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
        Schema::dropIfExists('proxies');
    }
};
