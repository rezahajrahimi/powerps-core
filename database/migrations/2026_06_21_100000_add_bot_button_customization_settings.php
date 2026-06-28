<?php

use App\Models\MainMenuItem;
use App\Services\BotKeyboardConfigService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('main_menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('main_menu_items', 'button_style')) {
                $table->string('button_style', 16)->nullable()->after('position');
            }
            if (! Schema::hasColumn('main_menu_items', 'icon_custom_emoji_id')) {
                $table->string('icon_custom_emoji_id', 64)->nullable()->after('button_style');
            }
            if (! Schema::hasColumn('main_menu_items', 'solo_row')) {
                $table->boolean('solo_row')->default(false)->after('icon_custom_emoji_id');
            }
        });

        MainMenuItem::query()
            ->where('position', 1)
            ->update(['solo_row' => true]);

        (new BotKeyboardConfigService())->ensureSettingsExist();
    }

    public function down(): void
    {
        Schema::table('main_menu_items', function (Blueprint $table) {
            foreach (['button_style', 'icon_custom_emoji_id', 'solo_row'] as $column) {
                if (Schema::hasColumn('main_menu_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
