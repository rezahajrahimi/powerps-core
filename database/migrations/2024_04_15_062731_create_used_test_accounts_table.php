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
        Schema::create('used_test_accounts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('test_account_id')->unsigned();
            $table->bigInteger('account_id');

            $table->timestamps();
            $table
                ->foreign('test_account_id')
                ->references('id')
                ->on('test_accounts')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('used_test_accounts');
    }
};
