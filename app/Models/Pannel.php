<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pannel extends Model
{
    use HasFactory;

    public const TYPE_MARZBAN = 'marzban';

    public const TYPE_PASARGUARD = 'pasarguard';

    public const TYPE_INVENTORY = 'custome';

    public const TYPE_HIDDIFY = 'hiddify';

    public const TYPE_SANAEI = 'sanaei';

    protected $guarded = ['id'];
    protected $fillable = ['type', 'api_version', 'username', 'password', 'token', 'location', 'url_port', 'sub_port', 'admin_url', 'capacity', 'secret_code', 'cookie_session', 'user_link'];

    public static function marzbanCompatibleTypes(): array
    {
        return [self::TYPE_MARZBAN, self::TYPE_PASARGUARD];
    }

    public static function isMarzbanCompatibleType(?string $type): bool
    {
        return in_array($type, self::marzbanCompatibleTypes(), true);
    }

    public function isMarzbanCompatible(): bool
    {
        return self::isMarzbanCompatibleType($this->type);
    }

    public function isInventoryPanel(): bool
    {
        return $this->type === self::TYPE_INVENTORY;
    }

    public static function isInventoryPanelType(?string $type): bool
    {
        return $type === self::TYPE_INVENTORY;
    }

    public static function remarkRenameSupportedTypes(): array
    {
        return [self::TYPE_HIDDIFY, self::TYPE_SANAEI];
    }

    public static function supportsRemarkRenameType(?string $type): bool
    {
        return in_array($type, self::remarkRenameSupportedTypes(), true);
    }

    public function supportsRemarkRename(): bool
    {
        return self::supportsRemarkRenameType($this->type);
    }

    /**
     * Maps marzban-based custom_text keys to pasarguard when needed.
     */
    public static function resolveCustomTextKey(string $marzbanBasedKey, ?string $panelType): string
    {
        if ($panelType === self::TYPE_PASARGUARD) {
            return str_replace('marzban', 'pasarguard', $marzbanBasedKey);
        }

        return $marzbanBasedKey;
    }

    public function customTextKey(string $marzbanBasedKey): string
    {
        return self::resolveCustomTextKey($marzbanBasedKey, $this->type);
    }

    /**
     * Get all of the comments for the Pannel
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function proxies()
    {
        return $this->hasMany(Proxy::class, 'pannel_id');
    }
    public function product_category()
    {
        return $this->hasMany(ProductCategory::class, 'pannel_id');
    }
    public function product_category_and_count_products()
    {
        // get count of products count by realation of product category
        return $this->hasMany(ProductCategory::class, 'pannel_id')->withCount('products');

    }
}
