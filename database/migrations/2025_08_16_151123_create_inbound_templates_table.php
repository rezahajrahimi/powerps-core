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
        Schema::create('inbound_templates', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pannel_id')->unsigned();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->text('inbound_config'); // JSON configuration
            $table->string('protocol', 50); // vless, vmess, trojan
            $table->integer('port')->unsigned();
            $table->text('stream_settings')->nullable(); // JSON stream settings
            $table->text('settings')->nullable(); // JSON settings
            $table->boolean('is_active')->default(true);
            $table->bigInteger('created_by')->unsigned()->nullable(); // user who created this template
            $table->timestamps();
            
            $table->foreign('pannel_id')->references('id')->on('pannels')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['pannel_id', 'protocol']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_templates');
    }
};
