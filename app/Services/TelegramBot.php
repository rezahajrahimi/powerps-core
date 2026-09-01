<?php

namespace App\Services;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

/**
 * TelegramBot
 */
class TelegramBot
{
    protected $token;
    protected $api_endpoint;
    protected $headers;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->token = (string) config('services.telegram.bot_token', '');
        $this->api_endpoint = rtrim((string) config('services.telegram.api_endpoint', 'https://api.telegram.org'), '/');
        $this->setHeaders();
    }

    /**
     * setHeaders
     *
     * @return void
     */
    protected function setHeaders()
    {
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * needsHtmlParsing
     *
     * @param  mixed $text
     * @return bool
     */
    protected function needsHtmlParsing($text)
    {
        // کاراکترهای خاص که نیاز به HTML دارند
        $specialCharacters = [
            '♦️', '➖', '$',
            '_', // زیرخط در نام کاربری
            ' ', // فاصله‌های متعدد
            '\n', // خط جدید
        ];

        // اگر متن شامل کاراکترهای خاص باشد
        foreach ($specialCharacters as $char) {
            if (strpos($text, $char) !== false) {
                // متن را آماده‌سازی می‌کنیم
                $text = str_replace(["\n", "\r"], "<br>", $text); // تبدیل خط جدید به <br>
                $text = preg_replace('/\s+/', ' ', $text); // حذف فاصله‌های اضافی
                $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); // تبدیل کاراکترهای خاص
                return $text;
            }
        }

        return false;
    }

    /**
     * sendMessage
     *
     * @param  mixed $text
     * @param  mixed $chat_id
     * @param  mixed $reply_to_message_id
     * @return void
     */
    public function sendMessage($text, $chat_id, $reply_to_message_id, $parse, $key = null)
    {
        $params = [
            'chat_id' => $chat_id,
            'reply_to_message_id' => $reply_to_message_id,
            'text' => $text,
            'allow_sending_without_reply' => true,
        ];

        if ($key !== null) {
            $params['reply_markup'] = $key;
        }

        if ($parse) {
            $params['parse_mode'] = $parse;
        }

        $url = "{$this->api_endpoint}/{$this->token}/sendMessage";

        try {
            // اول بدون HTML امتحان می‌کنیم
            $response = Http::withHeaders($this->headers)->post($url, $params);

            if (!$response->ok()) {
                \Log::info('First attempt failed, trying with HTML mode');
                // اگر خطا داد، با HTML امتحان می‌کنیم
                $params['parse_mode'] = 'HTML';
                $params['text'] = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

                $response = Http::withHeaders($this->headers)->post($url, $params);
            }

            $result = ['success' => $response->ok(), 'body' => $response->json()];
            return $result;

        } catch (\Throwable $th) {
            \Log::error('TelegramBot->sendMessage->error', [
                'error' => $th->getMessage(),
                'text' => $text,
                'parse_mode' => $parse
            ]);
            return ['success' => false, 'error' => $th->getMessage()];
        }
    }
    public function replace_specefic_charecter($text)
    {
        // $specialCharacters = ['*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '!'];
        // foreach ($specialCharacters as $char) {
        //     $text = str_replace($char, '\\' . $char, $text);
        // }
        return $text;
    }
    public function deleteMessage($chat_id, $message_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/deleteMessage";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
            return false;
        }

        return true;
    }
    public function editMessageReplyMarkup($chat_id, $message_id, $command)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array

        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'reply_markup' => ['inline_keyboard' => $command, 'resize_keyboard' => true],
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/editMessageReplyMarkup";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        return $result;
    }
    public function checkMember($channelID, $chat_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array
        $params = [
            'chat_id' => $channelID,
            'user_id' => $chat_id,
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/getChatMember";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            if ($response->ok() != false) {
                $result = ['success' => $response->ok(), 'body' => $response->json()];
                $json = $response->json();

                // $res = $json['status'];
                if ($json['result']['status'] == 'left') {
                    return false;
                }
                // \Log::info('rss', ['json' => $json["result"]["status"]]);

                // \Log::info('yesssssssss', ['result' => $result]);

                return true;
            } else {
                $result = ['False' => $response->ok(), 'body' => $response->json()];
                // \Log::info('noooooooooo', ['result' => $result]);

                return false;
            }
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
            \Log::info("Throwable  $th");

            return false;
        }

        return false;
    }
    public function buttonMessage($text, $opr, $chat_id, $reply_to_message_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array
        $params = [];
        $text = $this->replace_specefic_charecter($text);

        if ($text != null) {
            $params = [
                'chat_id' => $chat_id,
                // 'reply_to_message_id' => $reply_to_message_id,
                'allow_sending_without_reply' => true,
                'text' => $text,
                'reply_markup' => ['keyboard' => $opr, 'resize_keyboard' => true],
            ];
        } else {
            $params = [
                'chat_id' => $chat_id,
                // 'reply_to_message_id' => $reply_to_message_id,
                'allow_sending_without_reply' => true,
                'reply_markup' => ['keyboard' => $opr, 'resize_keyboard' => true],
            ];
        }

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/sendMessage";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        return $result;
    }
    public function inlineKeyboardButton($text, $opr, $chat_id, $reply_to_message_id)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array
        $text = $this->replace_specefic_charecter($text);

        $params = [
            'chat_id' => $chat_id,
            'reply_to_message_id' => $reply_to_message_id,
            'allow_sending_without_reply' => true,
            'text' => $text,
            'reply_markup' => ['inline_keyboard' => $opr, 'resize_keyboard' => true],
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/sendMessage";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        return $result;
    }

    public function imageMessage($image, $chat_id, $caption)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array

        $params = [
            'chat_id' => $chat_id,
            'photo' => $image,
            'caption' => $caption,
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/sendPhoto";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        return $result;
    }
    public function imageMessageByLink($image, $chat_id, $caption)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        $params = [
            'chat_id' => $chat_id,
            'caption' => $caption,
        ];
        // $file = public_path() . '/images/' . 'aa.png';
        $url = "{$this->api_endpoint}/{$this->token}/sendPhoto";

        // Send the request
        try {
            $response = Http::attach('photo', file_get_contents($image), 'aa.png')->post($url, [
                'chat_id' => $chat_id,
                'caption' => $caption,
            ]);
            // log resualt array
            // \Log::info('TelegramBot->imageMessageByLink->result', ['result' => $response->json()]);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
            \Log::info("Throwable imageMessageByLink: $th");
        }

        return $result;
    }
    public function sendMessageWithFile($file, $chat_id, $caption)
    {
        try {
            $result = ['success' => false, 'body' => []];
            $url = "{$this->api_endpoint}/{$this->token}/sendDocument";
            $params = [
                'chat_id' => $chat_id,
                'document' => $file,
                'caption' => $caption,
            ];
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
            \Log::info('TelegramBot->sendMessageWithFile->result', ['result' => $result]);
            return $result;
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
            \Log::info("Throwable sendMessageWithFile: $th");
            return $result;
        }


    }
    public function commandMessage($command, $chat_id, $text)
    {
        // Default result array
        $result = ['success' => false, 'body' => []];

        // Create params array
        $text = $this->replace_specefic_charecter($text);

        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'reply_markup' => ['inline_keyboard' => $command, 'resize_keyboard' => true],
        ];

        // Create url -> https://api.telegram.org/bot{token}/sendMessage
        $url = "{$this->api_endpoint}/{$this->token}/sendMessage";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->post($url, $params);
            $result = ['success' => $response->ok(), 'body' => $response->json()];
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        return $result;
    }

    /**
     * getImageUrl
     *
     * @param  mixed $photo
     * @return void
     */
    public function getImageUrl(array $photo)
    {
        $image_url = '';

        $file_id = $photo[count($photo) - 1]['file_id'];

        // set url -> https://api.telegram.org/bot<Your-Bot-token>/getFile?file_id=<Your-file-id>
        $url = "{$this->api_endpoint}/{$this->token}/getFile?file_id={$file_id}";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->get($url);
            $result = ['success' => $response->ok(), 'body' => $response->json()];

            $file_path = $result['body']['result']['file_path'];

            // https://api.telegram.org/file/bot<Your-Bot-token>/<Your-file-path>
            $image_url = "{$this->api_endpoint}/file/{$this->token}/{$file_path}";
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        return $image_url;
    }
    public function getImageUrlByFileID($file_id)
    {
        $image_url = '';

        // $file_id = $photo[count($photo) - 1]['file_id'];

        // set url -> https://api.telegram.org/bot<Your-Bot-token>/getFile?file_id=<Your-file-id>
        $url = "{$this->api_endpoint}/{$this->token}/getFile?file_id={$file_id}";

        // Send the request
        try {
            $response = Http::withHeaders($this->headers)->get($url);
            $result = ['success' => $response->ok(), 'body' => $response->json()];

            $file_path = $result['body']['result']['file_path'];

            // https://api.telegram.org/file/bot<Your-Bot-token>/<Your-file-path>
            $image_url = "{$this->api_endpoint}/file/{$this->token}/{$file_path}";
        } catch (\Throwable $th) {
            $result['error'] = $th->getMessage();
        }

        // \Log::info('TelegramBot->getImageUrl->result', ['result' => $result]);
        // \Log::info("image_url:  $image_url");

        return $image_url;
    }
    public function getImageId(array $photo)
    {
        $image_url = '';

        $file_id = $photo[count($photo) - 1]['file_id'];
        // \Log::info('TelegramBot->getImageUrl->result', ['imaaaaaaaaaaaaage' => $photo]);
        return $file_id;
    }
}
