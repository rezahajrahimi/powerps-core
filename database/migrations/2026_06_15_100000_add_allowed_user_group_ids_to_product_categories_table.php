<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->json('allowed_user_group_ids')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('allowed_user_group_ids');
        });
    }
};
