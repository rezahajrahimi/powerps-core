<?php

namespace App\Services;

use App\Http\Controllers\AdvanceSettingLookupController;
use App\Models\AdvanceSettingLookup;
use App\Models\MainMenuItem;
use Illuminate\Support\Collection;

class BotKeyboardConfigService
{
    public const SETTING_REPLY_COLUMNS = 'bot_reply_buttons_per_row';

    public const SETTING_INLINE_COLUMNS = 'bot_inline_buttons_per_row';

    public const SETTING_PACKAGE_COLUMNS = 'bot_package_buttons_per_row';

    public const SETTING_REPLY_PERSISTENT = 'bot_reply_keyboard_persistent';

    public const SETTING_MAIN_MENU_FIRST_ALONE = 'bot_main_menu_first_item_alone';

    public const SETTING_STYLE_RULES = 'bot_button_style_rules';

    public const STYLES = ['primary', 'success', 'danger'];

    /** @var array<int, array{match: string, match_type: string, style?: string|null, icon_custom_emoji_id?: string|null}> */
    public const DEFAULT_STYLE_RULES = [
        ['match' => 'confirmBuy', 'match_type' => 'action_prefix', 'style' => 'success'],
        ['match' => 'confirmRecharge', 'match_type' => 'action_prefix', 'style' => 'success'],
        ['match' => 'confirmReceipt', 'match_type' => 'action_prefix', 'style' => 'success'],
        ['match' => 'confirmDeleteHistory', 'match_type' => 'action_prefix', 'style' => 'danger'],
        ['match' => 'cancel', 'match_type' => 'action_prefix', 'style' => 'danger'],
        ['match' => 'deleteHistory', 'match_type' => 'action_prefix', 'style' => 'danger'],
        ['match' => 'buySubscription', 'match_type' => 'action_prefix', 'style' => 'primary'],
        ['match' => 'buyHistoryNext', 'match_type' => 'action_prefix', 'style' => 'primary'],
        ['match' => 'accountLoyaltyHistoryPage', 'match_type' => 'action_prefix', 'style' => 'primary'],
        ['match' => 'recharge', 'match_type' => 'action_prefix', 'style' => 'primary'],
        ['match' => '0', 'match_type' => 'exact', 'style' => null],
    ];

    public function __construct(
        private readonly AdvanceSettingLookupController $advanceSettingLookup = new AdvanceSettingLookupController(),
        private readonly ?LicenseFeatureService $licenseFeature = null,
        private readonly ?bool $forceCustomizationEnabled = null,
    ) {
    }

    private ?bool $customizationEnabled = null;

    public function isCustomizationEnabled(): bool
    {
        if ($this->forceCustomizationEnabled !== null) {
            return $this->forceCustomizationEnabled;
        }

        if ($this->customizationEnabled !== null) {
            return $this->customizationEnabled;
        }

        try {
            $this->customizationEnabled = ($this->licenseFeature ?? new LicenseFeatureService())
                ->canCustomizeBotButtons();
        } catch (\Throwable) {
            $this->customizationEnabled = false;
        }

        return $this->customizationEnabled;
    }

    public function ensureSettingsExist(): void
    {
        try {
            $defaults = [
                [self::SETTING_REPLY_COLUMNS, '2', 'تعداد دکمه در هر ردیف منوی اصلی (کیبورد پایین)'],
                [self::SETTING_INLINE_COLUMNS, '1', 'تعداد دکمه در هر ردیف کیبورد اینلاین (پیش‌فرض)'],
                [self::SETTING_PACKAGE_COLUMNS, '1', 'تعداد دکمه در هر ردیف لیست بسته‌ها'],
                [self::SETTING_REPLY_PERSISTENT, 'false', 'کیبورد پایین همیشه نمایش داده شود (is_persistent)'],
                [self::SETTING_MAIN_MENU_FIRST_ALONE, 'true', 'اولین آیتم منوی اصلی در ردیف جداگانه'],
                [self::SETTING_STYLE_RULES, json_encode(self::DEFAULT_STYLE_RULES, JSON_UNESCAPED_UNICODE), 'قوانین استایل و رنگ دکمه‌های اینلاین'],
            ];

            foreach ($defaults as [$name, $value, $description]) {
                if (! AdvanceSettingLookup::query()->where('name', $name)->exists()) {
                    AdvanceSettingLookup::query()->create([
                        'name' => $name,
                        'value' => $value,
                        'description' => $description,
                    ]);
                }
            }
        } catch (\Throwable) {
            // Settings table may be unavailable during tests or early bootstrap.
        }
    }

