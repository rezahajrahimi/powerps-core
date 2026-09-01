<?php

namespace App\Jobs;

use App\Http\Controllers\AgentProductController;
use App\Http\Controllers\AccountBallanceController;
use App\Http\Controllers\CustomTextController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\HiddifyPannelController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PannelController;
use App\Http\Controllers\PaymentSettingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReferralWalletController;
use App\Http\Controllers\MarzbanPannelController;
use App\Http\Controllers\SanaeiPannelController;
use App\Models\BotUser;
use App\Services\TelegramService;
use App\Services\SubscriptionPurchaseLock;
use App\Services\InventoryPurchaseService;
use App\Services\PromoCodeService;
use App\Services\PurchaseIntentService;
use App\Services\SubscriptionPaymentService;
use App\Services\LoyaltyPointsService;
use App\Services\MobileVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSubscriptionPurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sanaei panel login + add client can exceed 60s on slow/unstable links. */
    public int $timeout = 600;

    public int $tries = 1;

    protected $chatId;
    protected $productCategoryId;
    protected ?string $promoCode;

    /**
     * Create a new job instance.
     */
    public function __construct($chatId, $productCategoryId, ?string $promoCode = null)
    {
        $this->chatId = $chatId;
        $this->productCategoryId = $productCategoryId;
        $this->promoCode = $promoCode;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lock = SubscriptionPurchaseLock::acquire($this->chatId);
        if (! $lock) {
            \Log::warning('Duplicate subscription purchase job skipped', [
                'chat_id' => $this->chatId,
                'product_category_id' => $this->productCategoryId,
            ]);

            return;
        }

        $generalCntrl = new GeneralController();
        $customTextCtrl = new CustomTextController();
        $accBlCtrl = new AccountBallanceController();
        $referralCntrl = new ReferralWalletController();
        $panelCntrl = new PannelController();
        $logCtrl = new LogController();
        $paymnetSettingCntrl = new PaymentSettingController();
        $agentProductCtrl = new AgentProductController();
        $telegramService = new TelegramService();
        $prCntrl = new ProductController();
        $inventoryPurchaseService = new InventoryPurchaseService();
        $paymentService = new SubscriptionPaymentService($accBlCtrl, $paymnetSettingCntrl, $referralCntrl, $logCtrl);
        $loyaltyService = new LoyaltyPointsService();
        $reservedProductId = null;
        $soldInventoryProductId = null;
        $chargeResult = null;
        $purchaseDelivered = false;
        $loyaltyPointsRedeemed = 0;
        $originalProductPrice = 0.0;

        // Fetch user for logging
        $botUser = BotUser::where('account_id', $this->chatId)->first();
        $username = $botUser ? $botUser->username : 'Unknown';
        try {
            $mobileBlock = (new MobileVerificationService())->purchaseBlockResponse($this->chatId);
            if ($mobileBlock['blocked'] ?? false) {
                $telegramService->sendMessage($this->chatId, $mobileBlock['message'] ?? 'تایید موبایل لازم است.');
                (new MobileVerificationService())->promptVerification($this->chatId);

                return;
            }

            $pricing = $agentProductCtrl->resolveProductPricingForAccount($this->chatId, $this->productCategoryId);
            if ($pricing === null) {
                $telegramService->sendMessage($this->chatId, 'این بسته برای شما در دسترس نیست.');
                return;
            }

            $selectedPrCat = $pricing['category'];
            $selectedPrCat->refresh();
            if (! $selectedPrCat) {
                \Log::error("Product Category not found: " . $this->productCategoryId);
                return;
            }

            $limitMessage = $agentProductCtrl->checkAgentPurchaseLimits(
                $this->chatId,
                (float) ($selectedPrCat->volume ?? 0),
                1
            );
            if ($limitMessage !== null) {
                $telegramService->sendMessage($this->chatId, $limitMessage);
                return;
            }

            \Log::info("Selected Product Category: 111111" . $selectedPrCat->category_name);
            $productPrice = $pricing['price'];
            $productPriceInDollar = $pricing['price_in_dollar'];
            $promoDiscountToman = 0.0;
            $appliedPromo = null;

            if ($this->promoCode) {
                $promoService = new PromoCodeService();
                $promoResult = $promoService->validate(
                    $this->promoCode,
                    $this->chatId,
                    (int) $this->productCategoryId,
                    (float) $productPrice,
                    (float) $productPriceInDollar
                );
                if (! ($promoResult['valid'] ?? false)) {
                    $telegramService->sendMessage($this->chatId, $promoResult['message'] ?? 'کد تخفیف نامعتبر است.');
                    return;
                }
                $productPrice = (float) ($promoResult['final_price_toman'] ?? $productPrice);
                $productPriceInDollar = (float) ($promoResult['final_price_dollar'] ?? $productPriceInDollar);
                $promoDiscountToman = (float) ($promoResult['discount_toman'] ?? 0);
                $appliedPromo = $promoResult['promo'] ?? null;
            }

            $originalProductPrice = (float) $productPrice;
            $loyaltyCheckout = $loyaltyService->resolveCheckout(
                $this->chatId,
                (float) $productPrice,
                (float) $productPriceInDollar,
                fn ($accountId, $price, $priceDollar) => $accBlCtrl->checkUserHasBalance($accountId, $price, $priceDollar),
                fn ($accountId, $price) => $referralCntrl->check_user_has_ref_wallet_ballance($accountId, $price),
            );
            $productPrice = $loyaltyCheckout['charge_price_toman'];
            $loyaltyPointsRedeemed = $loyaltyCheckout['points_to_redeem'];
            $hasBallance = $loyaltyCheckout['has_balance'];
            $hasRefballance = $loyaltyCheckout['has_ref_balance'];

            if (! $loyaltyCheckout['can_proceed']) {
                $generalCntrl->send_insufficient_balance_message(
                    $this->chatId,
                    $selectedPrCat->id,
                    (float) $productPrice,
                    (float) $productPriceInDollar,
                    $this->promoCode
                );
                return;
            }

            $pannel = $panelCntrl->getPannelById($selectedPrCat->pannel_id);
            $day = $selectedPrCat->expire_day;
            $volume = $selectedPrCat->volume;
            $resualt = false;

            if ($pannel->isInventoryPanel()) {
                if ($prCntrl->countActiveInventory($selectedPrCat->id) < 1) {
                    $telegramService->sendMessage($this->chatId, 'موجودی این بسته تمام شده است.');
                    return;
                }

                $chargeResult = $paymentService->charge(
                    $this->chatId,
                    (float) $productPrice,
                    (float) $productPriceInDollar,
                    (bool) $hasRefballance,
                    $username
                );
                \Log::info('paymentSuccess: ' . ($chargeResult['success'] ? '1' : '0'));

                if ($loyaltyCheckout['points_only']) {
                    $chargeResult = [
                        'success' => true,
                        'source' => 'loyalty',
                        'amount_toman' => 0.0,
                        'amount_dollar' => 0.0,
                    ];
                } elseif (! $paymentService->wasCharged($chargeResult)) {
                    $generalCntrl->send_insufficient_balance_message(
                        $this->chatId,
                        $selectedPrCat->id,
                        (float) $productPrice,
                        (float) $productPriceInDollar,
                        $this->promoCode
                    );
                    return;
                }

                if ($loyaltyPointsRedeemed > 0) {
                    $loyaltyService->redeemPoints(
                        $this->chatId,
                        $loyaltyPointsRedeemed,
                        'purchase',
                        'product_category',
                        $selectedPrCat->id,
                        'استفاده از امتیاز در خرید اشتراک'
                    );
                }

                $soldInventoryProductId = $inventoryPurchaseService->deliverInventoryProduct($selectedPrCat, $this->chatId);
                $resualt = $soldInventoryProductId !== false ? $soldInventoryProductId : false;

                if ($resualt == false || $resualt == null) {
                    $paymentService->refund($this->chatId, $chargeResult, $username);
                    if ($loyaltyPointsRedeemed > 0) {
                        $loyaltyService->refundRedeemedPoints($this->chatId, $loyaltyPointsRedeemed);
                    }
                    $logCtrl->addNewLog('subscription', 'خرید اشتراک با شکست مواجه شد.', $this->chatId, $username, 'failed');
                    $telegramService->sendMessage($this->chatId, 'موجودی این بسته تمام شده است.');
                    return;
                }
            } else {
                $reservedProductId = $prCntrl->reserveProductId($this->chatId, $selectedPrCat->id);
                if ($reservedProductId === null) {
                    $telegramService->sendMessage($this->chatId, $customTextCtrl->getText('action.process.failed_buy'));
                    return;
                }

                $chargeResult = $paymentService->charge(
                    $this->chatId,
                    (float) $productPrice,
                    (float) $productPriceInDollar,
                    (bool) $hasRefballance,
                    $username
                );
                \Log::info('paymentSuccess: ' . ($chargeResult['success'] ? '1' : '0'));

                if ($loyaltyCheckout['points_only']) {
                    $chargeResult = [
                        'success' => true,
                        'source' => 'loyalty',
                        'amount_toman' => 0.0,
                        'amount_dollar' => 0.0,
                    ];
                } elseif (! $paymentService->wasCharged($chargeResult)) {
                    $prCntrl->deletePendingProduct($reservedProductId);
                    $generalCntrl->send_insufficient_balance_message(
                        $this->chatId,
                        $selectedPrCat->id,
                        (float) $productPrice,
                        (float) $productPriceInDollar,
                        $this->promoCode
                    );
                    return;
                }

                if ($loyaltyPointsRedeemed > 0) {
                    $loyaltyService->redeemPoints(
                        $this->chatId,
                        $loyaltyPointsRedeemed,
                        'purchase',
                        'product_category',
                        $selectedPrCat->id,
                        'استفاده از امتیاز در خرید اشتراک'
                    );
                }

                if ($pannel->type == 'hiddify') {
                    $resualt = $generalCntrl->new_hiddify_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $this->chatId, $reservedProductId);
                } elseif ($pannel->isMarzbanCompatible()) {
                    $resualt = $generalCntrl->new_marzban_config_telegram_text(
                        $selectedPrCat,
                        $pannel,
                        $volume,
                        $day,
                        $this->chatId,
                        $reservedProductId
                    );
                } elseif ($pannel->type == 'sanaei') {
                    \Log::info("sanaei pannel");
                    $resualt = $generalCntrl->new_sanaei_config_telegram_text(
                        $selectedPrCat,
                        $pannel,
                        $volume,
                        $day,
                        $this->chatId,
                        $reservedProductId
                    );
                }

                if ($resualt == false || $resualt == null) {
                    \Log::error('Subscription purchase delivery failed', [
                        'chat_id' => $this->chatId,
                        'product_category_id' => $this->productCategoryId,
                        'panel_type' => $pannel->type ?? null,
                        'panel_id' => $selectedPrCat->pannel_id ?? null,
                        'reserved_product_id' => $reservedProductId,
                    ]);
                    $paymentService->refund($this->chatId, $chargeResult, $username);
                    if ($loyaltyPointsRedeemed > 0) {
                        $loyaltyService->refundRedeemedPoints($this->chatId, $loyaltyPointsRedeemed);
                    }
                    $prCntrl->deletePendingProduct($reservedProductId);
                    $logCtrl->addNewLog('subscription', 'خرید اشتراک با شکست مواجه شد.', $this->chatId, $username, 'failed');
                    $telegramService->sendMessage($this->chatId, $customTextCtrl->getText('action.process.failed_buy'));
                    return;
                }
            }

            \Log::info("resualt response buoght from sanaei: " . $resualt);
            $purchaseDelivered = true;

            $generalCntrl->send_using_subscription_manual_message($this->chatId, null, null, $pannel->isInventoryPanel());
            $logCtrl->addNewLog('subscription', 'خرید اشتراک با موفقیت انجام شد.', $this->chatId, $username, 'success');

            if ($appliedPromo !== null) {
                $soldProductId = is_numeric($resualt) ? (int) $resualt : null;
                (new PromoCodeService())->recordUsage($appliedPromo, $this->chatId, $promoDiscountToman, $soldProductId);
            }

            $loyaltyService->awardPurchasePoints($this->chatId, $originalProductPrice, $selectedPrCat->id);

            (new PurchaseIntentService())->completeForAccount($this->chatId, (int) $this->productCategoryId);

        } catch (\Throwable $th) {
            \Log::error("خطا در خرید بسته (Job): " . $th->getMessage());

            if (! $purchaseDelivered && $chargeResult !== null && $paymentService->wasCharged($chargeResult)) {
                $paymentService->refund($this->chatId, $chargeResult, $username);
            }

            if (! $purchaseDelivered && $loyaltyPointsRedeemed > 0) {
                $loyaltyService->refundRedeemedPoints($this->chatId, $loyaltyPointsRedeemed);
            }

            if (! $purchaseDelivered && $reservedProductId !== null) {
                $prCntrl->deletePendingProduct($reservedProductId);
            }

            if (! $purchaseDelivered && $soldInventoryProductId !== null) {
                $inventoryPurchaseService->rollbackDelivery($soldInventoryProductId);
            }

            $telegramService->sendMessage($this->chatId, $customTextCtrl->getText('action.process.failed_buy'));
        } finally {
            SubscriptionPurchaseLock::clear($this->chatId);
            $lock->release();
        }
    }
}
