<?php

use App\Models\AdvanceSettingLookup;
use App\Services\PackageButtonLayoutService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (AdvanceSettingLookup::query()->where('name', PackageButtonLayoutService::SETTING_KEY)->exists()) {
            return;
        }

        $showOneRow = AdvanceSettingLookup::query()
            ->where('name', 'bot_show_one_row_config')
            ->value('value');

        $defaultLayout = ($showOneRow === 'true' || $showOneRow === '1' || $showOneRow === 1 || $showOneRow === true)
            ? PackageButtonLayoutService::LAYOUT_FULL_BUTTON
            : PackageButtonLayoutService::LAYOUT_MULTI_COLUMN;

        AdvanceSettingLookup::query()->create([
            'name' => PackageButtonLayoutService::SETTING_KEY,
            'value' => $defaultLayout,
            'description' => 'نحوه نمایش لیست بسته‌ها در ربات',
        ]);
    }

    public function down(): void
    {
        AdvanceSettingLookup::query()
            ->where('name', PackageButtonLayoutService::SETTING_KEY)
            ->delete();
    }
};
