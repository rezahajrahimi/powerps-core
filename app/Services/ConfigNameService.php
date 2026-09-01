<?php

namespace App\Services;

use App\Models\BotUser;
use App\Models\Setting;
use Illuminate\Support\Str;

class ConfigNameService
{
    public const DEFAULT_PREFIX = 'bot';

    public const DEFAULT_FORMAT = '{prefix}{account_label}';

    public const DEFAULT_MARZBAN_FORMAT = '{prefix}{chat_id}{product_id}';

    public static function getPrefix(): string
    {
        $prefix = Setting::query()->value('config_name_prefix');

        if (! filled($prefix)) {
            return self::DEFAULT_PREFIX;
        }

        $prefix = trim((string) $prefix);

        return $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }

    public static function getFormat(): string
    {
        $format = Setting::query()->value('config_name_format');

        return self::normalizeFormat($format);
    }

    public static function normalizePrefix(?string $prefix): string
    {
        if (! filled($prefix)) {
            return self::DEFAULT_PREFIX;
        }

        $prefix = preg_replace('/[^a-zA-Z0-9]/', '', trim($prefix)) ?? '';

        return $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }

    public static function normalizeFormat(?string $format): string
    {
        if (! filled($format)) {
            return self::DEFAULT_FORMAT;
        }

        $format = trim((string) $format);
        if ($format === '') {
            return self::DEFAULT_FORMAT;
        }

        $format = preg_replace('/[^{}a-zA-Z0-9_\-]/', '', $format) ?? '';

        return $format !== '' ? $format : self::DEFAULT_FORMAT;
    }

    public static function resolveAccountLabel(int|string $accountId, int|string|null $suffix = null): string
    {
        $botUser = BotUser::query()->where('account_id', $accountId)->first();
        $useAlias = self::useAdminAliasInConfigName();
        $label = ($useAlias && $botUser && filled($botUser->admin_alias))
            ? trim($botUser->admin_alias)
            : (string) $accountId;

        if ($suffix !== null && $suffix !== '') {
            return "{$label}-{$suffix}";
        }

        return $label;
    }

    public static function resolvePanelAccountLabel(
        int|string|null $chatId,
        int|string|null $productId = null,
        ?string $fallbackAccountId = null,
    ): string {
        if ($chatId !== null && $chatId !== '') {
            return self::resolveAccountLabel($chatId, $productId);
        }

        return (string) ($fallbackAccountId ?? '');
    }

    public static function applyFormat(string $format, array $vars): string
    {
        $replacements = [
            '{prefix}' => (string) ($vars['prefix'] ?? self::getPrefix()),
            '{account_id}' => (string) ($vars['account_id'] ?? ''),
            '{account_label}' => (string) ($vars['account_label'] ?? ''),
            '{chat_id}' => (string) ($vars['chat_id'] ?? ''),
            '{product_id}' => (string) ($vars['product_id'] ?? ''),
            '{random}' => (string) ($vars['random'] ?? ''),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }

    /**
     * @return array{account_label: string, account_id: string, chat_id: string, product_id: string}
     */
    public static function resolveNameVars(
        string $accountLabel,
        int|string|null $chatId = null,
        int|string|null $productId = null,
    ): array {
        $accountId = $chatId !== null ? (string) $chatId : '';
        $resolvedProductId = $productId !== null ? (string) $productId : '';

        if ($accountId === '' && preg_match('/^(.+)-([^-]+)$/', $accountLabel, $matches) === 1) {
            $accountId = $matches[1];
            $resolvedProductId = $resolvedProductId !== '' ? $resolvedProductId : $matches[2];
        }

        return [
            'account_label' => $accountLabel,
            'account_id' => $accountId,
            'chat_id' => $accountId,
            'product_id' => $resolvedProductId,
        ];
    }

    public static function buildHiddifyName(
        string $accountLabel,
        int|string|null $chatId = null,
        int|string|null $productId = null,
    ): string {
        $vars = self::resolveNameVars($accountLabel, $chatId, $productId);
        $vars['prefix'] = self::getPrefix();

        return self::applyFormat(self::getFormat(), $vars);
    }

    public static function buildSanaeiClientId(
        string $accountLabel,
        ?string $randomSuffix = null,
        int|string|null $chatId = null,
        int|string|null $productId = null,
    ): string {
        $random = $randomSuffix ?? Str::random(4);
        $format = self::getFormat();
        $vars = self::resolveNameVars($accountLabel, $chatId, $productId);
        $vars['prefix'] = self::getPrefix();
        $vars['random'] = $random;

        $name = self::applyFormat($format, $vars);
        if (! str_contains($format, '{random}')) {
            $name .= '-' . $random;
        }

        return $name;
    }

    public static function buildMarzbanFallbackUsername(int|string $chatId, int|string $productId): string
    {
        $vars = [
            'prefix' => self::getPrefix(),
            'account_label' => "{$chatId}-{$productId}",
            'account_id' => (string) $chatId,
            'chat_id' => (string) $chatId,
            'product_id' => (string) $productId,
        ];

        $format = self::getFormat();
        if (str_contains($format, '{chat_id}') || str_contains($format, '{product_id}')) {
            return self::applyFormat($format, $vars);
        }

        return self::applyFormat(self::DEFAULT_MARZBAN_FORMAT, $vars);
    }

    public static function buildMarzbanTestFallbackUsername(int|string $chatId): string
    {
        return self::getPrefix() . "{$chatId}Test";
    }

    public static function preview(string $format, string $prefix): string
    {
        return self::applyFormat(self::normalizeFormat($format), [
            'prefix' => self::normalizePrefix($prefix),
            'account_id' => '123456789',
            'account_label' => '123456789-42',
            'chat_id' => '123456789',
            'product_id' => '42',
            'random' => 'abcd',
        ]);
    }

    public static function useAdminAliasInConfigName(): bool
    {
        $setting = Setting::query()->first();
        if ($setting === null || $setting->use_admin_alias_in_config_name === null) {
            return true;
        }

        return (bool) $setting->use_admin_alias_in_config_name;
    }
}
