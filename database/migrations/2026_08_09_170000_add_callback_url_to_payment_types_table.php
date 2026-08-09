<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_types', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_types', 'callback_url')) {
                $table->string('callback_url', 500)->nullable()->after('merchant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_types', function (Blueprint $table) {
            if (Schema::hasColumn('payment_types', 'callback_url')) {
                $table->dropColumn('callback_url');
            }
        });
    }
};
