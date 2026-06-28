<?php

use App\Models\AdvanceSettingLookup;
use App\Services\MobileVerificationService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (AdvanceSettingLookup::query()
            ->where('name', MobileVerificationService::IRAN_ONLY_SETTING_KEY)
            ->exists()) {
            return;
        }

        AdvanceSettingLookup::query()->create([
            'name' => MobileVerificationService::IRAN_ONLY_SETTING_KEY,
            'value' => 'false',
            'description' => 'تایید موبایل فقط برای شماره‌های ایران (+98)',
        ]);
    }

    public function down(): void
    {
        AdvanceSettingLookup::query()
            ->where('name', MobileVerificationService::IRAN_ONLY_SETTING_KEY)
            ->delete();
    }
};
