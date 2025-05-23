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
        Schema::table('account_ballances', function (Blueprint $table) {
            $table->double('account_ballance_in_dollar', 15, 2)->nullable()->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_ballances', function (Blueprint $table) {
            //
        });
    }
};
