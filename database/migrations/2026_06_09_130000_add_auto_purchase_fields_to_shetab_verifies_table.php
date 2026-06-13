<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shetab_verifies', function (Blueprint $table) {
            $table->unsignedBigInteger('product_category_id')->nullable()->after('user_id');
            $table->string('base_amount')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('shetab_verifies', function (Blueprint $table) {
            $table->dropColumn(['product_category_id', 'base_amount']);
        });
    }
};
