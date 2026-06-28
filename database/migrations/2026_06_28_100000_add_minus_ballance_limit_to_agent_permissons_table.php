<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_permissons', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_permissons', 'minus_ballance_limit')) {
                $table->decimal('minus_ballance_limit', 15, 2)->nullable()->default(null)->after('minus_ballance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_permissons', function (Blueprint $table) {
            if (Schema::hasColumn('agent_permissons', 'minus_ballance_limit')) {
                $table->dropColumn('minus_ballance_limit');
            }
        });
    }
};
