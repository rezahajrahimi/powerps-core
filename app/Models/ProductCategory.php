<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'pannel_id'];
    protected $fillable = ['pannel_id', 'category_name', 'price', 'expire_day', 'volume', 'rechargable', 'show_subscription_link', 'show_pannel_link', 'send_config_to_user', 'is_active', 'price_in_dollar', 'inbound_id', 'inbound_ids', 'marzban_inbounds', 'pasarguard_group_ids', 'ip_limit', 'sample_inbound', 'allowed_user_group_ids', 'upsell_category_id'];

    protected $casts = [
        'send_config_to_user' => 'boolean',
        'allowed_user_group_ids' => 'array',
        'inbound_ids' => 'array',
        'marzban_inbounds' => 'array',
        'pasarguard_group_ids' => 'array',
    ];

    /**
     * Resolved inbound IDs for Sanaei panel (supports legacy single inbound_id).
     *
     * @return int[]
     */
    public function resolveInboundIds(): array
    {
        $resolved = [];

        $appendIds = function (mixed $raw) use (&$resolved): void {
            if ($raw === null || $raw === '' || $raw === []) {
                return;
            }

            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $raw = $decoded;
                } else {
                    $parts = preg_split('/[,; ]+/', trim($raw));
                    $raw = array_filter($parts ?? [], fn ($part) => $part !== '');
                }
            }

            if (! is_array($raw)) {
                return;
            }

            foreach ($raw as $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $resolved[] = (int) $value;
            }
        };

        $appendIds($this->inbound_ids);
        $appendIds($this->attributes['inbound_ids'] ?? null);
        $appendIds(self::extractInboundIdsFromSampleInbound(
            $this->attributes['sample_inbound'] ?? null
        ));

        if ($resolved === [] && $this->inbound_id !== null) {
            $resolved[] = (int) $this->inbound_id;
        }

        $resolved = array_values(array_unique($resolved));
        sort($resolved);

        return $resolved;
    }

    /**
     * Marzban/PasarGuard inbounds map: protocol => [tag, ...]
     *
     * @return array<string, array<int, string>>
     */
    public function resolveMarzbanInbounds(): array
    {
        $raw = $this->marzban_inbounds;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $result = [];
        foreach ($raw as $protocol => $tags) {
            $protocolKey = strtolower((string) $protocol);
            if ($protocolKey === '' || ! is_array($tags)) {
                continue;
            }
            $normalizedTags = [];
            foreach ($tags as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $normalizedTags[] = $tag;
                }
            }
            if ($normalizedTags !== []) {
                $result[$protocolKey] = array_values(array_unique($normalizedTags));
            }
        }

        return $result;
    }

    /**
     * @return int[]
     */
    public function resolvePasarguardGroupIds(): array
    {
        $raw = $this->pasarguard_group_ids;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $ids[] = (int) $value;
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @return int[]
     */
    public static function extractInboundIdsFromSampleInbound(mixed $sampleInbound): array
    {
        if (! is_string($sampleInbound) || ! str_starts_with($sampleInbound, '__INBOUND_IDS__:')) {
            return [];
        }

        $line = explode("\n", $sampleInbound, 2)[0];
        $decoded = json_decode(substr($line, strlen('__INBOUND_IDS__:')), true);
        if (! is_array($decoded) || $decoded === []) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $decoded)));
    }

    public static function stripInboundIdsFromSampleInbound(mixed $sampleInbound): ?string
    {
        if (! is_string($sampleInbound) || $sampleInbound === '') {
            return null;
        }

        if (! str_starts_with($sampleInbound, '__INBOUND_IDS__:')) {
            return $sampleInbound;
        }

        $parts = explode("\n", $sampleInbound, 2);
        $rest = isset($parts[1]) ? trim($parts[1]) : '';

        return $rest !== '' ? $rest : null;
    }

    public function toArray()
    {
        $array = parent::toArray();
        $resolved = $this->resolveInboundIds();
        if ($resolved !== []) {
            $array['inbound_ids'] = $resolved;
            $array['inbound_id'] = $resolved[0];
        }

        return $array;
    }

    public function isAllowedForUserGroup(?int $userGroupId): bool
    {
        $allowed = $this->allowed_user_group_ids;
        if ($allowed === null || $allowed === []) {
            return true;
        }

        $normalized = $userGroupId ?? 0;

        return in_array($normalized, array_map('intval', $allowed), true);
    }

    public function shouldSendConfigToUser(): bool
    {
        if (! array_key_exists('send_config_to_user', $this->attributes)
            || $this->attributes['send_config_to_user'] === null) {
            return true;
        }

        return filter_var($this->attributes['send_config_to_user'], FILTER_VALIDATE_BOOLEAN);
    }

    public static function extractConfigLinks(mixed $configs): array
    {
        if ($configs === null || $configs === '') {
            return [];
        }

        if (is_string($configs)) {
            $decoded = json_decode($configs, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [$configs];
            }
            $configs = $decoded;
        }

        if (! is_array($configs)) {
            return [];
        }

        if (array_is_list($configs)) {
            return array_values(array_filter($configs, fn ($link) => is_string($link) && $link !== ''));
        }

        $links = $configs['links'] ?? [];
        if (! is_array($links)) {
            return [];
        }

        return array_values(array_filter($links, fn ($link) => is_string($link) && $link !== ''));
    }

    public function getSampleInboundAttribute($value)
    {
        $value = self::stripInboundIdsFromSampleInbound($value);
        if (!$value) {
            return null;
        }
        // سعی کن JSON decode کن، اگر نتوانست خود مقدار را برگردان
        $decoded = json_decode($value, true);
        return $decoded !== null ? $decoded : $value;
    }

    public function setSampleInboundAttribute($value)
    {
        // اگر آن یک آرایه است، JSON encode کن
        $this->attributes['sample_inbound'] = is_array($value) ? json_encode($value) : $value;
    }
    /**
     * Get the user that owns the ProductCategory
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pannel()
    {
        return $this->belongsTo(Pannel::class, 'pannel_id');
    }
    public function comments()
    {
        return $this->hasMany(AgentProduct::class, 'product_categories_id', 'id');
    }
    public function agent_products()
    {
        return $this->hasMany(AgentProduct::class, 'product_categories_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class, 'product_categories_id');
    }

    /**
     * Get product category by ID
     *
     * @param int $id
     * @return \App\Models\ProductCategory|null
     */
    public function getProdctCategorByID($id)
    {
        return self::find($id);
    }

    /**
     * Get product category by ID (properly named)
     *
     * @param int $id
     * @return \App\Models\ProductCategory|null
     */
    public function getProductCategoryByID($id)
    {
        return self::find($id);
    }

}
