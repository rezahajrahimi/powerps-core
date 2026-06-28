<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_infos', function (Blueprint $table) {
            $table->string('primary_color')->nullable()->after('image');
            $table->string('secondary_color')->nullable()->after('primary_color');
            $table->string('background_color')->nullable()->after('secondary_color');
            $table->string('panel_title')->nullable()->after('background_color');
            $table->string('footer_text')->nullable()->after('panel_title');
            $table->boolean('show_powerps_credit')->default(true)->after('footer_text');
        });
    }

    public function down(): void
    {
        Schema::table('app_infos', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color',
                'secondary_color',
                'background_color',
                'panel_title',
                'footer_text',
                'show_powerps_credit',
            ]);
        });
    }
};
