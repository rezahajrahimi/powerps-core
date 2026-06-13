<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_permissons', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_permissons', 'product_count_baseline')) {
                $table->unsignedInteger('product_count_baseline')->default(0)->after('product_limitation');
            }
            if (! Schema::hasColumn('agent_permissons', 'traffic_tb_baseline')) {
                $table->decimal('traffic_tb_baseline', 15, 2)->default(0)->after('product_count_baseline');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_permissons', function (Blueprint $table) {
            if (Schema::hasColumn('agent_permissons', 'traffic_tb_baseline')) {
                $table->dropColumn('traffic_tb_baseline');
            }
            if (Schema::hasColumn('agent_permissons', 'product_count_baseline')) {
                $table->dropColumn('product_count_baseline');
            }
        });
    }
};
