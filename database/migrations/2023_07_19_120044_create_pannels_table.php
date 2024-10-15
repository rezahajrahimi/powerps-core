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
        Schema::create('pannels', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100)->default('other');
            $table->string('username', 100)->nullable()->default('admin');
            $table->string('password', 100)->nullable()->default('123456');
            $table->string('token', 255)->nullable()->default('Bearer ');
            $table->string('location', 100)->nullable();
            $table->string('url_port', 255)->nullable();
            $table->string('admin_url', 255)->nullable();
            $table->integer('capacity')->unsigned()->nullable()->default(1000);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pannels');
    }
};
