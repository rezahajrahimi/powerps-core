<?php

namespace App\Http\Controllers;

use App\Models\AdvanceSettingLookup;
use App\Services\BotKeyboardConfigService;
use App\Services\LicenseFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotButtonConfigController extends Controller
{
    public function __construct(
        private readonly BotKeyboardConfigService $keyboardConfig = new BotKeyboardConfigService(),
        private readonly LicenseFeatureService $license = new LicenseFeatureService(),
    ) {
    }

    private function silverLicenseRequired(): ?JsonResponse
    {
        if (! $this->license->canCustomizeBotButtons()) {
            return $this->license->silverRequiredResponse();
        }

        return null;
    }

    public function getConfig(): JsonResponse
    {
        if ($denied = $this->silverLicenseRequired()) {
            return $denied;
        }

        $this->keyboardConfig->ensureSettingsExist();

        return response()->json([
            'reply_buttons_per_row' => $this->keyboardConfig->getIntSetting(BotKeyboardConfigService::SETTING_REPLY_COLUMNS, 2),
            'inline_buttons_per_row' => $this->keyboardConfig->getIntSetting(BotKeyboardConfigService::SETTING_INLINE_COLUMNS, 1),
            'package_buttons_per_row' => $this->keyboardConfig->getIntSetting(BotKeyboardConfigService::SETTING_PACKAGE_COLUMNS, 1),
            'reply_keyboard_persistent' => $this->keyboardConfig->getBoolSetting(BotKeyboardConfigService::SETTING_REPLY_PERSISTENT, false),
            'main_menu_first_item_alone' => $this->keyboardConfig->getBoolSetting(BotKeyboardConfigService::SETTING_MAIN_MENU_FIRST_ALONE, true),
            'style_rules' => $this->keyboardConfig->getStoredStyleRules(),
            'available_styles' => BotKeyboardConfigService::STYLES,
            'telegram_features' => [
                'button_style' => 'Bot API 9.4+ — primary (آبی), success (سبز), danger (قرمز)',
                'icon_custom_emoji_id' => 'Bot API 9.4+ — ایموجی سفارشی قبل از متن دکمه',
                'is_persistent' => 'کیبورد پایین همیشه نمایش داده می‌شود',
            ],
        ]);
    }

    public function updateLayoutSettings(Request $request): JsonResponse
    {
        if ($denied = $this->silverLicenseRequired()) {
            return $denied;
        }

        $validated = $request->validate([
            'reply_buttons_per_row' => 'nullable|integer|min:1|max:8',
            'inline_buttons_per_row' => 'nullable|integer|min:1|max:8',
            'package_buttons_per_row' => 'nullable|integer|min:1|max:8',
            'reply_keyboard_persistent' => 'nullable|boolean',
            'main_menu_first_item_alone' => 'nullable|boolean',
        ]);

        $map = [
            'reply_buttons_per_row' => BotKeyboardConfigService::SETTING_REPLY_COLUMNS,
            'inline_buttons_per_row' => BotKeyboardConfigService::SETTING_INLINE_COLUMNS,
            'package_buttons_per_row' => BotKeyboardConfigService::SETTING_PACKAGE_COLUMNS,
            'reply_keyboard_persistent' => BotKeyboardConfigService::SETTING_REPLY_PERSISTENT,
            'main_menu_first_item_alone' => BotKeyboardConfigService::SETTING_MAIN_MENU_FIRST_ALONE,
        ];

        foreach ($map as $requestKey => $settingName) {
            if (! array_key_exists($requestKey, $validated) || $validated[$requestKey] === null) {
                continue;
            }

            $value = is_bool($validated[$requestKey])
                ? ($validated[$requestKey] ? 'true' : 'false')
                : (string) $validated[$requestKey];

            AdvanceSettingLookup::query()->updateOrCreate(
                ['name' => $settingName],
                ['value' => $value],
            );
        }

        return $this->getConfig();
    }

    public function updateStyleRules(Request $request): JsonResponse
    {
        if ($denied = $this->silverLicenseRequired()) {
            return $denied;
        }

        $validated = $request->validate([
            'style_rules' => 'required|array',
            'style_rules.*.match' => 'required|string|max:120',
            'style_rules.*.match_type' => 'required|string|in:action_prefix,exact,callback_contains',
            'style_rules.*.style' => 'nullable|string|in:primary,success,danger',
            'style_rules.*.icon_custom_emoji_id' => 'nullable|string|max:64',
        ]);

        AdvanceSettingLookup::query()->updateOrCreate(
            ['name' => BotKeyboardConfigService::SETTING_STYLE_RULES],
            ['value' => json_encode($validated['style_rules'], JSON_UNESCAPED_UNICODE)],
        );

        return $this->getConfig();
    }
}
