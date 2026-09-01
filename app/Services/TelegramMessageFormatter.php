<?php

namespace App\Services;

/**
 * کلاس کمکی برای ساخت پیام‌های فرمت‌شده در تلگرام
 * این کلاس امکان ساخت پیام‌های پیچیده با فرمت HTML یا Markdown را فراهم می‌کند
 */
class TelegramMessageFormatter
{
    private string $message = '';
    private string $format;
    private TelegramService $telegram;

    public function __construct(TelegramService $telegram, string $format = 'HTML')
    {
        $this->telegram = $telegram;
        $this->format = $format;
    }

    /**
     * افزودن متن ساده به پیام
     *
     * @param string $text متن مورد نظر
     * @return self
     */
    public function addText(string $text): self
    {
        $this->message .= $this->format === 'HTML'
            ? $this->telegram->formatHTML($text)
            : $this->telegram->formatMarkdown($text);
        return $this;
    }

    /**
     * افزودن متن پررنگ به پیام
     *
     * @param string $text متن مورد نظر
     * @return self
     */
    public function addBold(string $text): self
    {
        $this->message .= $this->format === 'HTML'
            ? $this->telegram->boldHTML($text)
            : $this->telegram->boldMarkdown($text);
        return $this;
    }

    /**
     * افزودن متن کج (ایتالیک) به پیام
     *
     * @param string $text متن مورد نظر
     * @return self
     */
    public function addItalic(string $text): self
    {
        $this->message .= $this->format === 'HTML'
            ? $this->telegram->italicHTML($text)
            : $this->telegram->italicMarkdown($text);
        return $this;
    }

    /**
     * افزودن لینک به پیام
     *
     * @param string $text متن لینک
     * @param string $url آدرس لینک
     * @return self
     */
    public function addLink(string $text, string $url): self
    {
        $this->message .= $this->format === 'HTML'
            ? $this->telegram->createHTMLLink($text, $url)
            : $this->telegram->createMarkdownLink($text, $url);
        return $this;
    }

    /**
     * افزودن متن با فرمت کد به پیام
     *
     * @param string $text متن کد
     * @return self
     */
    public function addCode(string $text): self
    {
        $this->message .= $this->format === 'HTML'
            ? $this->telegram->codeHTML($text)
            : $this->telegram->codeMarkdown($text);
        return $this;
    }

    /**
     * افزودن بلوک کد به پیام
     *
     * @param string $text متن کد
     * @param string $language زبان برنامه‌نویسی (اختیاری)
     * @return self
     */
    public function addPre(string $text, string $language = ''): self
    {
        $this->message .= $this->format === 'HTML'
            ? $this->telegram->preHTML($text, $language)
            : $this->telegram->preMarkdown($text, $language);
        return $this;
    }

    /**
     * افزودن خط جدید به پیام
     *
     * @return self
     */
    public function addNewLine(): self
    {
        $this->message .= "\n";
        return $this;
    }

    /**
     * افزودن متن از CustomText با فرمت‌های مختلف
     *
     * @param string $text متن اصلی
     * @param array $formats آرایه‌ای از فرمت‌ها و متن‌ها
     * @return self
     */
    public function addFormattedText(string $text, array $formats): self
    {
        foreach ($formats as $format) {
            switch ($format['type']) {
                case 'bold':
                    $this->addBold($format['text']);
                    break;
                case 'italic':
                    $this->addItalic($format['text']);
                    break;
                case 'text':
                    $this->addText($format['text']);
                    break;
                case 'code':
                    $this->addCode($format['text']);
                    break;
                case 'link':
                    $this->addLink($format['text'], $format['url']);
                    break;
                case 'newline':
                    $this->addNewLine();
                    break;
            }
        }
        return $this;
    }

    /**
     * ارسال پیام فرمت‌شده به تلگرام
     *
     * @param string $chatId شناسه چت
     * @param array $options تنظیمات اضافی
     * @return array پاسخ تلگرام
     */
    public function send(string $chatId, array $options = []): array
    {
        if ($this->format === 'HTML') {
            return $this->telegram->sendHTMLMessage($chatId, $this->message, $options);
        }
        return $this->telegram->sendMarkdownMessage($chatId, $this->message, $options);
    }

    /**
     * دریافت متن کامل پیام فرمت‌شده
     *
     * @return string متن پیام
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
