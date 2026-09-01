<?php

namespace App\Services;

use App\Http\Controllers\AdvanceSettingLookupController;
use App\Models\AdvanceSettingLookup;
use App\Services\LicenseFeatureService;
use Illuminate\Support\Collection;

class PackageButtonLayoutService
{
    public const LAYOUT_FULL_BUTTON = 'full_button';

    public const LAYOUT_MULTI_COLUMN = 'multi_column';

    public const LAYOUT_LIST_IN_MESSAGE = 'list_in_message';

    public const LAYOUT_COMPACT_BUTTON = 'compact_button';

    public const SETTING_KEY = 'bot_package_button_layout';

    /** @var array<string, string> */
    public const LAYOUT_LABELS = [
        self::LAYOUT_FULL_BUTTON => 'دکمه کامل (نام + قیمت در یک دکمه)',
        self::LAYOUT_MULTI_COLUMN => 'جدولی (ستون جدا برای قیمت و نام)',
        self::LAYOUT_LIST_IN_MESSAGE => 'لیست در پیام + دکمه کوتاه',
        self::LAYOUT_COMPACT_BUTTON => 'دکمه فشرده (نام کوتاه + قیمت)',
    ];

    public function __construct(
        private readonly AdvanceSettingLookupController $advanceSettingLookup = new AdvanceSettingLookupController(),
    ) {
    }

    public function resolveLayout(): string
    {
        if (! (new LicenseFeatureService())->canUseAdvancedSetting(self::SETTING_KEY)) {
            return self::LAYOUT_MULTI_COLUMN;
        }

        $this->ensureLayoutSettingExists();

        $lookup = AdvanceSettingLookup::query()->where('name', self::SETTING_KEY)->first();
        $value = is_string($lookup?->value) ? trim($lookup->value) : '';

        if ($value !== '' && array_key_exists($value, self::LAYOUT_LABELS)) {
            return $value;
        }

        $showOneRow = $this->advanceSettingLookup->getValueByNameWithBooleanValue('bot_show_one_row_config');

        return ($showOneRow === true || $showOneRow === 1)
            ? self::LAYOUT_FULL_BUTTON
            : self::LAYOUT_MULTI_COLUMN;
    }

    /**
     * @return array{message: string, buttons: array<int, array<string, string>>}
     */
    public function buildPackageSelection(
        Collection $categories,
        bool $dollarTransaction,
        string $baseMessage,
    ): array {
        $result = match ($this->resolveLayout()) {
            self::LAYOUT_MULTI_COLUMN => $this->buildMultiColumnLayout($categories, $dollarTransaction, $baseMessage),
            self::LAYOUT_LIST_IN_MESSAGE => $this->buildListInMessageLayout($categories, $dollarTransaction, $baseMessage),
            self::LAYOUT_COMPACT_BUTTON => $this->buildCompactButtonLayout($categories, $dollarTransaction, $baseMessage),
            default => $this->buildFullButtonLayout($categories, $dollarTransaction, $baseMessage),
        };

        if ($this->resolveLayout() !== self::LAYOUT_MULTI_COLUMN) {
            $result['buttons'] = (new BotKeyboardConfigService())->applyPackageButtonLayout($result['buttons']);
        }

        return $result;
    }

    public function ensureLayoutSettingExists(): void
    {
        $exists = AdvanceSettingLookup::query()->where('name', self::SETTING_KEY)->exists();
        if ($exists) {
            return;
        }

        $showOneRow = $this->advanceSettingLookup->getValueByNameWithBooleanValue('bot_show_one_row_config');
        $defaultLayout = ($showOneRow === true || $showOneRow === 1)
            ? self::LAYOUT_FULL_BUTTON
            : self::LAYOUT_MULTI_COLUMN;

        AdvanceSettingLookup::query()->create([
            'name' => self::SETTING_KEY,
            'value' => $defaultLayout,
            'description' => 'نحوه نمایش لیست بسته‌ها در ربات',
        ]);
    }

    /**
     * @return array{message: string, buttons: array<int, array<string, string>>}
     */
    private function buildFullButtonLayout(Collection $categories, bool $dollarTransaction, string $baseMessage): array
    {
        $buttons = [];

        foreach ($categories as $category) {
            $buttons[] = [
                $this->formatFullButtonLabel($category, $dollarTransaction) => 'buySubscription-' . $category->id,
            ];
        }

        return [
            'message' => $baseMessage,
            'buttons' => $buttons,
        ];
    }

