<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('upsell_category_id')->nullable()->after('allowed_user_group_ids');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('upsell_category_id');
        });
    }
};
