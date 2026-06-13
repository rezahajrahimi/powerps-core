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
        Schema::table('inbounds', function (Blueprint $table) {
            // Change data field to text to store larger JSON data
            $table->text('data')->change();
            
            // Change name field to text for longer names
            $table->text('name')->change();
            
            // Add new fields for better Sanaei support
            $table->integer('port')->unsigned()->nullable()->after('name');
            $table->string('protocol', 50)->nullable()->after('port');
            $table->text('settings')->nullable()->after('protocol');
            $table->text('stream_settings')->nullable()->after('settings');
            $table->string('tag', 100)->nullable()->after('stream_settings');
            $table->json('client_stats')->nullable()->after('tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbounds', function (Blueprint $table) {
            $table->string('data', 100)->change();
            $table->string('name', 100)->change();
            $table->dropColumn(['port', 'protocol', 'settings', 'stream_settings', 'tag', 'client_stats']);
        });
    }
};