    public function getIntSetting(string $name, int $default, int $min = 1, int $max = 8): int
    {
        try {
            $this->ensureSettingsExist();
            $value = AdvanceSettingLookup::query()->where('name', $name)->value('value');

            return max($min, min($max, (int) ($value ?: $default)));
        } catch (\Throwable) {
            return $default;
        }
    }

    public function getBoolSetting(string $name, bool $default = false): bool
    {
        try {
            $this->ensureSettingsExist();
            $value = AdvanceSettingLookup::query()->where('name', $name)->value('value');

            if ($value === null || $value === '') {
                return $default;
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @return array<int, array{match: string, match_type: string, style?: string|null, icon_custom_emoji_id?: string|null}>
     */
    public function getStyleRules(): array
    {
        if (! $this->isCustomizationEnabled()) {
            return [];
        }

        return $this->getStoredStyleRules();
    }

    /**
     * @return array<int, array{match: string, match_type: string, style?: string|null, icon_custom_emoji_id?: string|null}>
     */
    public function getStoredStyleRules(): array
    {
        try {
            $this->ensureSettingsExist();
            $raw = AdvanceSettingLookup::query()->where('name', self::SETTING_STYLE_RULES)->value('value');
            if (! is_string($raw) || trim($raw) === '') {
                return self::DEFAULT_STYLE_RULES;
            }

            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return self::DEFAULT_STYLE_RULES;
            }

            return array_values(array_filter($decoded, static fn ($rule) => is_array($rule) && isset($rule['match'])));
        } catch (\Throwable) {
            return self::DEFAULT_STYLE_RULES;
        }
    }

    /**
     * @return array<int, array{match: string, match_type: string, style?: string|null, icon_custom_emoji_id?: string|null}>
     */
    public function getAdminStyleRules(): array
    {
        return $this->getStoredStyleRules();
    }

    /**
     * @return array{style?: string, icon_custom_emoji_id?: string}
     */
    public function resolveStyleForCallback(?string $callbackData, array $overrides = []): array
    {
        $result = [];

        foreach (['style', 'icon_custom_emoji_id'] as $field) {
            if (! empty($overrides[$field]) && ($field !== 'style' || $this->isValidStyle((string) $overrides[$field]))) {
                $result[$field] = (string) $overrides[$field];
            }
        }

        if ($callbackData === null || $callbackData === '') {
            return $result;
        }

        $action = explode('-', $callbackData, 2)[0];

        foreach ($this->getStyleRules() as $rule) {
            $match = (string) ($rule['match'] ?? '');
            $matchType = (string) ($rule['match_type'] ?? 'action_prefix');

            $matched = match ($matchType) {
                'exact' => $callbackData === $match,
                'action_prefix' => $action === $match || str_starts_with($action, $match),
                'callback_contains' => str_contains($callbackData, $match),
                default => $action === $match,
            };

            if (! $matched) {
                continue;
            }

            if (! isset($result['style']) && ! empty($rule['style']) && $this->isValidStyle((string) $rule['style'])) {
                $result['style'] = (string) $rule['style'];
            }

            if (! isset($result['icon_custom_emoji_id']) && ! empty($rule['icon_custom_emoji_id'])) {
                $result['icon_custom_emoji_id'] = (string) $rule['icon_custom_emoji_id'];
            }

            if (isset($result['style']) || isset($result['icon_custom_emoji_id'])) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, MainMenuItem>|iterable<int, MainMenuItem>  $items
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function buildMainMenuKeyboard(iterable $items): array
    {
        if (! $this->isCustomizationEnabled()) {
            return $this->buildLegacyMainMenuKeyboard($items);
        }

        $columns = $this->getIntSetting(self::SETTING_REPLY_COLUMNS, 2);
        $firstAlone = $this->getBoolSetting(self::SETTING_MAIN_MENU_FIRST_ALONE, true);
        $rows = [];
        $buffer = [];

        foreach ($items as $item) {
            $button = $this->buildReplyButton(
                (string) $item->alias_name,
                [
                    'style' => $item->button_style ?? null,
                    'icon_custom_emoji_id' => $item->icon_custom_emoji_id ?? null,
                ],
            );

            $forceSoloRow = (bool) ($item->solo_row ?? false);

            if ($forceSoloRow || ($firstAlone && $rows === [] && $buffer === [])) {
                if ($buffer !== []) {
                    $rows[] = $buffer;
                    $buffer = [];
                }
                $rows[] = [$button];

                continue;
            }

            $buffer[] = $button;
            if (count($buffer) >= $columns) {
                $rows[] = $buffer;
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $rows[] = $buffer;
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string|int, mixed>>  $buttons
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function formatInlineKeyboard(array $buttons, ?int $columnsPerRow = null, bool $regroupSingleRows = true): array
    {
        $rows = [];

        foreach ($buttons as $row) {
            if (! is_array($row)) {
                continue;
            }

            $formattedRow = [];
            foreach ($row as $key => $value) {
                if (is_int($key) && is_array($value)) {
                    $formattedRow[] = $this->normalizeInlineButton($value);
                    continue;
                }

                if (is_scalar($value) && (is_string($key) || is_int($key) || is_float($key))) {
                    $formattedRow[] = $this->normalizeInlineButton([
                        'text' => (string) $key,
                        'callback_data' => (string) $value,
                    ]);
                }
            }

            if ($formattedRow !== []) {
                $rows[] = $formattedRow;
            }
        }

        if ($regroupSingleRows && $this->isCustomizationEnabled() && $this->canRegroupRows($rows)) {
            $columns = $columnsPerRow ?? $this->getIntSetting(self::SETTING_INLINE_COLUMNS, 1);
            $rows = $this->regroupSingleButtonRows($rows, $columns);
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $buttons
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function formatReplyKeyboard(array $buttons): array
    {
        $rows = [];

        foreach ($buttons as $row) {
            if (! is_array($row)) {
                continue;
            }

            $formattedRow = [];
            foreach ($row as $button) {
                if (is_string($button)) {
                    $formattedRow[] = $this->buildReplyButton($button);
                    continue;
                }

                if (! is_array($button)) {
                    continue;
                }

                $formattedRow[] = $this->buildReplyButton(
                    (string) ($button['text'] ?? ''),
                    [
                        'style' => $button['style'] ?? null,
                        'icon_custom_emoji_id' => $button['icon_custom_emoji_id'] ?? null,
                    ],
                    $button,
                );
            }

            if ($formattedRow !== []) {
                $rows[] = $formattedRow;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, string>>  $buttons
     * @return array<int, array<string, string>>
     */
    public function applyPackageButtonLayout(array $buttons): array
    {
        if (! $this->isCustomizationEnabled()) {
            return $buttons;
        }

        $columns = $this->getIntSetting(self::SETTING_PACKAGE_COLUMNS, 1);
        if ($columns <= 1) {
            return $buttons;
        }

        $flat = [];
        foreach ($buttons as $row) {
            foreach ($row as $text => $callback) {
                $flat[] = [$text => $callback];
            }
        }

        $grouped = [];
        $buffer = [];
        foreach ($flat as $item) {
            $buffer[] = $item;
            if (count($buffer) >= $columns) {
                $grouped[] = array_merge(...array_map(static fn ($entry) => $entry, $buffer));
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $grouped[] = array_merge(...array_map(static fn ($entry) => $entry, $buffer));
        }

        return array_map(static fn ($row) => $row, $grouped);
    }

    public function replyKeyboardOptions(): array
    {
        $options = [
            'resize_keyboard' => true,
        ];

        if ($this->isCustomizationEnabled() && $this->getBoolSetting(self::SETTING_REPLY_PERSISTENT, false)) {
            $options['is_persistent'] = true;
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $button
     * @return array<string, mixed>
     */
    private function normalizeInlineButton(array $button): array
    {
        $text = (string) ($button['text'] ?? '');
        $callbackData = (string) ($button['callback_data'] ?? $button['callback'] ?? '');

        $normalized = ['text' => $text];

        if ($callbackData !== '') {
            $normalized['callback_data'] = $callbackData;
        }

        foreach (['url', 'web_app', 'login_url', 'switch_inline_query', 'switch_inline_query_current_chat', 'pay', 'copy_text'] as $field) {
            if (! empty($button[$field])) {
                $normalized[$field] = $button[$field];
            }
        }

        $styleMeta = $this->isCustomizationEnabled()
            ? $this->resolveStyleForCallback(
                $callbackData !== '' ? $callbackData : null,
                [
                    'style' => $button['style'] ?? null,
                    'icon_custom_emoji_id' => $button['icon_custom_emoji_id'] ?? null,
                ],
            )
            : [];

        return $this->appendStyleFields($normalized, $styleMeta);
    }

    /**
     * @param  array<string, mixed>  $extraFields
     * @param  array{style?: string, icon_custom_emoji_id?: string}  $styleMeta
     * @return array<string, mixed>
     */
    private function buildReplyButton(string $text, array $styleMeta = [], array $extraFields = []): array
    {
        $button = ['text' => $text];

        foreach (['request_contact', 'request_location', 'request_poll', 'web_app'] as $field) {
            if (! empty($extraFields[$field])) {
                $button[$field] = $extraFields[$field];
            }
        }

        return $this->appendStyleFields($button, $this->isCustomizationEnabled() ? $styleMeta : []);
    }

    /**
     * @param  array<string, mixed>  $button
     * @param  array{style?: string, icon_custom_emoji_id?: string}  $styleMeta
     * @return array<string, mixed>
     */
    private function appendStyleFields(array $button, array $styleMeta): array
    {
        if (! empty($styleMeta['style']) && $this->isValidStyle((string) $styleMeta['style'])) {
            $button['style'] = (string) $styleMeta['style'];
        }

        if (! empty($styleMeta['icon_custom_emoji_id'])) {
            $button['icon_custom_emoji_id'] = (string) $styleMeta['icon_custom_emoji_id'];
        }

        return $button;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $rows
     */
    private function canRegroupRows(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (count($row) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $rows
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function regroupSingleButtonRows(array $rows, int $columns): array
    {
        if ($columns <= 1) {
            return $rows;
        }

        $flat = array_merge(...$rows);
        $regrouped = [];
        $buffer = [];

        foreach ($flat as $button) {
            $buffer[] = $button;
            if (count($buffer) >= $columns) {
                $regrouped[] = $buffer;
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $regrouped[] = $buffer;
        }

        return $regrouped;
    }

    private function isValidStyle(string $style): bool
    {
        return in_array($style, self::STYLES, true);
    }

    /**
     * @param  iterable<int, MainMenuItem>  $items
     * @return array<int, array<int, array<string, string>>>
     */
    private function buildLegacyMainMenuKeyboard(iterable $items): array
    {
        $rows = [];
        $buffer = [];
        $isFirst = true;

        foreach ($items as $item) {
            $button = ['text' => (string) $item->alias_name];

            if ($isFirst) {
                $rows[] = [$button];
                $isFirst = false;

                continue;
            }

            $buffer[] = $button;
            if (count($buffer) >= 2) {
                $rows[] = $buffer;
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $rows[] = $buffer;
        }

        return $rows;
    }
}
