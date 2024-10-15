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
        Schema::table('pannels', function (Blueprint $table) {
            $table->string('secret_code', 100)->nullable();
            $table->text('cookie_session')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pannels', function (Blueprint $table) {
            //
        });
    }
};
