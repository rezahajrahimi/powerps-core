<?php

namespace App\Services;

use App\Http\Controllers\PannelController;
use App\Http\Controllers\ProductController;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\TelegramService;

class InventoryPurchaseService
{
    public function __construct(
        private readonly ProductController $productController = new ProductController(),
        private readonly PannelController $panelController = new PannelController(),
        private readonly TelegramService $telegramService = new TelegramService(),
    ) {
    }

    public function deliverInventoryProduct(ProductCategory $category, int|string $chatId): int|false
    {
        $product = $this->productController->getProductConfigAndChangeStatus($category->id, $chatId);
        if ($product === null) {
            return false;
        }

        $this->sendDeliveryMessages($category, $product, $chatId);

        return (int) $product->id;
    }

    public function rollbackDelivery(int $productId): bool
    {
        return $this->productController->releaseInventoryProduct($productId);
    }

    private function sendDeliveryMessages(ProductCategory $category, Product $product, int|string $chatId): void
    {
        $text = "خرید شما با موفقیت انجام شد\r\n";

        if (! empty($product->panel_link) && $category->show_pannel_link) {
            $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده: {$product->panel_link}\r\n";
        }

        if (! empty($product->subscription_link) && $category->show_subscription_link) {
            $subscriptionLink = $product->subscription_link;
            $text .= "لینک سابسکریپشن: {$subscriptionLink}\r\n";
            $image = $this->panelController->generateQrMOC($subscriptionLink);
            $text .= "همچنین می‌توانید QRCode ارسال‌شده را اسکن نمایید.\r\n";
            $this->telegramService->sendPhotoFile($chatId, $image, $text);
            $text = '';
        }

        if ($category->shouldSendConfigToUser() && ! empty($product->configs)) {
            $configLinks = ProductCategory::extractConfigLinks($product->configs);
            if ($configLinks !== []) {
                foreach ($configLinks as $link) {
                    $image = $this->panelController->generateQrMOC($link);
                    $this->telegramService->sendPhotoFile($chatId, $image, $link);
                }
                $text = '';
            } elseif (is_string($product->configs)) {
                $text .= "کانفیگ:\r\n{$product->configs}\r\n";
            }
        }

        if ($text !== '') {
            $this->telegramService->sendMessage($chatId, $text);
        }
    }
}
