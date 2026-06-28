<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->string('phone_number', 32)->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('bot_users', function (Blueprint $table) {
            $table->dropColumn('phone_number');
        });
    }
};
