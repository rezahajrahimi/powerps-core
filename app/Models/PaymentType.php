<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'merchant_id', 'callback_url', 'type', 'is_active', 'is_sandbox'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_sandbox' => 'boolean',
    ];

    /**
     * Normalize a callback domain (scheme + host[+port], no path).
     * Accepts values like "example.com", "https://example.com/", "https://example.com/order".
     */
    public static function normalizeCallbackDomain(?string $stored): ?string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $stored) !== 1) {
            $stored = 'https://' . ltrim($stored, '/');
        }

        $parts = parse_url($stored);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'https';
        }

        $domain = $scheme . '://' . $parts['host'];
        if (! empty($parts['port'])) {
            $domain .= ':' . $parts['port'];
        }

        return $domain;
    }

    /**
     * Resolve absolute Zarinpal callback URL.
     * Only the domain is configurable; path is always /order.
     */
    public static function resolveZarinpalCallbackUrl(?string $callbackDomain, string $mainUrl): string
    {
        $domain = self::normalizeCallbackDomain($callbackDomain);
        if ($domain === null) {
            $domain = rtrim($mainUrl, '/');
        }

        return $domain . '/order';
    }

    /**
     * Get all of the comments for the PaymentType
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'payment_type_id');
    }
}
