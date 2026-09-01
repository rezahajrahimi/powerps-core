<?php
namespace App\Services;

use App\Services\TelegramMessageFormatter;

class TelegramService
{
    private string $baseUrl;
    private string $botToken;
    private BotKeyboardConfigService $keyboardConfig;

    public function __construct(?BotKeyboardConfigService $keyboardConfig = null)
    {
        $this->baseUrl = 'https://api.telegram.org/';
        $this->botToken = $this->resolveBotToken();
        $this->keyboardConfig = $keyboardConfig ?? new BotKeyboardConfigService();
    }

    private function resolveBotToken(): string
    {
        try {
            $settingToken = trim((string) (\App\Models\Setting::query()->value('bot_token') ?? ''));
            if ($settingToken !== '') {
                return $this->normalizeBotToken($settingToken);
            }
        } catch (\Throwable) {
            // Settings table may be unavailable during early bootstrap/tests.
        }

        return $this->normalizeBotToken((string) config('services.telegram.bot_token', ''));
    }

    private function normalizeBotToken(string $token): string
    {
        $token = preg_replace('/bot:?bot/i', 'bot', trim($token));
        if ($token !== '' && ! str_starts_with($token, 'bot')) {
            $token = 'bot' . $token;
        }

        return $token;
    }

    public function isUnreachableChatError(array $response): bool
    {
        if (($response['ok'] ?? null) !== false) {
            return false;
        }

        $description = strtolower((string) ($response['description'] ?? ''));
        $errorCode = (int) ($response['error_code'] ?? 0);

        if ($errorCode === 403) {
            return true;
        }

        return $errorCode === 400 && (
            str_contains($description, 'chat not found')
            || str_contains($description, 'user is deactivated')
            || str_contains($description, 'peer_id_invalid')
        );
    }

    public function sendMessage(string $chatId, string|array $text, array $options = []): array
    {
        if (is_array($text)) {
            // use format text service
            $text = $this->formatText($text);
        }
        return $this->makeRequest('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options));
    }

    public function sendPlainMessage(string $chatId, string|array $text, array $options = []): array
    {
        if (is_array($text)) {
            $text = $this->formatText($text);
        }
        return $this->makeRequest('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $options));
    }

    public function sendMarkdownMessage(string $chatId, string|array $text, array $options = []): array
    {
        if (is_array($text)) {
            // use format text service
            $text = $this->formatText($text);
        }
        return $this->sendMessage($chatId, $text, array_merge([
            'parse_mode' => 'MarkdownV2',
        ], $options));
    }

    public function sendHTMLMessage(string $chatId, string|array $text, array $options = []): array
    {
        if (is_array($text)) {
            // use format text service
            $text = $this->formatText($text);
        }
        return $this->sendMessage($chatId, $text, array_merge([
            'parse_mode' => 'HTML',
        ], $options));
    }

    public function sendPhotoUrl(string $chatId, string $photo, ?string $caption = null, array $options = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
            'parse_mode' => 'HTML',
        ], $options);

        if ($caption !== null) {
            $params['caption'] = $caption;
        }

        return $this->makeRequest('sendPhoto', $params);
    }

