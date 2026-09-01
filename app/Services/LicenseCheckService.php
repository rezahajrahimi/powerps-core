<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseCheckService
{
    private const CACHE_TTL_SECONDS = 780;

    public function normalizeHost(?string $host): ?string
    {
        if ($host === null || $host === '') {
            return null;
        }

        $host = trim($host);
        $host = preg_replace('#^https?://#i', '', $host);
        $host = explode('/', $host, 2)[0];

        return strtolower(rtrim($host, '/'));
    }

    public function getLicenseType(): string
    {
        if (env('APP_ENV') === 'development') {
            return 'false';
        }

        $host = $this->normalizeHost(env('FRONT_URL'));
        if ($host === null) {
            return 'false';
        }

        $licenseType = 'gold';
        $cacheKey = "license_check:{$host}:{$licenseType}";

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if ($cached !== 'false') {
                return (string) $cached;
            }
        }

        $accountType = $this->fetchLicenseType($host, $licenseType);
        if ($accountType === null) {
            return 'false';
        }

        if ($accountType !== 'false') {
            Cache::put($cacheKey, $accountType, self::CACHE_TTL_SECONDS);
        }

        return $accountType;
    }

    public function isMiddlewareLicenseValid(): bool
    {
        if (env('APP_ENV') === 'development') {
            return true;
        }

        $host = $this->normalizeHost(env('FRONT_URL'));
        if ($host === null) {
            return false;
        }

        $licenseType = 'gold';
        $cacheKey = "license_check:{$host}:{$licenseType}";

        if (Cache::has($cacheKey)) {
            $accountType = Cache::get($cacheKey);
            if ($accountType !== 'false') {
                return $this->isPaidLicenseType($accountType);
            }
        }

        $accountType = $this->fetchLicenseType($host, $licenseType);
        if ($accountType === null) {
            Log::warning('License check unreachable; allowing request temporarily', ['host' => $host]);
            return true;
        }

        if ($accountType !== 'false') {
            Cache::put($cacheKey, $accountType, self::CACHE_TTL_SECONDS);
        }

        return $this->isPaidLicenseType($accountType);
    }

    private function isPaidLicenseType(mixed $accountType): bool
    {
        return ! in_array($accountType, ['false', 'free', null], true);
    }

    private function fetchLicenseType(string $host, string $licenseType): ?string
    {
        $baseUrl = rtrim((string) env('LICENSE_CHECK_URL', 'https://license.powerps.ir'), '/');
        $url = "{$baseUrl}/api/checkLicense";

        try {
            $request = Http::timeout(10)
                ->connectTimeout(5)
                ->retry(2, 200);

            if ($hostHeader = env('LICENSE_CHECK_HOST')) {
                $request = $request->withHeaders(['Host' => $hostHeader]);
            }

            $response = $request->post($url, [
                'name' => 'Reza',
                'type' => $licenseType,
                'host' => $host,
                'admin_id' => env('TELEGRAM_ADMIN_ID'),
            ]);

            if (! $response->successful()) {
                return 'false';
            }

            $accountType = $response->json('data.account_type');
            if ($accountType === null || $accountType === '') {
                return 'false';
            }

            return (string) $accountType;
        } catch (\Throwable $e) {
            Log::warning('License check request failed', [
                'host' => $host,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
