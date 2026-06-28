<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_intents', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('product_category_id');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE purchase_intents MODIFY stage VARCHAR(50) NOT NULL DEFAULT 'package_selected'"
            );
        }
    }

    public function down(): void
    {
        Schema::table('purchase_intents', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
    }
};
