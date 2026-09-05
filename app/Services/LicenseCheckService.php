<?php

namespace App\Services;

/**
 * Open-source builds always run as gold — no remote license server.
 */
class LicenseCheckService
{
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
        return 'gold';
    }

    public function isMiddlewareLicenseValid(): bool
    {
        return true;
    }
}
