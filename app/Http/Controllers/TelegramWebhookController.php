<?php
namespace App\Http\Controllers;

use App\Http\Controllers\AccountProcessController;
use App\Http\Controllers\CustomTextController;
use App\Http\Controllers\SubscriptionProcessController;
use App\Models\BotUser;
use App\Models\User;
use App\Models\UserState;
use App\Models\Transaction;
use App\Services\TelegramMessageFormatter;
use App\Services\TelegramService;
use App\Services\TelegramCallbackHandler;
use App\Services\MobileVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TelegramWebhookController extends Controller
{
    private TelegramService $telegramService;
    private CustomTextController $customTextCtrl;
    private SubscriptionProcessController $subscriptionProcessCtrl;
    private TransactionController $transactionCntrl;
    private GeneralController $generalCntrl;
    private AccountProcessController $accountProcessCtrl;
    private AuthController $authCntrl;
    private BlockedUserController $blockedUserCtrl;
    private UserController $userCtrl;
    private TelegramCallbackHandler $callbackHandler;
    private $chatId;
    private ?string $pendingStartPayload = null;

    public function __construct(TelegramService $telegramService, TelegramCallbackHandler $callbackHandler)
    {
        $this->telegramService = $telegramService;
        $this->customTextCtrl = new CustomTextController();
        $this->subscriptionProcessCtrl = new SubscriptionProcessController($this->telegramService);
        $this->transactionCntrl = new TransactionController();
        $this->generalCntrl = new GeneralController();
        $this->accountProcessCtrl = new AccountProcessController($this->telegramService);
        $this->authCntrl = new AuthController();
        $this->blockedUserCtrl = new BlockedUserController();
        $this->userCtrl = new UserController();
        $this->callbackHandler = $callbackHandler;
        $this->callbackHandler->setWebhookController($this);
    }

    public function handle(Request $request)
    {
        try {
            // handle the first time bit start
            if ($this->is_first_time_bot_start_event()) {
                return response()->json(['status' => 'success']);
            }
            $update = $request->all();

            // Telegram retries the same update when our webhook is slow (>~60s).
            // Deduplicate by update_id so photos / messages are not processed repeatedly.
            $updateId = $update['update_id'] ?? null;
            if ($updateId !== null) {
                $dedupeKey = 'telegram_webhook_update_' . $updateId;
                if (! Cache::add($dedupeKey, 1, now()->addDay())) {
                    \Log::info('Skipping duplicate telegram update', ['update_id' => $updateId]);
                    return response()->json(['status' => 'success', 'deduplicated' => true]);
                }
            }

            $this->chatId = $update['message']['chat']['id'] ?? $update['callback_query']['from']['id'] ?? null;

            try {
                if (isset($update['message'])) {
                    $isBlocked = $this->blockedUserCtrl->isBlocked($this->chatId);
                    if ($isBlocked) {
                        $text = $this->customTextCtrl->getText('error.blocked_user');
                        $this->telegramService->sendMessage($this->chatId, $text);
                        return response()->json(['status' => 'success']);
                    }
                }
            } catch (\Exception $e) {
                Log::error('خطا در پردازش webhook تلگرام: ' . $e->getMessage());
                // return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }

            // پردازش callback queries (دکمه‌های اینلاین)
            if (isset($update['callback_query'])) {
                return $this->handleCallbackQuery($update['callback_query']);
            }

            $message = $update['message'] ?? null;
            if (!$message) {
                return response()->json(['status' => 'success']);
            }

            $chatId = $this->chatId;
            $this->pendingStartPayload = self::extractStartPayload(
                isset($message['text']) && is_string($message['text']) ? $message['text'] : null
            );

            // Referral must be saved even if channel lock blocks the rest of /start.
            if ($this->pendingStartPayload !== null) {
                $this->handleReferralCommand('/start ' . $this->pendingStartPayload);
            }

            // check the chatId is exist in users on account_id
            $isChannelMember = $this->checkChannelLock();
            if (!$isChannelMember) {
                return response()->json(['status' => 'success']);
            }

            // نمایش وضعیت تایپ کردن
            $this->telegramService->sendChatAction($this->chatId, 'typing');

            // پردازش انواع مختلف پیام
            if (isset($message['text'])) {
                // پاسخ اجباری ادمین (مثل مبلغ رسید) باید قبل از منو پردازش شود
                if ($this->awaitingReply($this->chatId)) {
                    $this->handleAwaitingReply($this->chatId, $message['text']);
                    return response()->json(['status' => 'success']);
                }

                $response = $this->processTextMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['photo'])) {
                $response = $this->processPhotoMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['document'])) {
                $response = $this->processDocumentMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['location'])) {
                $response = $this->processLocationMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['voice'])) {
                $response = $this->processVoiceMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['video'])) {
                $response = $this->processVideoMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            } elseif (isset($message['contact'])) {
                $response = $this->processContactMessage($message);
                $this->sendResponseIfNotEmpty($this->chatId, $response);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('خطا در پردازش webhook تلگرام: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function processTextMessage(array $message): string|array
    {
        try {
            $text = $message['text'];
            ///

            // پردازش دستورات
            if (str_starts_with($text, '/')) {
                return $this->processCommand($text);
            }

            $promoState = \App\Models\UserState::where('chat_id', $this->chatId)
                ->whereIn('state', ['promo_code_pending', 'promo_code_pending_recharge'])
                ->latest()
                ->first();
            if ($promoState) {
                $this->subscriptionProcessCtrl->handlePromoCodeReply($this->chatId, trim($text));
                return "";
            }

            // check if text is a menu item
            $menuItemCtrl = new MainMenuItemController();
            $menuItem = $menuItemCtrl->getMenuItemByAliasName($text);
            if ($menuItem) {
                $response = $this->processMenuCommand($menuItem);
                // if response == true or false or null, don't return anything
                if ($response == true || $response == false || $response == null || $response == 1 || $response == 0) {
                    return "";
                }
                return $response;
            }
            // return main menu items
            $chatId = $this->getCurrentChatId();
            $this->generalCntrl->return_main_menu_items($chatId, $text);
            // check if text is a gift card
            if (str_starts_with(strtolower($text), 'giftcard-')) {
                $this->generalCntrl->subGiftCard($chatId, $text);
                return "";
            }
            if (str_starts_with(strtolower($text), 'charge') !== false) {
                $actionList = explode('-', $text);

                return $this->accountProcessCtrl->adminFastCharge($chatId, $actionList[2], $actionList[1]);

            }
            if (str_starts_with(strtolower($text), 'block') !== false) {

                // check chatId is user and have admin role
                $user = new User();
                $user = $user->get_role_by_account_id($chatId);
                if ($user != 'admin') {
                    $text = $this->customTextCtrl->getText('error.action.not_found');
                    $this->telegramService->sendMessage($chatId, $text);
                    return "";
                }
                $actionList = explode('-', $text);
                $this->generalCntrl->block_user_command('block', $actionList[1], $actionList[2]);
                $text = $this->customTextCtrl->getText('action.block_user.success');
                $this->telegramService->sendMessage($chatId, $text);
                return "";
            }
            if (str_starts_with(strtolower($text), 'unblock') !== false) {
                // check chatId is admin
                $user = new User();
                $user = $user->get_role_by_account_id($chatId);
                if ($user != 'admin') {
                    $text = $this->customTextCtrl->getText('error.action.not_found');
                    $this->telegramService->sendMessage($chatId, $text);
                    return "";
                }
                $actionList = explode('-', $text);
                $this->generalCntrl->block_user_command('unblock', $actionList[1], null);
                $text = $this->customTextCtrl->getText('action.unblock_user.success');
                $this->telegramService->sendMessage($chatId, $text);

                return "";
            }

            return "پیام متنی شما دریافت شد: " . $text;
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش processTextMessage: " . $th->getMessage());
            $this->telegramService->sendMessage($this->chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    private function processMenuCommand($menuItem)
    {
        $this->addNewBotLog('menu', "وارد منوی {$menuItem->name} ربات شد.", 'show');
        $chatId = $this->getCurrentChatId();
        switch ($menuItem->name) {
            case 'خرید اشتراک':
                return $this->subscriptionProcessCtrl->buySubscriptionMenu($chatId);
                break;
            case 'اطلاعات حساب':
                return $this->accountProcessCtrl->accountDetails($chatId);
                break;
            case 'سابقه خرید':
                return $this->subscriptionProcessCtrl->buyHistory($chatId, 1);
                break;
            case 'پشتیبانی':
                return $this->generalCntrl->support($chatId);
                break;
            case 'آموزش استفاده و سوالات متداول':
                return $this->generalCntrl->getFaqs($chatId);
                break;
            case 'دانلود برنامه':
                return $this->generalCntrl->appDownload($chatId);
                break;
            case 'گیفت کارت':
                return $this->generalCntrl->giftCard($chatId);
                break;
            case 'اکانت آزمایشی':
                return $this->generalCntrl->testAccount($chatId);
                break;
            case 'webapp':
                return $this->authCntrl->generate_auto_login_link(new Request(['account_id' => $chatId]));
                break;
            case 'کسب درآمد':
                return $this->generalCntrl->subReferral($chatId);
                break;

            default:
                return $this->customTextCtrl->getText('error.menu.not_found');
                break;
        }
        return $this->customTextCtrl->getText('error.menu.not_found');
    }

    private function processPhotoMessage(array $message): string|array
    {
        try {
            $photos = $message['photo'];
            $photo = end($photos); // بزرگترین سایز عکس
            $fileId = $photo['file_id'];
            $caption = $message['caption'] ?? '';
            $chatId = $message['chat']['id'];
            $messageId = $message['message_id'] ?? null;

            // Extra guard if Telegram somehow resends without same update_id.
            if ($messageId !== null) {
                $photoKey = "telegram_photo_{$chatId}_{$messageId}";
                if (! Cache::add($photoKey, 1, now()->addDay())) {
                    \Log::info('Skipping duplicate telegram photo', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                    ]);
                    return "";
                }
            }

            // دریافت اطلاعات فایل از تلگرام
            $fileInfo = $this->telegramService->getFile($fileId);
            if (!isset($fileInfo['result']['file_path'])) {
                $text = $this->customTextCtrl->getText('action.server_error');
                $this->telegramService->sendMessage($chatId, $text);
                return "";
            }

            $request = new Request();
            $transactionId = $this->transactionCntrl->addUserTranaction($chatId, 0, '000', 0);
            if ($transactionId === null || $transactionId === false || $transactionId === '') {
                \Log::warning('Receipt photo received but transaction was not created', [
                    'account_id' => $chatId,
                ]);
                $transactionId = 0;
            }
            $request->transaction_id = $transactionId;
            $request->img_src = $fileInfo['result']['file_path']; // ارسال file_path به جای file_id
            $request->account_id = $chatId;
            $request->user_text = $caption ?? 'بدون متن';

            // Acknowledge user ASAP so Telegram does not keep retrying the webhook.
            $text = $this->customTextCtrl->getText('action.send_photo.success', [
                'name' => $this->getCurrentChatFirstName(),
            ]);
            $this->telegramService->sendMessage($chatId, $text);

            if ((int) $transactionId > 0) {
                $imageTrCntrl = new TransactionImageController();
                $imageTrCntrl->saveNewTransactionImage($request);
            }
            $this->addUserBotLog($chatId, 'payment', 'تصویر رسید پرداخت آفلاین ارسال شد', 'upload');
            $this->sendMessageToAdmin($chatId, $fileId, (int) $transactionId, 'image');
            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش تصویر: " . $th->getMessage());
            return "با پشتیبان ربات تماس بگیرید ،خطا در دریافت تصویر";
        }
    }

    private function processDocumentMessage(array $message): string|array
    {
        $document = $message['document'];
        $fileId = $document['file_id'];
        $fileName = $document['file_name'] ?? 'بدون نام';
        $mimeType = $document['mime_type'] ?? 'نامشخص';

        return "فایل شما با نام {$fileName} و نوع {$mimeType} دریافت شد.";
    }

    private function processLocationMessage(array $message): string|array
    {
        $location = $message['location'];
        $latitude = $location['latitude'];
        $longitude = $location['longitude'];

        return "موقعیت مکانی شما در مختصات {$latitude}, {$longitude} دریافت شد.";
    }

    private function processVoiceMessage(array $message): string|array
    {
        $voice = $message['voice'];
        $fileId = $voice['file_id'];
        $duration = $voice['duration'];

        // ذخیره فایل صوتی
        $fileInfo = $this->telegramService->getFile($fileId);
        if (isset($fileInfo['result']['file_path'])) {
            $fileContent = $this->telegramService->downloadFile($fileInfo['result']['file_path']);
            Storage::put("telegram/voices/{$fileId}.ogg", $fileContent);
        }

        return "پیام صوتی شما با مدت زمان {$duration} ثانیه دریافت شد.";
    }

    private function processVideoMessage(array $message): string|array
    {
        $video = $message['video'];
        $fileId = $video['file_id'];
        $duration = $video['duration'];
        $caption = $message['caption'] ?? '';

        // ذخیره ویدیو
        $fileInfo = $this->telegramService->getFile($fileId);
        if (isset($fileInfo['result']['file_path'])) {
            $fileContent = $this->telegramService->downloadFile($fileInfo['result']['file_path']);
            Storage::put("telegram/videos/{$fileId}.mp4", $fileContent);
        }

        return "ویدیوی شما با مدت زمان {$duration} ثانیه دریافت شد." .
            ($caption ? "\nکپشن: {$caption}" : '');
    }

    private function processContactMessage(array $message): string|array
    {
        $contact = $message['contact'];
        $chatId = $message['chat']['id'] ?? $message['from']['id'] ?? null;
        $from = $message['from'] ?? [];

        if ($chatId === null) {
            return $this->customTextCtrl->getText('error.server_error');
        }

        $mobileVerification = new MobileVerificationService();
        $result = $mobileVerification->verifyFromContact($chatId, $contact, $from);

        if ($result['success']) {
            $this->generalCntrl->return_main_menu_items($chatId, $result['message']);

            return '';
        }

        return $result['message'];
    }

    public static function extractStartPayload(?string $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $text = trim($text);
        if ($text === '' || ! str_starts_with($text, '/')) {
            return null;
        }

        if (! preg_match('#^/(?:start|restart)(?:@[A-Za-z0-9_]+)?(?:\s+|$)(.*)$#s', $text, $matches)) {
            return null;
        }

        $payload = trim((string) ($matches[1] ?? ''));

        return $payload === '' ? null : $payload;
    }

    public static function channelLockResumeStartParam(?string $payload): string
    {
        if (is_string($payload) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $payload) === 1) {
            return $payload;
        }

        return 'start';
    }

    private function processCommand(string $text): string|array
    {
        $parts = explode(' ', $text);
        $command = $parts[0];
        $ref = $parts[1] ?? null;
        $ref != null ? $this->handleReferralCommand($text) : null;
        if ($ref != null) {
            $command = '/start';
        }

        $response = match ($command) {
            '/start' => $this->handleStartCommand($text),
            '/restart' => $this->handleStartCommand($text),
            '/help' => $this->handleHelpCommand(),
            '/menu' => $this->handleMenuCommand(),
            default => $this->customTextCtrl->getText('error.command.not_found')
        };
        return $response;
    }
    public function checkChannelLock()
    {
        try {
            $chatId = $this->getCurrentChatId();
            $channelLockCtrl = new ChannelLockController();
            $channels = $channelLockCtrl->getAllActiveChannelLock();
            $notJoinedChannels = [];

            if ($channels->count() > 0) {
                foreach ($channels as $channel) {
                    $channelId = $channel->channel_id;
                    // حذف @ از ابتدای نام کانال اگر وجود داشته باشد
                    $channelId = ltrim($channelId, '@');

                    $isChannelMember = $this->telegramService->checkChatIdIsChannelMember($chatId, $channelId);
                    if (!$isChannelMember) {
                        // اصلاح ساختار آرایه دکمه‌ها
                        $notJoinedChannels[] = [
                            'text' => "@" . $channelId,
                            'url' => "https://t.me/" . $channelId,
                        ];
                    }
                }

                if (count($notJoinedChannels) > 0) {
                    $text = $this->customTextCtrl->getText('action.chanel_lock_text');
                    // add start link by reflink
                    // get bot name from setting controller
                    $settingCntrl = new SettingController();
                    $botName = $settingCntrl->get_bot_name();
                    $startParam = self::channelLockResumeStartParam($this->pendingStartPayload);
                    $notJoinedChannels[] = [
                        'text' => "عضو شدم",
                        'url' => "https://t.me/" . $botName . "?start=" . $startParam,
                    ];
                    // add start command to $notJoinedChannels

                    $this->telegramService->sendMessageWithLinkButtons($chatId, $text, $notJoinedChannels);



                    return false;
                }
            }

            return true;

        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش checkChannelLock: " . $th->getMessage());
            return true;
        }
    }

    private function handleStartCommand(string $message, ): string|array
    {
        try {
            $chatId = $this->getCurrentChatId();
            $firstName = $this->getCurrentChatFirstName();
            $lastName = $this->getCurrentChatLastName();
            $userName = $this->getCurrentChatUserName();
            $referralLogsCntrl = new ReferralLogsController();
            $botUserCtrl = new BotUserController();

            $botUserCtrl->hasRegistred($chatId, $userName, $firstName, $lastName);

            $welcomeFormats = $this->customTextCtrl->getText('action.welcome.message', [
                'name' => $firstName,
                'lastName' => $lastName,
            ]);
            if (is_array($welcomeFormats)) {
                $welcomeFormats = $this->telegramService->formatText($welcomeFormats);
            }

            $this->generalCntrl->return_main_menu_items($chatId, $welcomeFormats);
            return '';

        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش handleStartCommand: " . $th->getMessage());
            return $this->customTextCtrl->getText('error.server_error');

        }
    }
    public function handleHelpCommand($action = null): string|array
    {
        if ($action == 'faqs') {
            return $this->generalCntrl->getFaqs($this->getCurrentChatId());
        }
        if ($action == 'appDownload') {
            return $this->generalCntrl->appDownload();
        }
        // $text = $this->customTextCtrl->getText('action.help.message');
        // $this->generalCntrl->return_main_menu_items($this->getCurrentChatId(), $text);
        return "";
    }

    public function handleReferralCommand($text): string|array
    {
        try {
            $parts = explode(' ', $text);
            $ref = $parts[1] ?? null;
            $chatId = $this->getCurrentChatId();
            $firstName = $this->getCurrentChatFirstName();
            $lastName = $this->getCurrentChatLastName();
            $userName = $this->getCurrentChatUserName();
            $referralLogsCntrl = new ReferralLogsController();
            $botUserCtrl = new BotUserController();

            $result = $botUserCtrl->hasRegistred($chatId, $userName, $firstName, $lastName);
            if ($ref != null) {
                $referralSettingCntrl = new ReferralSettingController();
                if ($referralSettingCntrl->check_referral_setting_is_active()) {
                    $referralLogsCntrl->check_user_has_referral_and_create($chatId, $ref);
                }
            }
            return '/start';
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش handleReferralCommand: " . $th->getMessage());
            return '/start';
        }
    }

    private function handleMenuCommand(): string|array
    {
        $chatId = $this->getCurrentChatId();

        $buttons = [
            ['ارسال موقعیت مکانی' => 'send_location', 'ارسال شماره تماس' => 'send_contact'],
            ['آپلود فایل' => 'upload_file', 'ارسال عکس' => 'send_photo'],
            ['راهنما' => 'help', 'بازگشت' => 'back'],
        ];

        $this->telegramService->sendMessageWithInlineKeyboard(
            $chatId,
            "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:",
            $buttons
        );

        return '';
    }

    private function handleCallbackQuery(array $callbackQuery): \Illuminate\Http\JsonResponse
    {
        $chatId = $callbackQuery['from']['id'];
        $data = $callbackQuery['data'];
        $callbackQueryId = $callbackQuery['id'];
        $messageId = $callbackQuery['message']['message_id'] ?? null;

        \Log::info("handleCallbackQuery data=> {$data}");

        // explode the data to get the action
        $actionList = explode('-', $data);
        $action = array_shift($actionList); // Get action and remove it from list
        $params = $actionList; // Remaining items are params

        if (in_array($action, ['confirmBuyPromo', 'confirmRechargePromo'], true) && count($params) >= 2) {
            $entityId = array_shift($params);
            $params = [$entityId, implode('-', $params)];
        }

        $response = $this->callbackHandler->handle($chatId, $action, $params, $messageId, $callbackQueryId);

        $alertText = $this->customTextCtrl->getText('action.process.on_progress');
        $showAlert = false;

        if (is_array($response) && isset($response['alert'])) {
            $alertText = $response['alert'];
            $showAlert = $response['show_alert'] ?? true;
            $response = null;
        }

        // ارسال پاسخ به callback query
        $this->telegramService->answerCallbackQuery(
            $callbackQueryId,
            $alertText,
            $showAlert
        );

        if ($response && ($response != "" || $response != null || $response != " ")) {
            $this->telegramService->sendMessage($chatId, $response);
        }
        return response()->json(['status' => 'success']);
    }
    private function handleCancelPayment(string $chatId): string
    {

        $this->telegramService->sendMessage($chatId, 'پرداخت با موفقیت لغو شد.');
        return '';
    }
    private function handleAction1(string $chatId): string
    {
        // مثال درخواست اطلاعات از کاربر
        $this->setAwaitingReply($chatId, 'action_1_reply');
        return $this->telegramService->forceReply($chatId, "لطفاً نام خود را وارد کنید:");
    }

    private function handleAction2(string $chatId): string
    {
        // درخواست شماره تماس با کیبورد مخصوص
        $buttons = [[['text' => 'ارسال شماره تماس', 'request_contact' => true]]];
        $this->telegramService->sendMessage($chatId, 'لطفاً شماره تماس خود را به اشتراک بگذارید:', [
            'reply_markup' => json_encode([
                'keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]),
        ]);

        return '';
    }

    private function handleAction3(string $chatId): string
    {
        // درخواست موقعیت مکانی با کیبورد مخصوص
        $buttons = [[['text' => 'ارسال موقعیت مکانی', 'request_location' => true]]];
        $this->telegramService->sendMessage($chatId, 'لطفاً موقعیت مکانی خود را به اشتراک بگذارید:', [
            'reply_markup' => json_encode([
                'keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]),
        ]);

        return '';
    }

    private function handleAwaitingReply(string $chatId, string $text): void
    {
        if ($this->telegramService->isCancelOrExitText($text)) {
            $this->cancelAwaitingInput($chatId, $text);
            return;
        }

        $awaitingType = $this->getAwaitingReplyType($chatId);

        if ($awaitingType && str_starts_with($awaitingType, 'awaiting_receipt_amount:')) {
            $payload = substr($awaitingType, strlen('awaiting_receipt_amount:'));
            $parts = explode(':', $payload, 2);
            $transactionId = $parts[0] ?? '0';
            $accountId = $parts[1] ?? null;
            $this->processAdminReceiptAmount($chatId, $transactionId, $text, $accountId);
            return;
        }

        switch ($awaitingType) {
            case 'action_1_reply':
                $this->telegramService->sendMessage($chatId, "نام شما با موفقیت ثبت شد");
                $this->clearAwaitingReply($chatId);
                break;
            case 'remark_reply':
                $this->subscriptionProcessCtrl->remarkReply($chatId, $text);
                // $this->clearAwaitingReply($chatId);
                break;
            case 'add_balance_reply':
                $this->accountProcessCtrl->addBalanceReply($chatId, $text);
                // $this->clearAwaitingReply($chatId);
                break;
            // سایر موارد...
        }
    }

    private function cancelAwaitingInput(string $chatId, string $text): void
    {
        $this->clearAwaitingReply($chatId);
        UserState::where('chat_id', $chatId)
            ->whereIn('state', ['add_balance_reply', 'promo_code_pending', 'promo_code_pending_recharge'])
            ->delete();

        $trimmed = mb_strtolower(trim($text));
        if (str_starts_with($trimmed, '/start') || str_starts_with($trimmed, '/restart')) {
            $this->handleStartCommand($text);
            return;
        }

        $message = $this->customTextCtrl->getText('action.process.reply.cancel_done');
        if (is_array($message)) {
            $message = $this->telegramService->formatText($message);
        }
        $this->generalCntrl->return_main_menu_items($chatId, $message);
    }

    // متدهای کمکی برای مدیریت وضعیت انتظار پاسخ
    private function setAwaitingReply(string $chatId, string $type): void
    {
        // می‌توانید از کش یا دیتابیس استفاده کنید
        Cache::put("awaiting_reply_{$chatId}", $type, now()->addMinutes(30));
    }

    private function awaitingReply(string $chatId): bool
    {
        return Cache::has("awaiting_reply_{$chatId}");
    }

    private function getAwaitingReplyType(string $chatId): ?string
    {
        return Cache::get("awaiting_reply_{$chatId}");
    }

    private function clearAwaitingReply(string $chatId): void
    {
        Cache::forget("awaiting_reply_{$chatId}");
    }

    private function getCurrentChatId(): string
    {
        return $this->chatId ?? request()->input('message.chat.id') ?? request()->input('callback_query.from.id');
    }
    private function getCurrentChatFirstName(): string
    {
        return request()->input('message.from.first_name') ?? '';
    }
    private function getCurrentChatLastName(): string
    {
        return request()->input('message.from.last_name') ?? '';
    }
    private function getCurrentChatUserName(): string
    {
        return request()->input('message.from.username') ?? '';
    }
    public function sendMessageToAdmin($chat_id, $image_url, $transaction_id, $messageType)
    {
        try {
            $admins = User::where('role', 'admin')->get();

            if ($admins->isEmpty()) {
                \Log::warning("No admins found to send message.");
                return "";
            }

            $transactionId = (int) $transaction_id;
            $accountId = (string) $chat_id;
            Cache::put("admin_receipt_context_{$transactionId}_{$accountId}", [
                'account_id' => $accountId,
                'transaction_id' => $transactionId,
            ], now()->addDays(1));

            $adminMessages = [];
            foreach ($admins as $admin) {
                $admin_id = $admin->account_id;
                if ($messageType == 'image') {
                    $text = $this->customTextCtrl->getText('action.send_photo.success.admin', [
                        'account_id' => $chat_id,
                    ]);

                    // Include account_id so admin can still charge if transaction row is missing.
                    $buttons = [
                        [
                            'تایید ✅' => "confirmReceipt-{$transactionId}-{$accountId}",
                            'لغو ❌' => "cancelReceipt-{$transactionId}-{$accountId}"
                        ]
                    ];

                    $result = $this->telegramService->sendPhoto($admin_id, $image_url, $text, [
                        'reply_markup' => json_encode([
                            'inline_keyboard' => $this->telegramService->formatInlineKeyboardButtons($buttons)
                        ])
                    ]);

                    if (isset($result['ok']) && $result['ok'] && isset($result['result']['message_id'])) {
                        $adminMessages[] = [
                            'chat_id' => $admin_id,
                            'message_id' => $result['result']['message_id']
                        ];
                    }
                } else {
                    // For other message types, transaction_id might be the text
                    $this->telegramService->sendMessage($admin_id, $transaction_id);
                }
            }

            $cacheKey = $transactionId > 0
                ? "admin_receipt_messages_{$transactionId}"
                : "admin_receipt_messages_account_{$accountId}";
            if (!empty($adminMessages)) {
                Cache::put($cacheKey, $adminMessages, now()->addDays(1));
            }

            return "";
        } catch (\Throwable $th) {
            \Log::error("خطا در پردازش sendMessageToAdmin: " . $th);
            return "";
        }
    }
    private function removeReceiptButtonsFromAllAdmins($transactionId, $accountId = null)
    {
        $keys = ["admin_receipt_messages_{$transactionId}"];
        if ($accountId !== null && $accountId !== '') {
            $keys[] = "admin_receipt_messages_account_{$accountId}";
        }

        foreach ($keys as $key) {
            $messages = Cache::get($key, []);
            foreach ($messages as $msg) {
                try {
                    $this->telegramService->editMessageReplyMarkup($msg['chat_id'], $msg['message_id'], ['inline_keyboard' => []]);
                } catch (\Throwable $th) {
                    \Log::error("Error removing buttons for admin {$msg['chat_id']}: " . $th->getMessage());
                }
            }
        }
    }

    public function handleConfirmReceipt($adminChatId, $transactionId, $callbackQueryId, $messageId = null, $accountId = null)
    {
        $transactionId = (int) $transactionId;
        $accountId = $accountId !== null && $accountId !== ''
            ? (string) $accountId
            : null;

        $processedKey = $this->receiptProcessedCacheKey($transactionId, $accountId);
        if (Cache::has($processedKey)) {
            $this->telegramService->answerCallbackQuery($callbackQueryId, "این رسید قبلاً توسط مدیر دیگری بررسی شده است.", true);
            $this->removeReceiptButtonsFromAllAdmins($transactionId, $accountId);
            return "";
        }

        $transaction = $transactionId > 0 ? Transaction::find($transactionId) : null;
        if ($accountId === null && $transaction) {
            $accountId = (string) $transaction->account_id;
        }

        if ($accountId === null || $accountId === '') {
            $this->telegramService->answerCallbackQuery($callbackQueryId, "شناسه کاربر یافت نشد.", true);
            $this->removeReceiptButtonsFromAllAdmins($transactionId, $accountId);
            return "";
        }

        // Remove buttons for ALL admins
        $this->removeReceiptButtonsFromAllAdmins($transactionId, $accountId);

        // Set state for admin to wait for amount (even if transaction row is missing)
        $this->setAwaitingReply(
            $adminChatId,
            "awaiting_receipt_amount:{$transactionId}:{$accountId}"
        );

        $hint = $transaction
            ? "لطفاً مبلغ شارژ برای کاربر {$accountId} را به تومان وارد کنید:"
            : "تراکنش در سیستم ثبت نشده بود. لطفاً مبلغ شارژ برای کاربر {$accountId} را به تومان وارد کنید تا به حسابش اضافه شود:";

        $this->telegramService->forceReply($adminChatId, $hint);
        return "";
    }

    public function handleCancelReceipt($adminChatId, $transactionId, $callbackQueryId, $messageId = null, $accountId = null)
    {
        $transactionId = (int) $transactionId;
        $accountId = $accountId !== null && $accountId !== ''
            ? (string) $accountId
            : null;

        $processedKey = $this->receiptProcessedCacheKey($transactionId, $accountId);
        if (Cache::has($processedKey)) {
            $this->telegramService->answerCallbackQuery($callbackQueryId, "این رسید قبلاً توسط مدیر دیگری بررسی شده است.", true);
            $this->removeReceiptButtonsFromAllAdmins($transactionId, $accountId);
            return "";
        }

        // Remove buttons for ALL admins
        $this->removeReceiptButtonsFromAllAdmins($transactionId, $accountId);

        Cache::put($processedKey, true, now()->addDays(1));

        $transaction = $transactionId > 0 ? Transaction::find($transactionId) : null;
        if ($transaction) {
            $transaction->confirmed = 0;
            $transaction->recipe_number = 'REJECTED';
            $transaction->save();
            $this->addUserBotLog(
                $transaction->account_id,
                'payment',
                'رسید پرداخت آفلاین توسط مدیر رد شد',
                'reject'
            );
            $this->telegramService->sendMessage($transaction->account_id, "رسید تراکنش شما توسط مدیریت رد شد.");
        } elseif ($accountId) {
            $this->telegramService->sendMessage($accountId, "رسید تراکنش شما توسط مدیریت رد شد.");
        }

        $this->generalCntrl->return_main_menu_items($adminChatId, "رسید با موفقیت رد شد.");
        return "";
    }

    private function processAdminReceiptAmount($adminChatId, $transactionId, $amount, $accountId = null)
    {
        $transactionId = (int) $transactionId;
        $accountId = $accountId !== null && $accountId !== ''
            ? (string) $accountId
            : null;

        $processedKey = $this->receiptProcessedCacheKey($transactionId, $accountId);
        if (Cache::has($processedKey)) {
            $this->telegramService->sendMessage($adminChatId, "این رسید قبلاً توسط مدیر دیگری بررسی شده است.");
            $this->clearAwaitingReply($adminChatId);
            return;
        }

        $normalizedAmount = $this->normalizeAmountInput($amount);
        if ($normalizedAmount === null) {
            $this->telegramService->forceReply($adminChatId, "لطفاً یک مبلغ معتبر (عدد بزرگتر از صفر) وارد کنید:");
            return;
        }

        $transaction = $transactionId > 0 ? Transaction::find($transactionId) : null;
        if ($accountId === null && $transaction) {
            $accountId = (string) $transaction->account_id;
        }

        if ($accountId === null || $accountId === '') {
            $this->telegramService->sendMessage($adminChatId, "شناسه کاربر برای شارژ یافت نشد.");
            $this->clearAwaitingReply($adminChatId);
            return;
        }

        Cache::put($processedKey, true, now()->addDays(1));

        if ($transaction) {
            $transaction->amount = $normalizedAmount;
            $transaction->confirmed = 1;
            $transaction->save();
        } else {
            // Create a confirmed offline transaction so history stays consistent.
            $createdId = $this->transactionCntrl->addUserTranaction(
                $accountId,
                $normalizedAmount,
                'MANUAL_ADMIN_RECEIPT',
                0
            );
            if ($createdId) {
                $created = Transaction::find($createdId);
                if ($created) {
                    $created->confirmed = 1;
                    $created->amount = $normalizedAmount;
                    $created->save();
                    $transaction = $created;
                    $transactionId = (int) $createdId;
                }
            }
        }

        $this->accountProcessCtrl->adminFastCharge($adminChatId, $normalizedAmount, $accountId);
        $formattedAmount = number_format($normalizedAmount, 0, '.', ',');
        $this->addUserBotLog(
            $accountId,
            'payment',
            "رسید پرداخت آفلاین توسط مدیر تایید شد (مبلغ: {$formattedAmount} تومان)",
            'confirm'
        );
        $this->addUserBotLog(
            $accountId,
            'ballance',
            "میزان موجودی کاربر به مقدار {$formattedAmount} تومان افزایش یافت",
            'edit'
        );

        // Add referral amount
        if ($transaction) {
            $referralLogsCntrl = new ReferralLogsController();
            $referralSettingCntrl = new ReferralSettingController();
            $referral_percent = $referralSettingCntrl->get_referral_setting_referral_percent();
            $referralAmount = \App\Models\ReferralSetting::commissionFromAmount(
                (float) $normalizedAmount,
                $referral_percent
            );
            $referralLogsCntrl->add_amount_to_refrerral_user_Log_and_referral_wallet($transaction->id, $referralAmount, false);
        }

        $this->clearAwaitingReply($adminChatId);
        $this->generalCntrl->return_main_menu_items($adminChatId, 'یک گزینه را از منوی اصلی انتخاب کنید.');
    }

    private function receiptProcessedCacheKey(int $transactionId, ?string $accountId): string
    {
        if ($transactionId > 0) {
            return "receipt_processed_{$transactionId}";
        }

        return "receipt_processed_account_" . ($accountId ?? 'unknown');
    }

    private function normalizeAmountInput($amount): ?float
    {
        return $this->telegramService->parseNumericAmount(is_scalar($amount) ? (string) $amount : null);
    }

    private function sendResponseIfNotEmpty(string $chatId, string|array|null $response): void
    {
        if ($response === null || $response === '') {
            return;
        }

        if (is_array($response) && $response === []) {
            return;
        }

        $this->telegramService->sendMessage($chatId, $response);
    }

    private function addNewBotLog($type, $message, $event)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $this->getCurrentChatId(), $this->getCurrentChatUserName(), $event);
        return true;
    }

    private function addUserBotLog(string $accountId, string $type, string $message, string $event): void
    {
        $botUser = BotUser::where('account_id', $accountId)->first();
        $username = $botUser?->username ?? '';
        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $accountId, $username, $event);
    }
    private function is_first_time_bot_start_event()
    {
        // check if the bot is started for the first time
        // check we have a user with admin id or not
        $admin = User::where('role', 'admin')->first();
        if ($admin == null) {
            // send message in telegram to first you have to login in webapp and broken other process
            $this->telegramService->sendMessage($this->getCurrentChatId(), "برای شروع ربات ابتدا می بایست وارد وب اپلیکیشن شوید و تنظیمات ربات را انجام بدهید");
            $authCtrl = new AuthController();
            $authCtrl->createFirstAdminUser();
            return true;
        }
        return false;
    }
}
