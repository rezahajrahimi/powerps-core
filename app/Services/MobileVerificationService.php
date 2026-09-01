<?php

namespace App\Services;

use App\Http\Controllers\AdvanceSettingLookupController;
use App\Http\Controllers\CustomTextController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\SettingController;
use App\Models\BotUser;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class MobileVerificationService
{
    public const SETTING_KEY = 'bot_require_mobile_verification';

    public const IRAN_ONLY_SETTING_KEY = 'bot_mobile_verification_iran_only';

    public const AWAITING_CACHE_PREFIX = 'awaiting_reply_';

    public const PENDING_STATE = 'mobile_verification_pending';

    public function __construct(
        private readonly AdvanceSettingLookupController $settings = new AdvanceSettingLookupController(),
        private readonly CustomTextController $customText = new CustomTextController(),
        private readonly TelegramService $telegram = new TelegramService(),
        private readonly LogController $log = new LogController(),
    ) {}

    public function isRequired(): bool
    {
        return (bool) $this->settings->getValueByNameWithBooleanValue(self::SETTING_KEY);
    }

    public function isIranOnlyEnabled(): bool
    {
        return (bool) $this->settings->getValueByNameWithBooleanValue(self::IRAN_ONLY_SETTING_KEY);
    }

    public function isIranianPhoneNumber(string $phoneNumber): bool
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';
        if ($digits === '') {
            return false;
        }

        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 2);
        }

        if (preg_match('/^98(9\d{9})$/', $digits)) {
            return true;
        }

        if (preg_match('/^09\d{9}$/', $digits)) {
            return true;
        }

        if (preg_match('/^9\d{9}$/', $digits)) {
            return true;
        }

        return false;
    }

    public function findUserByAccountId(int|string $accountId): ?User
    {
        return User::where('account_id', $accountId)->first();
    }

    public function needsVerification(int|string $accountId): bool
    {
        if (! $this->isRequired()) {
            return false;
        }

        $user = $this->findUserByAccountId($accountId);
        if ($user === null || $user->role !== 'user') {
            return false;
        }

        return ! (bool) $user->is_verified;
    }

    /**
     * @return array{blocked: bool, message?: string}
     */
    public function purchaseBlockResponse(int|string $accountId): array
    {
        if (! $this->needsVerification($accountId)) {
            return ['blocked' => false];
        }

        $settingCtrl = new SettingController();

        return [
            'blocked' => true,
            'code' => 'mobile_verification_required',
            'message' => $this->customText->getText('error.mobile_verification.required'),
            'bot_username' => $settingCtrl->get_bot_name(),
        ];
    }

    public function blockBotPurchaseIfNeeded(int|string $chatId): bool
    {
        if (! $this->needsVerification($chatId)) {
            return false;
        }

        $this->promptVerification($chatId);

        return true;
    }

    public function promptVerification(int|string $chatId): void
    {
        $textKey = $this->isIranOnlyEnabled()
            ? 'action.mobile_verification.prompt_iran_only'
            : 'action.mobile_verification.prompt';
        $text = $this->customText->getText($textKey);
        if (is_array($text)) {
            $text = $this->telegram->formatText($text);
        }

        $buttonLabel = $this->customText->getText('action.mobile_verification.button');
        if (is_array($buttonLabel)) {
            $buttonLabel = $this->telegram->formatText($buttonLabel);
        }

        $buttons = [[['text' => (string) $buttonLabel, 'request_contact' => true]]];

        $this->telegram->sendMessage($chatId, (string) $text, [
            'reply_markup' => json_encode([
                'keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        Cache::put(self::AWAITING_CACHE_PREFIX.$chatId, self::PENDING_STATE, now()->addMinutes(15));
    }

    /**
     * @param  array<string, mixed>  $contact
     * @param  array<string, mixed>  $from
     * @return array{success: bool, message: string}
     */
    public function verifyFromContact(int|string $chatId, array $contact, array $from): array
    {
        if (! $this->isRequired()) {
            return [
                'success' => false,
                'message' => $this->customText->getText('error.mobile_verification.disabled'),
            ];
        }

        $user = $this->findUserByAccountId($chatId);
        if ($user === null || $user->role !== 'user') {
            return [
                'success' => false,
                'message' => $this->customText->getText('error.mobile_verification.not_applicable'),
            ];
        }

        if ((bool) $user->is_verified) {
            $this->clearPendingState($chatId);

            return [
                'success' => true,
                'message' => $this->customText->getText('action.mobile_verification.already_verified'),
            ];
        }

        $contactUserId = $contact['user_id'] ?? null;
        $senderId = $from['id'] ?? null;

        if ($contactUserId === null || $senderId === null || (int) $contactUserId !== (int) $senderId) {
            return [
                'success' => false,
                'message' => $this->customText->getText('error.mobile_verification.invalid_contact'),
            ];
        }

        $phoneNumber = trim((string) ($contact['phone_number'] ?? ''));
        if ($phoneNumber === '') {
            return [
                'success' => false,
                'message' => $this->customText->getText('error.mobile_verification.invalid_contact'),
            ];
        }

        if ($this->isIranOnlyEnabled() && ! $this->isIranianPhoneNumber($phoneNumber)) {
            return [
                'success' => false,
                'message' => $this->customText->getText('error.mobile_verification.iran_only'),
            ];
        }

        $botUser = BotUser::where('account_id', $chatId)->first();
        if ($botUser !== null) {
            $botUser->phone_number = $phoneNumber;
            if (! empty($contact['first_name'])) {
                $botUser->first_name = (string) $contact['first_name'];
            }
            if (array_key_exists('last_name', $contact)) {
                $botUser->last_name = (string) ($contact['last_name'] ?? '');
            }
            $botUser->save();
        }

        $user->is_verified = true;
        $user->save();

        $this->clearPendingState($chatId);

        $username = $botUser?->username ?? '';
        $this->log->addNewLog(
            'user',
            "تایید موبایل موفق - شماره: {$phoneNumber}",
            (string) $chatId,
            $username,
            'mobile_verified'
        );

        return [
            'success' => true,
            'message' => $this->customText->getText('action.mobile_verification.success'),
        ];
    }

    /**
     * @return array{required: bool, verified: bool, phone_number: ?string, bot_username: ?string, message?: string}
     */
    public function statusForAccount(int|string $accountId): array
    {
        $user = $this->findUserByAccountId($accountId);
        $required = $this->isRequired() && $user !== null && $user->role === 'user';
        $verified = $user ? (bool) $user->is_verified : false;
        $botUser = BotUser::where('account_id', $accountId)->first();
        $settingCtrl = new SettingController();

        $response = [
            'required' => $required,
            'verified' => $verified,
            'iran_only' => $required && $this->isIranOnlyEnabled(),
            'phone_number' => $botUser?->phone_number,
            'bot_username' => $settingCtrl->get_bot_name(),
        ];

        if ($required && ! $verified) {
            $response['message'] = $this->isIranOnlyEnabled()
                ? $this->customText->getText('error.mobile_verification.required_iran_only')
                : $this->customText->getText('error.mobile_verification.required');
        }

        return $response;
    }

    public function clearPendingState(int|string $chatId): void
    {
        Cache::forget(self::AWAITING_CACHE_PREFIX.$chatId);
    }

    public function isPendingState(?string $awaitingType): bool
    {
        return $awaitingType === self::PENDING_STATE;
    }
}
