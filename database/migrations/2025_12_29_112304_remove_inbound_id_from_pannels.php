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
            $table->dropColumn('inbound_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pannels', function (Blueprint $table) {
            $table->unsignedBigInteger('inbound_id')->nullable()->after('user_link');
        });
    }
};
