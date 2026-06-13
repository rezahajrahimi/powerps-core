# Telegram Bot Improvement Plan

## UX Improvements
- [ ] Add `editMessageText` and `editMessageReplyMarkup` to `TelegramService`.
- [ ] Update `TelegramWebhookController` to use message editing for menu navigation instead of sending new messages.
- [ ] Implement `sendChatAction` (typing indicators) for long-running operations.
- [ ] Use `answerCallbackQuery` for toast notifications (alerts) instead of text messages where appropriate.

## Code Structure & Performance
- [ ] Refactor `handleCallbackQuery` in `TelegramWebhookController` to reduce complexity (Strategy Pattern/Action Classes).
- [ ] Move heavy operations (config generation, panel API calls) to Laravel Jobs/Queues.
