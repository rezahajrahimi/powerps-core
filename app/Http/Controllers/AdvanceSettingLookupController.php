<?php
namespace App\Http\Controllers;

use App\Models\AdvanceSettingLookup;
use App\Services\BotKeyboardConfigService;
use App\Services\LicenseFeatureService;
use App\Services\MobileVerificationService;
use App\Services\PackageButtonLayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvanceSettingLookupController extends Controller
{
    public function __construct(
        private readonly LicenseFeatureService $license = new LicenseFeatureService(),
    ) {}

    private function advancedSettingLicenseRequired(string $name): ?\Illuminate\Http\JsonResponse
    {
        if (! $this->license->canUseAdvancedSetting($name)) {
            return $this->license->advancedSettingRequiredResponse($name);
        }

        return null;
    }

    private function silverLicenseRequired(): ?\Illuminate\Http\JsonResponse
    {
        if (! $this->license->canUseAdvancedSettings()) {
            return $this->license->silverRequiredResponse();
        }

        return null;
    }

    public function getAll()
    {
        try {
            (new PackageButtonLayoutService())->ensureLayoutSettingExists();
            $advanceSettingLookups = AdvanceSettingLookup::all();
            if ($advanceSettingLookups->isEmpty()) {
                $this->seed();
                $advanceSettingLookups = AdvanceSettingLookup::all();
            }
            return response()->json($advanceSettingLookups);
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getAll->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }
    public function getAllWithBooleanValue()
    {
        try {
            $advanceSettingLookups = AdvanceSettingLookup::all()->map(function ($item) {
                return ['name' => $item->name, 'value' => $item->booleanValue];
            });
            if ($advanceSettingLookups->isEmpty()) {
                $this->seed();
                $advanceSettingLookups = AdvanceSettingLookup::all()->map(function ($item) {
                    return ['name' => $item->name, 'value' => $item->booleanValue];
                });
            }
            return $advanceSettingLookups;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getAllWithBooleanValue->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }
    public function seed()
    {
        $advanceSettingLookups = [
            ['name' => 'bot_show_configs_by_panels_category', 'value' => 'false', 'description' => 'نمایش کانفیگ ها براساس موقیت جغرافیایی پنل'],
            ['name' => 'bot_auto_set_price_by_dollar_price', 'value' => 'false', 'description' => 'قیمت گذاری اتوماتیک بر اساس قیمت دلار'],
            ['name' => 'bot_calculate_product_category_price_in_dollar_by_toman', 'value' => 'false', 'description' => 'قیمت گذاری اتوماتیک بر اساس قیمت تومان'],
            ['name' => 'bot_show_one_row_config', 'value' => 'true', 'description' => 'نمایش پیکربندی ها در یک ردیف (قدیمی — در صورت تنظیم «نحوه نمایش لیست بسته‌ها» نادیده گرفته می‌شود)'],
            ['name' => PackageButtonLayoutService::SETTING_KEY, 'value' => PackageButtonLayoutService::LAYOUT_FULL_BUTTON, 'description' => 'نحوه نمایش لیست بسته‌ها در ربات'],
            ['name' => BotKeyboardConfigService::SETTING_REPLY_COLUMNS, 'value' => '2', 'description' => 'تعداد دکمه در هر ردیف منوی اصلی (کیبورد پایین)'],
            ['name' => BotKeyboardConfigService::SETTING_INLINE_COLUMNS, 'value' => '1', 'description' => 'تعداد دکمه در هر ردیف کیبورد اینلاین (پیش‌فرض)'],
            ['name' => BotKeyboardConfigService::SETTING_PACKAGE_COLUMNS, 'value' => '1', 'description' => 'تعداد دکمه در هر ردیف لیست بسته‌ها'],
            ['name' => BotKeyboardConfigService::SETTING_REPLY_PERSISTENT, 'value' => 'false', 'description' => 'کیبورد پایین همیشه نمایش داده شود (is_persistent)'],
            ['name' => BotKeyboardConfigService::SETTING_MAIN_MENU_FIRST_ALONE, 'value' => 'true', 'description' => 'اولین آیتم منوی اصلی در ردیف جداگانه'],
            ['name' => BotKeyboardConfigService::SETTING_STYLE_RULES, 'value' => json_encode(BotKeyboardConfigService::DEFAULT_STYLE_RULES, JSON_UNESCAPED_UNICODE), 'description' => 'قوانین استایل و رنگ دکمه‌های اینلاین'],
            ['name' => 'bot_daily_backup', 'value' => 'true', 'description' => 'برای ایجاد بکاپ روزانه'],
            ['name' => 'bot_auto_delete_expired_configs', 'value' => 'true', 'description' => 'حذف کانفیگ هایی که 10 روز از انقضا آنها می گذرد'],
            ['name' => MobileVerificationService::SETTING_KEY, 'value' => 'false', 'description' => 'الزام تایید موبایل قبل از خرید (ارسال شماره تماس در تلگرام)'],
            ['name' => MobileVerificationService::IRAN_ONLY_SETTING_KEY, 'value' => 'false', 'description' => 'تایید موبایل فقط برای شماره‌های ایران (+98)'],
        ];
        AdvanceSettingLookup::insert($advanceSettingLookups);
    }
    public function re_seed_advance_settings_lookup(){
        if ($denied = $this->silverLicenseRequired()) {
            return $denied;
        }

        try{
            DB::transaction(function () {
                AdvanceSettingLookup::query()->delete();
                $this->seed();
            });
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->re_seed_advance_settings_lookup->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }
    public function getByName($name)
    {
        try {
            return AdvanceSettingLookup::getByName($name);
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getByName->error", ['error' => $th->getMessage(), 'name' => $name]);
            return null;
        }
    }
    public function getByNameWithBooleanValue($name)
    {
        try {
            return AdvanceSettingLookup::getByName($name)->booleanValue;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getByName->error", ['error' => $th->getMessage(), 'name' => $name]);
            return null;
        }
    }
    public function getByNameAndValue($name, $value)
    {
        try {
            return AdvanceSettingLookup::getByNameAndValue($name, $value);
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getByNameAndValue->error", ['error' => $th->getMessage(), 'name' => $name, 'value' => $value]);
            return null;
        }
    }
    public function getValueByNameWithBooleanValue($name)
    {
        try {
            if (! $this->license->canUseAdvancedSetting((string) $name)) {
                return false;
            }

            $advanceSettingLookup = AdvanceSettingLookup::getByName($name);
            if ($advanceSettingLookup == null) {
                // get all advance setting lookups, then clear all of them, then run $this->seed function, update new ones with old values, then get the advance setting lookup by name
                $advanceSettingLookups = AdvanceSettingLookup::all();
                DB::transaction(function () use ($advanceSettingLookups) {
                    AdvanceSettingLookup::query()->delete();
                    $this->seed();
                    foreach ($advanceSettingLookups as $advanceSettingLookup) {
                        $this->update(Request::create($advanceSettingLookup->id, $advanceSettingLookup->name, $advanceSettingLookup->value, $advanceSettingLookup->description));
                    }
                });
                $advanceSettingLookup = AdvanceSettingLookup::getByName($name);
                return $advanceSettingLookup->booleanValue;
            }
            if ($advanceSettingLookup->booleanValue == 'true') {
                return true;
            }
            return false;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getByNameAndValue->error", ['error' => $th->getMessage(), 'name' => $name]);
            return null;
        }
    }

    /**
     * Create a new advance setting lookup.
     *
     * @param string $name the name of advance setting lookup
     * @param string $value the value of advance setting lookup
     * @param string|null $description the description of advance setting lookup
     *
     * @return \App\Models\AdvanceSettingLookup
     */
    public function create($name, $value, $description = null)
    {
        try {
            return AdvanceSettingLookup::create(['name' => $name, 'value' => $value, 'description' => $description]);
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->create->error", ['error' => $th->getMessage(), 'name' => $name, 'value' => $value, 'description' => $description]);
            return "null";
        }
    }
    public function update(Request $request)
    {
        if ($denied = $this->silverLicenseRequired()) {
            return $denied;
        }

        try {
            $advanceSettingLookup              = AdvanceSettingLookup::find($request->id);
            $advanceSettingLookup->name        = $request->name;
            $advanceSettingLookup->value       = $request->value;
            $advanceSettingLookup->description = $request->description;
            $advanceSettingLookup->update();
            return $advanceSettingLookup;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->update->error", [
                'error' => $th->getMessage(),
                'id' => $request->id ?? null,
                'name' => $request->name ?? null,
                'value' => $request->value ?? null,
                'description' => $request->description ?? null
            ]);
            return null;
        }
    }
    public function updateByName(Request $request)
    {
        $name = (string) ($request->name ?? '');
        if ($denied = $this->advancedSettingLicenseRequired($name)) {
            return $denied;
        }

        try {
            $advanceSettingLookup              = AdvanceSettingLookup::where('name', $request->name)->first();
            $advanceSettingLookup->value       = $request->value;
            if ($request->filled('description')) {
                $advanceSettingLookup->description = $request->description;
            }
            $advanceSettingLookup->update();
            return $advanceSettingLookup;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->updateByName->error", [
                'error' => $th->getMessage(),
                'name' => $request->name ?? null,
                'value' => $request->value ?? null,
                'description' => $request->description ?? null
            ]);
            return null;
        }
    }

}
