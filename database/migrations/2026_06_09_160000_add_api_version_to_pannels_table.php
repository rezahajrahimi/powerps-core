<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pannels', function (Blueprint $table) {
            $table->string('api_version', 10)->nullable()->default('v3')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('pannels', function (Blueprint $table) {
            $table->dropColumn('api_version');
        });
    }
};
