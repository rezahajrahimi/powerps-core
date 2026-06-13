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
        Schema::create('test_accounts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pannel_id')->unsigned();
            $table->double('volume', 15, 2)->nullable()->default(1);
            $table
                ->integer('expire_day')
                ->unsigned()
                ->default(30);

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
        Schema::dropIfExists('test_accounts');
    }
};