    // check that the chatId is channel member
    public function checkChatIdIsChannelMember(string $chatId, $channelId): bool
    {
        try {
            $url = $this->baseUrl . $this->botToken . '/getChatMember';
            $params = [
                'chat_id' => "@" . $channelId, // اضافه کردن @ برای اطمینان
                'user_id' => $chatId,
            ];

            $response = $this->makeRequest('getChatMember', $params);

            // بررسی وضعیت عضویت کاربر
            if (isset($response['ok']) && $response['ok'] === true && isset($response['result']['status'])) {
                $status = $response['result']['status'];
                // اگر کاربر left یا kicked باشد یعنی عضو کانال نیست
                $res = in_array($status, ['left', 'kicked']);
                return $res == 1 || $res == true ? false : true;
            }

            return false; // در صورت هر خطایی فرض میکنیم کاربر عضو نیست

        } catch (\Exception $e) {
            \Log::error("خطا در بررسی عضویت کاربر در کانال: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ایجاد متن با فرمت HTML برای تلگرام
     *
     * @param string $text متن اصلی
     * @return string متن فرمت شده
     */
    public function formatHTML(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * ایجاد متن با فرمت Markdown برای تلگرام
     *
     * @param string $text متن اصلی
     * @return string متن فرمت شده
     */
    public function formatMarkdown(string $text): string
    {
        $escapeChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        return str_replace($escapeChars, array_map(fn($char) => "\\$char", $escapeChars), $text);
    }

    /**
     * ایجاد لینک با فرمت HTML
     */
    public function createHTMLLink(string $text, string $url): string
    {
        return sprintf('<a href="%s">%s</a>', $this->formatHTML($url), $this->formatHTML($text));
    }

    /**
     * ایجاد لینک با فرمت Markdown
     */
    public function createMarkdownLink(string $text, string $url): string
    {
        return sprintf('[%s](%s)', $this->formatMarkdown($text), $this->formatMarkdown($url));
    }

    /**
     * ایجاد متن پررنگ با HTML
     */
    public function boldHTML(string $text): string
    {
        return sprintf('<b>%s</b>', $this->formatHTML($text));
    }

    /**
     * ایجاد متن پررنگ با Markdown
     */
    public function boldMarkdown(string $text): string
    {
        return sprintf('*%s*', $this->formatMarkdown($text));
    }

    /**
     * ایجاد متن کج با HTML
     */
    public function italicHTML(string $text): string
    {
        return sprintf('<i>%s</i>', $this->formatHTML($text));
    }

    /**
     * ایجاد متن کج با Markdown
     */
    public function italicMarkdown(string $text): string
    {
        return sprintf('_%s_', $this->formatMarkdown($text));
    }

    /**
     * ایجاد متن کد با HTML
     */
    public function codeHTML(string $text): string
    {
        return sprintf('<code>%s</code>', $this->formatHTML($text));
    }

    /**
     * ایجاد متن کد با Markdown
     */
    public function codeMarkdown(string $text): string
    {
        return sprintf('`%s`', $this->formatMarkdown($text));
    }

    /**
     * ایجاد بلوک کد با HTML
     */
    public function preHTML(string $text, string $language = ''): string
    {
        if ($language) {
            return sprintf(
                '<pre><code class="language-%s">%s</code></pre>',
                $this->formatHTML($language),
                $this->formatHTML($text)
            );
        }
        return sprintf('<pre>%s</pre>', $this->formatHTML($text));
    }

    /**
     * ایجاد بلوک کد با Markdown
     */
    public function preMarkdown(string $text, string $language = ''): string
    {
        return sprintf(
            '```%s\n%s\n```',
            $language,
            $this->formatMarkdown($text)
        );
    }

    public function sendMessageWithKeyboard(string $chatId, string|array $text, array $buttons, bool $resize = true): array
    {
        if (is_array($text)) {
            // use format text service
            $text = $this->formatText($text);
        }
        $replyMarkup = array_merge(
            $this->keyboardConfig->replyKeyboardOptions(),
            [
                'keyboard' => $this->formatKeyboardButtons($buttons),
                'resize_keyboard' => $resize,
            ],
        );
        $response = $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode($replyMarkup),
        ]);
        return $response;
    }

    public function sendMessageWithInlineKeyboard(
        string $chatId,
        string|array $text,
        array $buttons,
        ?int $columnsPerRow = null,
        bool $regroupSingleRows = true,
    ): array {
        try {
            if (is_array($text)) {
                // use format text service
                $text = $this->formatText($text);
            }
            if ($buttons === []) {
                return $this->sendMessage($chatId, $text);
            }
            $response = $this->sendMessage($chatId, $text, [
                'reply_markup' => json_encode([
                    'inline_keyboard' => $this->formatInlineKeyboardButtons($buttons, $columnsPerRow, $regroupSingleRows),
                ]),
            ]);
            // log response as a array
            return $response;
        } catch (\Throwable $e) {
            \Log::error('sendMessageWithInlineKeyboard ' . $e->getMessage());

            return [
                'ok' => false,
                'description' => $e->getMessage(),
            ];
        }
    }

    public function removeKeyboard(string $chatId, string $text): array
    {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'remove_keyboard' => true,
            ]),
        ]);
    }

    public function isCancelOrExitText(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        $normalized = mb_strtolower($trimmed);

        return in_array($normalized, ['لغو', 'cancel', '/cancel'], true)
            || str_starts_with($normalized, '/start')
            || str_starts_with($normalized, '/restart');
    }

    /**
     * Parse a user-entered amount, including Persian/Arabic digits.
     */
    public function parseNumericAmount(?string $text): ?float
    {
        if ($text === null) {
            return null;
        }

        $normalized = trim($text);
        if ($normalized === '') {
            return null;
        }

        $normalized = strtr($normalized, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', '٬' => ',',
        ]);

        $normalized = str_ireplace(['تومان', 'toman', 'usd', 'dollar', 'دلار', '$'], '', $normalized);
        $normalized = str_replace([' ', "\u{00A0}"], '', trim($normalized));
        $normalized = str_replace('،', ',', $normalized);

        if (str_contains($normalized, '.') && str_contains($normalized, ',')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (substr_count($normalized, ',') === 1) {
            [$left, $right] = explode(',', $normalized, 2);
            $normalized = strlen($right) <= 2
                ? $left . '.' . $right
                : str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        $value = (float) $normalized;
        if ($value <= 0 || ! is_finite($value)) {
            return null;
        }

        return $value;
    }

    public static function isInlineUrlButtonValid(?string $url): bool
    {
        $url = trim((string) $url);

        return $url !== '' && (bool) preg_match('#^(https?://|tg://)#i', $url);
    }

    public function forceReply(string $chatId, string|array $text): array
    {
        if (is_array($text)) {
            // use format text service
            $text = $this->formatText($text);
        }

        $placeholder = is_string($text) ? mb_substr(trim(strip_tags($text)), 0, 64) : 'لغو';

        return $this->sendMessage($chatId, $text, [
            'reply_markup' => json_encode([
                'keyboard' => $this->formatKeyboardButtons([['لغو']]),
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
                'input_field_placeholder' => $placeholder !== '' ? $placeholder : 'لغو',
            ]),
        ]);
    }

    public function sendPhotoFile(string $chatId, string $photoPath, string|array $caption = '', array $options = []): array
    {
        // ایجاد CURLFile از فایل تصویر
        $photo = new \CURLFile($photoPath);
        if (is_array($caption)) {
            // use format text service
            $caption = $this->formatText($caption);
        }
        $result = $this->makeRequestFile('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options));

        return $result;
    }

    public function sendPhoto(string $chatId, string $photo, string|array $caption = '', array $options = []): array
    {
        if (is_array($caption)) {
            // use format text service
            $caption = $this->formatText($caption);
        }
        return $this->makeRequest('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options));
    }

    public function sendDocument(string $chatId, string $document, string|array $caption = '', array $options = []): array
    {
        if (is_array($caption)) {
            // use format text service
            $caption = $this->formatText($caption);
        }
        try {
            $result = $this->makeRequest('sendDocument', array_merge([
                'chat_id' => $chatId,
                'document' => $document,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ], $options));
            // \Log::info("sendDocument result=> $result");
            return $result;
        } catch (\Throwable $th) {
            \Log::error($th->getMessage());
            return [];
        }
    }
    public function sendDocumentFile(string $chatId, string $document, string|array $caption = '', array $options = []): array
    {
        if (is_array($caption)) {
            // use format text service
            $caption = $this->formatText($caption);
        }
        try {
            $document = new \CURLFile($document);
            $result = $this->makeRequestFile('sendDocument', array_merge([
                'chat_id' => $chatId,
                'document' => $document,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ], $options));
            // \Log::info("sendDocument result=> $result");
            return $result;
        } catch (\Throwable $th) {
            \Log::error($th->getMessage());
            return [];
        }
    }

    public function sendLocation(string $chatId, float $latitude, float $longitude): array
    {
        return $this->makeRequest('sendLocation', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    public function getFile(string $fileId): array
    {
        return $this->makeRequest('getFile', [
            'file_id' => $fileId,
        ]);
    }

    public function downloadFile(string $filePath): string
    {
        $url = $this->baseUrl . 'file/' . $this->botToken . '/' . ltrim($filePath, '/');
        $context = stream_context_create([
            'http' => [
                'timeout' => 25,
            ],
        ]);
        $contents = @file_get_contents($url, false, $context);
        if ($contents === false) {
            throw new \Exception('خطا در دانلود فایل از تلگرام');
        }

        return $contents;
    }
    public function downloadImageFile($file_path)
    {
        $url = $this->baseUrl . 'file/' . $this->botToken . '/' . ltrim((string) $file_path, '/');
        $context = stream_context_create([
            'http' => [
                'timeout' => 25,
            ],
        ]);
        $contents = @file_get_contents($url, false, $context);
        if ($contents === false) {
            throw new \Exception('خطا در دانلود تصویر از تلگرام');
        }

        return $contents;
    }
    public function sendVoice(string $chatId, string $voice, string|array $caption = '', array $options = []): array
    {
        if (is_array($caption)) {
            // use format text service
            $caption = $this->formatText($caption);
        }
        return $this->makeRequest('sendVoice', array_merge([
            'chat_id' => $chatId,
            'voice' => $voice,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options));
    }

    public function sendVideo(string $chatId, string $video, string|array $caption = '', array $options = []): array
    {
        if (is_array($caption)) {
            // use format text service
            $caption = $this->formatText($caption);
        }
        return $this->makeRequest('sendVideo', array_merge([
            'chat_id' => $chatId,
            'video' => $video,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options));
    }

    public function sendContact(string $chatId, string $phoneNumber, string $firstName, string $lastName = ''): array
    {
        return $this->makeRequest('sendContact', [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    public function sendChatAction(string $chatId, string $action): array
    {
        return $this->makeRequest('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action, // typing, upload_photo, record_video, upload_video, record_voice, upload_voice, upload_document
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, string|array $text = '', bool $showAlert = false): array
    {
        if (is_array($text)) {
            // use format text service
            $text = $this->formatText($text);
        }
        return $this->makeRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    private function formatKeyboardButtons(array $buttons): array
    {
        return $this->keyboardConfig->formatReplyKeyboard($buttons);
    }
    public function formatText(array $text): string
    {
        $formatter = new TelegramMessageFormatter($this);
        $text = $formatter->addFormattedText('', $text)->getMessage();
        return $text;
    }

    public function formatInlineKeyboardButtons(
        array $buttons,
        ?int $columnsPerRow = null,
        bool $regroupSingleRows = true,
    ): array {
        return $this->keyboardConfig->formatInlineKeyboard($buttons, $columnsPerRow, $regroupSingleRows);
    }

    private function makeRequest(string $method, array $params = []): array
    {
        $url = $this->baseUrl . $this->botToken . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            \Log::error("Telegram API Curl Error ($method): $error");
            throw new \Exception('خطا در ارتباط با تلگرام: ' . $error);
        }

        $decoded = json_decode($response, true) ?? [];
        if (isset($decoded['ok']) && $decoded['ok'] === true) {
            \Log::info("Telegram API Response ($method): ok");
        } else {
            $this->logTelegramApiFailure($method, $decoded, $params);
        }

        return $decoded;
    }

    private function logTelegramApiFailure(string $method, array $decoded, array $params): void
    {
        $logParams = $params;
        foreach ($logParams as $key => $value) {
            if ($value instanceof \CURLFile) {
                $logParams[$key] = '[CURLFile: ' . $value->getFilename() . ']';
            }
        }

        $payload = json_encode($decoded, JSON_UNESCAPED_UNICODE)
            . ' | Params: '
            . json_encode($logParams, JSON_UNESCAPED_UNICODE);

        if ($this->isUnreachableChatError($decoded)) {
            \Log::warning("Telegram API delivery skipped ($method): {$payload}");

            return;
        }

        \Log::error("Telegram API Error ($method): {$payload}");
    }

    private function makeRequestFile(string $method, array $params = []): array
    {
        $url = $this->baseUrl . $this->botToken . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        // برای ارسال فایل نیازی به تنظیم Content-Type نیست
        // CURL به صورت خودکار Content-Type: multipart/form-data را تنظیم می‌کند
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            \Log::error("Telegram API Curl Error ($method): $error");
            throw new \Exception('خطا در ارتباط با تلگرام: ' . $error);
        }

        $decoded = json_decode($response, true) ?? [];
        if (isset($decoded['ok']) && $decoded['ok'] === true) {
            \Log::info("Telegram API Response ($method): ok");
        } else {
            $this->logTelegramApiFailure($method, $decoded, $params);
        }

        return $decoded;
    }

    /**
     * Send message with multiple buttons
     *
     * @param string $chatId Telegram chat ID
     * @param string $text Message text
     * @param array $buttonsList Array of buttons [ ['text' => 'Button Text', 'url' => 'Button URL'] ]
     * @return array
     */
    public function sendMessageWithLinkButtons(string $chatId, string|array $text, array $buttonsList): array
    {
        if (is_array($text)) {
            // use format text service
            $text = $this->formatText($text);
        }
        $buttons = [];
        foreach ($buttonsList as $button) {
            if (! is_array($button)) {
                continue;
            }
            $url = trim((string) ($button['url'] ?? ''));
            $label = trim((string) ($button['text'] ?? ''));
            if ($label === '' || ! self::isInlineUrlButtonValid($url)) {
                continue;
            }
            $normalized = [
                'text' => $label,
                'url' => $url,
            ];
            foreach (['style', 'icon_custom_emoji_id'] as $field) {
                if (! empty($button[$field])) {
                    $normalized[$field] = $button[$field];
                }
            }
            $buttons[] = [$normalized];
        }
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        if ($buttons !== []) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $this->keyboardConfig->formatInlineKeyboard($buttons, null, false),
            ]);
        }

        return $this->makeRequest('sendMessage', $payload);
    }

    public function editMessageText(string $chatId, int $messageId, string $text, array $options = []): array
    {
        return $this->makeRequest('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options));
    }

    public function editMessageReplyMarkup(string $chatId, int $messageId, array $replyMarkup = []): array
    {
        return $this->makeRequest('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($replyMarkup),
        ]);
    }

    public function editMessageWithInlineKeyboard(
        string $chatId,
        int $messageId,
        string|array $text,
        array $buttons,
        ?int $columnsPerRow = null,
        bool $regroupSingleRows = true,
    ): array {
        if (is_array($text)) {
            $text = $this->formatText($text);
        }
        return $this->editMessageText($chatId, $messageId, $text, [
            'reply_markup' => json_encode([
                'inline_keyboard' => $this->formatInlineKeyboardButtons($buttons, $columnsPerRow, $regroupSingleRows),
            ]),
        ]);
    }
}