    /**
     * @return array{message: string, buttons: array<int, array<string, string>>}
     */
    private function buildMultiColumnLayout(Collection $categories, bool $dollarTransaction, string $baseMessage): array
    {
        $buttons = [];

        if ($dollarTransaction) {
            $buttons[] = [
                $this->inlineButton('قیمت(دلار)', '0'),
                $this->inlineButton('قیمت(تومان)', '0'),
                $this->inlineButton('بسته', '0'),
            ];
            foreach ($categories as $category) {
                $callback = 'buySubscription-' . $category->id;
                $buttons[] = [
                    $this->inlineButton($this->formatDollarPrice((float) $category->price_in_dollar), $callback),
                    $this->inlineButton($this->formatTomanPrice((float) $category->price), $callback),
                    $this->inlineButton((string) $category->category_name, $callback),
                ];
            }
        } else {
            $buttons[] = [
                $this->inlineButton('قیمت(تومان)', '0'),
                $this->inlineButton('بسته', '0'),
            ];
            foreach ($categories as $category) {
                $callback = 'buySubscription-' . $category->id;
                $buttons[] = [
                    $this->inlineButton($this->formatTomanPrice((float) $category->price), $callback),
                    $this->inlineButton((string) $category->category_name, $callback),
                ];
            }
        }

        return [
            'message' => $baseMessage,
            'buttons' => $buttons,
        ];
    }

    /**
     * @return array{message: string, buttons: array<int, array<string, string>>}
     */
    private function buildListInMessageLayout(Collection $categories, bool $dollarTransaction, string $baseMessage): array
    {
        $lines = [];
        $buttons = [];
        $index = 1;

        foreach ($categories as $category) {
            $lines[] = $this->formatListLine($index, $category, $dollarTransaction);
            $buttons[] = [
                $this->truncateText("{$index}. {$category->category_name}", 36) => 'buySubscription-' . $category->id,
            ];
            $index++;
        }

        $message = trim($baseMessage);
        if ($lines !== []) {
            $message .= "\n\n" . implode("\n", $lines);
        }

        return [
            'message' => $message,
            'buttons' => $buttons,
        ];
    }

    /**
     * @return array{message: string, buttons: array<int, array<string, string>>}
     */
    private function buildCompactButtonLayout(Collection $categories, bool $dollarTransaction, string $baseMessage): array
    {
        $buttons = [];

        foreach ($categories as $category) {
            $priceToman = $this->formatTomanPrice((float) $category->price);
            $label = $this->truncateText((string) $category->category_name, 24) . " — {$priceToman}";
            if ($dollarTransaction) {
                $label .= ' · ' . $this->formatDollarPrice((float) $category->price_in_dollar) . '$';
            }

            $buttons[] = [
                $this->truncateText($label, 40) => 'buySubscription-' . $category->id,
            ];
        }

        return [
            'message' => $baseMessage,
            'buttons' => $buttons,
        ];
    }

    private function formatFullButtonLabel(object $category, bool $dollarTransaction): string
    {
        $priceToman = $this->formatTomanPrice((float) $category->price);

        if ($dollarTransaction) {
            $priceDollar = $this->formatDollarPrice((float) $category->price_in_dollar);

            return "{$category->category_name} - {$priceDollar}$ - {$priceToman} تومان";
        }

        return "{$category->category_name} - {$priceToman} تومان";
    }

    private function formatListLine(int $index, object $category, bool $dollarTransaction): string
    {
        $priceToman = $this->formatTomanPrice((float) $category->price);
        $line = "{$index}. {$category->category_name} — {$priceToman} تومان";

        if ($dollarTransaction) {
            $line .= ' (' . $this->formatDollarPrice((float) $category->price_in_dollar) . '$)';
        }

        return $line;
    }

    private function formatTomanPrice(float $price): string
    {
        return number_format($price, 0, '.', ',');
    }

    private function formatDollarPrice(float $price): string
    {
        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }

    private function truncateText(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(1, $maxLength - 1))) . '…';
    }

    /**
     * @return array{text: string, callback_data: string}
     */
    private function inlineButton(string $text, string $callbackData): array
    {
        return [
            'text' => $text,
            'callback_data' => $callbackData,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $buttons
     * @return array<int, array<int, array{text: string, callback_data: string}>>
     */
    public function toLegacyInlineKeyboard(array $buttons): array
    {
        $rows = [];

        foreach ($buttons as $row) {
            $legacyRow = [];
            foreach ($row as $text => $callbackData) {
                $legacyRow[] = [
                    'text' => (string) $text,
                    'callback_data' => (string) $callbackData,
                ];
            }
            $rows[] = $legacyRow;
        }

        return $rows;
    }
}
