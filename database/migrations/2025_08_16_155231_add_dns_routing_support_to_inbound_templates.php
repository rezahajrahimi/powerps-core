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
            $table->json('dns_info')->nullable()->after('server_info');
            $table->json('routing_info')->nullable()->after('dns_info');
            $table->text('remarks')->nullable()->after('routing_info');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbound_templates', function (Blueprint $table) {
            $table->dropColumn(['dns_info', 'routing_info', 'remarks']);
        });
    }
};
