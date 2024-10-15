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
        Schema::table('agent_permissons', function (Blueprint $table) {
           $table->double('traffic_limitation_tb', 15, 2)->nullable()->default(10.00);
           $table->integer('product_limitation')->unsigned()->nullable()->default(1000);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_permissons', function (Blueprint $table) {
            //
        });
    }
};
