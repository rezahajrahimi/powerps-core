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
        Schema::table('inbound_templates', function (Blueprint $table) {
            $table->string('listen', 100)->nullable()->after('port');
            $table->json('server_info')->nullable()->after('stream_settings');
            $table->string('config_type', 50)->default('custom')->after('server_info');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbound_templates', function (Blueprint $table) {
            $table->dropColumn(['listen', 'server_info', 'config_type']);
        });
    }
};
