<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SwapPayService
{
    protected string $apiKey;

    protected string $application;

    protected string $baseUrl = 'https://swapwallet.app/api';

    public function __construct(?string $apiKey = null, ?string $application = null)
    {
        $this->apiKey = trim((string) $apiKey);
        $this->application = trim((string) $application);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->application !== '';
    }

    public static function missingApplicationMessage(): string
    {
        return 'اپلیکیشن SwapPay پیدا نشد. در https://pay.swapwallet.app با تلگرام وارد شوید، یک Application بسازید و همان Application Username را وارد کنید — نه یوزرنیم حساب سواپ‌ولت و نه آیدی عددی کاربر.';
    }

    public static function personalAccountIdentifierMessage(): string
    {
        return 'این مقدار یوزرنیم یا آیدی حساب سواپ‌ولت است، نه Application Username. از پنل پذیرنده https://pay.swapwallet.app یک اپلیکیشن بسازید و نام همان اپ را کپی کنید.';
    }

    /**
     * @param  array<string, mixed>  $whoami
     */
    public static function isPersonalAccountIdentifier(string $application, array $whoami): bool
    {
        $application = trim($application);
        if ($application === '') {
            return false;
        }

        $username = trim((string) ($whoami['username'] ?? ''));
        $id = trim((string) ($whoami['id'] ?? ''));

        return ($username !== '' && strcasecmp($application, $username) === 0)
            || ($id !== '' && $application === $id);
    }

    /**
     * Validate API key + application username against SwapPay.
     *
     * @return array{ok: bool, message?: string}
     */
    public function validateCredentials(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'API Key و Application Username الزامی است.'];
        }

        $whoami = $this->whoami();
        if ($whoami === null) {
            return ['ok' => false, 'message' => 'API Key سواپ‌ولت نامعتبر است. کلید را از اپ سواپ‌ولت، بخش پروفایل ← کلید API کپی کنید.'];
        }

        if (self::isPersonalAccountIdentifier($this->application, $whoami)) {
            return ['ok' => false, 'message' => self::personalAccountIdentifierMessage()];
        }

        $exists = $this->applicationExists();
        if ($exists === false) {
            return ['ok' => false, 'message' => self::missingApplicationMessage()];
        }

        return ['ok' => true];
    }

    /**
     * @return array{success: bool, result?: array, error?: string, status?: int}
     */
    public function createInvoice(
        float|string $amountUsd,
        string $returnUrl,
        ?string $externalId = null,
        string $description = 'شارژ کیف پول',
        ?string $customData = null,
        string $autoConversionToken = 'USDT',
        int $ttl = 3600
    ): array {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'SwapPay credentials are not configured.'];
        }

        $payload = [
            'amount' => [
                'number' => (string) $amountUsd,
                'unit' => 'USD',
            ],
            'autoConversionToken' => $autoConversionToken,
            'ttl' => $ttl,
            'description' => $description,
            'returnUrl' => $returnUrl,
        ];

        if ($externalId !== null && $externalId !== '') {
            $payload['externalId'] = (string) $externalId;
        }
        if ($customData !== null && $customData !== '') {
            $payload['customData'] = $customData;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Apikey ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/v1/payment/' . rawurlencode($this->application) . '/invoice', $payload);

            $body = $response->json();
            Log::info('SwapPay createInvoice response', [
                'status' => $response->status(),
                'application' => $this->application,
            ]);

            if ($response->successful() && is_array($body) && isset($body['result']) && is_array($body['result'])) {
                return [
                    'success' => true,
                    'result' => $body['result'],
                    'status' => $response->status(),
                ];
            }

            return [
                'success' => false,
                'error' => $this->extractErrorMessage($body, $response->status()),
                'status' => $response->status(),
            ];
        } catch (\Throwable $th) {
            Log::error('SwapPay createInvoice exception', ['error' => $th->getMessage()]);

            return ['success' => false, 'error' => $th->getMessage()];
        }
    }

    /**
     * @return array{success: bool, result?: array, error?: string, status?: int}
     */
    public function getInvoice(string $invoiceId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'SwapPay credentials are not configured.'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Apikey ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl . '/v1/payment/' . rawurlencode($this->application) . '/invoice/' . rawurlencode($invoiceId));

            $body = $response->json();
            Log::info('SwapPay getInvoice response', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
            ]);

            if ($response->successful() && is_array($body) && isset($body['result']) && is_array($body['result'])) {
                return [
                    'success' => true,
                    'result' => $body['result'],
                    'status' => $response->status(),
                ];
            }

            return [
                'success' => false,
                'error' => $this->extractErrorMessage($body, $response->status()),
                'status' => $response->status(),
            ];
        } catch (\Throwable $th) {
            Log::error('SwapPay getInvoice exception', [
                'invoice_id' => $invoiceId,
                'error' => $th->getMessage(),
            ]);

            return ['success' => false, 'error' => $th->getMessage()];
        }
    }

    /**
     * Pick a payment URL from SwapPay paymentLinks.
     *
     * @param  array<int, array{type?: string, url?: string}>  $paymentLinks
     */
    public static function pickPaymentUrl(array $paymentLinks, array $preferredTypes = ['WEBSITE', 'TELEGRAM_WEBAPP', 'TELEGRAM_BOT']): ?string
    {
        $byType = [];
        foreach ($paymentLinks as $link) {
            if (! is_array($link)) {
                continue;
            }
            $type = strtoupper((string) ($link['type'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($type !== '' && $url !== '') {
                $byType[$type] = $url;
            }
        }

        foreach ($preferredTypes as $type) {
            $key = strtoupper($type);
            if (! empty($byType[$key]) && self::isUsablePaymentUrl($byType[$key])) {
                return $byType[$key];
            }
        }

        foreach ($byType as $url) {
            if (self::isUsablePaymentUrl($url)) {
                return $url;
            }
        }

        return null;
    }

    public static function isUsablePaymentUrl(?string $url): bool
    {
        $url = trim((string) $url);

        return $url !== '' && (bool) preg_match('#^(https?://|tg://)#i', $url);
    }

    public static function isPaidStatus(?string $status): bool
    {
        return in_array(strtoupper(trim((string) $status)), ['PAID', 'SUCCESS', 'COMPLETED', 'CONFIRMED'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function whoami(): ?array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders($this->authHeaders())
                ->get($this->baseUrl . '/v1/user/whoami');
            $body = $response->json();
            if ($response->successful() && is_array($body) && isset($body['result']) && is_array($body['result'])) {
                return $body['result'];
            }
        } catch (\Throwable $th) {
            Log::error('SwapPay whoami exception', ['error' => $th->getMessage()]);
        }

        return null;
    }

    public function applicationExists(): ?bool
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders($this->authHeaders())
                ->get($this->baseUrl . '/v1/payment/' . rawurlencode($this->application) . '/invoice', [
                    'page' => 1,
                    'limit' => 1,
                ]);

            if ($response->status() === 404) {
                return false;
            }
            if ($response->successful()) {
                return true;
            }
        } catch (\Throwable $th) {
            Log::error('SwapPay applicationExists exception', ['error' => $th->getMessage()]);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Apikey ' . $this->apiKey,
            'Accept' => 'application/json',
        ];
    }

    protected function extractErrorMessage(mixed $body, int $status): string
    {
        if ($status === 404) {
            return self::missingApplicationMessage();
        }

        if (is_array($body)) {
            $error = $body['error'] ?? null;
            if (is_array($error)) {
                return (string) ($error['localizedMessage'] ?? $error['message'] ?? 'خطا در ارتباط با SwapPay.');
            }
            if (is_string($error) && $error !== '') {
                return $error === 'Not Found' ? self::missingApplicationMessage() : $error;
            }
            if (! empty($body['message']) && is_string($body['message'])) {
                return $body['message'];
            }
        }

        return 'خطا در ارتباط با SwapPay.';
    }
}
