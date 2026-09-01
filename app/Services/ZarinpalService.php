<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZarinpalService
{
    protected string $merchantId;
    protected bool $sandbox;
    protected string $callbackUrl;

    // Production endpoints
    protected string $requestUrl = 'https://api.zarinpal.com/pg/v4/payment/request.json';
    protected string $verifyUrl = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
    protected string $startPayUrl = 'https://www.zarinpal.com/pg/StartPay/';

    // Sandbox endpoints
    protected string $sandboxRequestUrl = 'https://sandbox.zarinpal.com/pg/v4/payment/request.json';
    protected string $sandboxVerifyUrl = 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json';
    protected string $sandboxStartPayUrl = 'https://sandbox.zarinpal.com/pg/StartPay/';

    public function __construct(?string $merchantId = null, ?bool $sandbox = null, ?string $callbackUrl = null)
    {
        $this->merchantId = $merchantId ?? env('ZARINPAL_MERCHANT_ID', '');
        $this->sandbox = $sandbox ?? (env('ZARINPAL_SANDBOX', false) == true || env('ZARINPAL_MODE', 'normal') === 'sandbox');
        $this->callbackUrl = $callbackUrl ?? env('ZARINPAL_CALLBACK_URL', url('/order'));
    }

    /**
     * Set merchant ID at runtime
     */
    public function setMerchantId(string $merchantId): self
    {
        $this->merchantId = $merchantId;
        return $this;
    }

    /**
     * Enable or disable sandbox mode
     */
    public function setSandbox(bool $sandbox): self
    {
        $this->sandbox = $sandbox;
        return $this;
    }

    /**
     * Set callback URL
     */
    public function setCallbackUrl(string $callbackUrl): self
    {
        $this->callbackUrl = $callbackUrl;
        return $this;
    }

    /**
     * Get the appropriate request URL based on mode
     */
    protected function getRequestUrl(): string
    {
        return $this->sandbox ? $this->sandboxRequestUrl : $this->requestUrl;
    }

    /**
     * Get the appropriate verify URL based on mode
     */
    protected function getVerifyUrl(): string
    {
        return $this->sandbox ? $this->sandboxVerifyUrl : $this->verifyUrl;
    }

    /**
     * Get the appropriate payment start URL based on mode
     */
    protected function getStartPayUrl(): string
    {
        return $this->sandbox ? $this->sandboxStartPayUrl : $this->startPayUrl;
    }

    /**
     * Request a new payment
     *
     * @param int $amount Amount (In Toman)
     * @param string $description Payment description
     * @param string|null $email Optional customer email
     * @param string|null $mobile Optional customer mobile
     * @return array ['success' => bool, 'authority' => string|null, 'url' => string|null, 'error' => string|null, 'code' => int|null]
     */
    public function request(int $amount, string $description = 'پرداخت', ?string $email = null, ?string $mobile = null): array
    {
        // Zarinpal API v4 expects amount in Toman.
        $data = [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'callback_url' => $this->callbackUrl,
            'description' => $description,
        ];

        if ($email) {
            $data['metadata']['email'] = $email;
        }
        if ($mobile) {
            $data['metadata']['mobile'] = $mobile;
        }

        Log::info('Zarinpal request', [
            'url' => $this->getRequestUrl(),
            'sandbox' => $this->sandbox,
            'amount' => $amount,
            'callback' => $this->callbackUrl,
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->getRequestUrl(), $data);

            $result = $response->json();

            Log::info('Zarinpal response', ['response' => $result]);

            if (isset($result['data']) && isset($result['data']['code']) && $result['data']['code'] == 100) {
                $authority = $result['data']['authority'];
                return [
                    'success' => true,
                    'authority' => $authority,
                    'url' => $this->getStartPayUrl() . $authority,
                    'error' => null,
                    'code' => 100,
                ];
            }

            // Handle errors
            $errorCode = $result['errors']['code'] ?? ($result['data']['code'] ?? -1);
            $errorMessage = $result['errors']['message'] ?? $this->getErrorMessage($errorCode);

            return [
                'success' => false,
                'authority' => null,
                'url' => null,
                'error' => $errorMessage,
                'code' => $errorCode,
            ];
        } catch (\Exception $e) {
            Log::error('Zarinpal request exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'authority' => null,
                'url' => null,
                'error' => 'خطا در برقراری ارتباط با درگاه پرداخت: ' . $e->getMessage(),
                'code' => -1,
            ];
        }
    }

    /**
     * Verify a payment
     *
     * @param string $authority The authority code from callback
     * @param int $amount Amount (In Toman)
     * @return array ['success' => bool, 'ref_id' => string|null, 'card_pan' => string|null, 'error' => string|null, 'code' => int|null]
     */
    public function verify(string $authority, int $amount): array
    {
        // Zarinpal API v4 expects amount in Toman.
        $data = [
            'merchant_id' => $this->merchantId,
            'authority' => $authority,
            'amount' => $amount,
        ];

        Log::info('Zarinpal verify request', [
            'url' => $this->getVerifyUrl(),
            'sandbox' => $this->sandbox,
            'authority' => $authority,
            'amount' => $amount,
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->getVerifyUrl(), $data);

            $result = $response->json();

            Log::info('Zarinpal verify response', ['response' => $result]);

            if (isset($result['data']) && isset($result['data']['code']) && in_array($result['data']['code'], [100, 101])) {
                return [
                    'success' => true,
                    'ref_id' => $result['data']['ref_id'] ?? null,
                    'card_pan' => $result['data']['card_pan'] ?? null,
                    'error' => null,
                    'code' => $result['data']['code'],
                ];
            }

            // Handle errors
            $errorCode = $result['errors']['code'] ?? ($result['data']['code'] ?? -1);
            $errorMessage = $result['errors']['message'] ?? $this->getErrorMessage($errorCode);

            return [
                'success' => false,
                'ref_id' => null,
                'card_pan' => null,
                'error' => $errorMessage,
                'code' => $errorCode,
            ];
        } catch (\Exception $e) {
            Log::error('Zarinpal verify exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'ref_id' => null,
                'card_pan' => null,
                'error' => 'خطا در برقراری ارتباط با درگاه پرداخت: ' . $e->getMessage(),
                'code' => -1,
            ];
        }
    }

    /**
     * Get error message for error codes
     */
    protected function getErrorMessage(int $code): string
    {
        $messages = [
            -1 => 'اطلاعات ارسال شده ناقص است.',
            -2 => 'IP و یا مرچنت کد پذیرنده صحیح نیست.',
            -3 => 'با توجه به محدودیت‌های شاپرک امکان پرداخت با رقم درخواست شده میسر نمی‌باشد.',
            -4 => 'سطح تأیید پذیرنده پایین‌تر از سطح نقره‌ای است.',
            -9 => 'خطای اعتبارسنجی.',
            -10 => 'آی‌پی و یا مرچنت کد پذیرنده صحیح نیست.',
            -11 => 'مرچنت کد فعال نیست.',
            -12 => 'تلاش بیش از حد مجاز در یک بازه زمانی کوتاه.',
            -15 => 'ترمینال شما به حالت تعلیق درآمده است.',
            -16 => 'سطح تأیید پذیرنده پایین‌تر از سطح نقره‌ای است.',
            -21 => 'هیچ نوع عملیات مالی برای این تراکنش یافت نشد.',
            -22 => 'تراکنش ناموفق بوده است.',
            -30 => 'اجازه دسترسی به تسویه اشتراکی شناور ندارید.',
            -31 => 'حساب بانکی تسویه را به پنل اضافه کنید.',
            -32 => 'Wages is not valid.',
            -33 => 'درصد و یا مبلغ تسویه جمع مبلغ تقسیم شده بیش از مبلغ تراکنش است.',
            -34 => 'مبلغ تقسیم شده کمتر از حداقل مبلغ مجاز است.',
            -35 => 'تعداد تقسیم بیش از حداکثر مجاز است.',
            -40 => 'پارامترهای اضافی نامعتبر است.',
            -50 => 'مبلغ پرداخت شده با مقدار مبلغ تراکنش یکسان نیست.',
            -51 => 'پرداخت ناموفق.',
            -52 => 'خطای غیرمنتظره‌ای رخ داده است.',
            -53 => 'authority صحیح نیست.',
            -54 => 'authority غیرفعال است.',
            100 => 'عملیات موفق.',
            101 => 'تراکنش قبلاً وریفای شده است.',
        ];

        return $messages[$code] ?? 'خطای ناشناخته رخ داده است.';
    }

    /**
     * Check if sandbox mode is enabled
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }
}
