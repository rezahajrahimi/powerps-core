<?php

use App\Models\AdvanceSettingLookup;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (AdvanceSettingLookup::query()->where('name', 'bot_require_mobile_verification')->exists()) {
            return;
        }

        AdvanceSettingLookup::query()->create([
            'name' => 'bot_require_mobile_verification',
            'value' => 'false',
            'description' => 'الزام تایید موبایل قبل از خرید (ارسال شماره تماس در تلگرام)',
        ]);
    }

    public function down(): void
    {
        AdvanceSettingLookup::query()->where('name', 'bot_require_mobile_verification')->delete();
    }
};
