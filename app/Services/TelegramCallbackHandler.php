<?php

namespace App\Services;

use App\Http\Controllers\AccountProcessController;
use App\Http\Controllers\CustomTextController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\SubscriptionProcessController;
use App\Http\Controllers\TelegramWebhookController;

class TelegramCallbackHandler
{
    protected $subscriptionProcessCtrl;
    protected $accountProcessCtrl;
    protected $generalCntrl;
    protected $customTextCtrl;
    protected $webhookCtrl;

    public function __construct(
        SubscriptionProcessController $subscriptionProcessCtrl,
        AccountProcessController $accountProcessCtrl,
        GeneralController $generalCntrl,
        CustomTextController $customTextCtrl
    ) {
        $this->subscriptionProcessCtrl = $subscriptionProcessCtrl;
        $this->accountProcessCtrl = $accountProcessCtrl;
        $this->generalCntrl = $generalCntrl;
        $this->customTextCtrl = $customTextCtrl;
    }

    public function setWebhookController(TelegramWebhookController $webhookCtrl)
    {
        $this->webhookCtrl = $webhookCtrl;
    }

    public function handle(string $chatId, string $action, array $params, ?int $messageId, string $callbackQueryId)
    {
        return match ($action) {
            'buySubscription' => $this->subscriptionProcessCtrl->buySubscriptionAction($chatId, $params[0] ?? null),
            'openBuySubscription' => $this->subscriptionProcessCtrl->buySubscriptionMenu($chatId),
            'confirmBuy' => $this->subscriptionProcessCtrl->confirmPurchase($chatId, $params[0] ?? null, null),
            'confirmBuyPromo' => $this->subscriptionProcessCtrl->confirmPurchase(
                $chatId,
                $params[0] ?? null,
                $params[1] ?? null
            ),
            'applyPromo' => $this->subscriptionProcessCtrl->promptPromoCode($chatId, $params[0] ?? null),
            'buySubscriptionByLocation' => $this->subscriptionProcessCtrl->buySubscriptionByLocationAction($chatId, $params[0] ?? null),
            'offlineGateway' => $this->subscriptionProcessCtrl->handle_offline_add_balance($chatId, $params[0] ?? null),
            'buyHistory' => $this->subscriptionProcessCtrl->subBuyHistory($chatId, $params[0] ?? null),
            'buyHistoryNext' => $this->subscriptionProcessCtrl->buyHistory($chatId, $params[0] ?? null),
            'recharge' => $this->subscriptionProcessCtrl->recharge($chatId, $params[0] ?? null),
            'confirmRecharge' => $this->subscriptionProcessCtrl->confirmRecharge($chatId, $params[0] ?? null, null),
            'confirmRechargePromo' => $this->subscriptionProcessCtrl->confirmRecharge(
                $chatId,
                $params[0] ?? null,
                $params[1] ?? null
            ),
            'applyPromoRecharge' => $this->subscriptionProcessCtrl->promptPromoCodeForRecharge($chatId, $params[0] ?? null),
            'remark' => $this->subscriptionProcessCtrl->remark($chatId, $params[0] ?? null),
            'deleteHistory' => $this->subscriptionProcessCtrl->deleteHistory($chatId, $params[0] ?? null),
            'confirmDeleteHistory' => $this->subscriptionProcessCtrl->confirmDeleteHistory($chatId, $params[0] ?? null),

            'accountTransactions' => $this->accountProcessCtrl->accountTransactions($chatId),
            'accountLoyaltyHistory' => $this->accountProcessCtrl->accountLoyaltyHistory($chatId),
            'accountLoyaltyHistoryPage' => $this->accountProcessCtrl->accountLoyaltyHistory(
                $chatId,
                (int) ($params[0] ?? 1),
                $messageId
            ),
            'accountSubAccounts' => $this->accountProcessCtrl->accountSubAccounts($chatId),
            'accountAddBalance' => $this->accountProcessCtrl->accountAddBalance($chatId),
            'accountSubAccountsZarinpal' => $this->accountProcessCtrl->handleActionAddBalanceZarinpal($chatId),
            'accountSubAccountsNowpayment' => $this->accountProcessCtrl->handleActionAddBalanceNowpayments($chatId),
            'accountSubAccountsCryptomus' => $this->accountProcessCtrl->handleActionAddBalanceCryptomus($chatId),
            'accountSubAccountsSwappay' => $this->accountProcessCtrl->handleActionAddBalanceSwappay($chatId),
            'addBalanceReply' => $this->accountProcessCtrl->addBalanceReply($chatId, $params[0] ?? null),
            'charge' => $this->accountProcessCtrl->adminFastCharge($chatId, $params[0] ?? null, $params[1] ?? null),
            'shetabVerify' => $this->accountProcessCtrl->handleActionAddBalanceShetabVerify($chatId, $params[0] ?? null),
            'shetabVerifyAuto' => $this->accountProcessCtrl->processShetabVerification(
                $chatId,
                $params[0] ?? null,
                $params[1] ?? null
            ),

            'toturial' => ($params[0] ?? null) == 'appDownload'
            ? $this->generalCntrl->appDownload($chatId, $messageId)
            : $this->generalCntrl->getFaqs($chatId, $messageId),
            'help' => $this->webhookCtrl ? $this->webhookCtrl->handleHelpCommand() : '',
            'faq' => $this->generalCntrl->subFaq($chatId, $params[0] ?? null),
            'appDownload' => $this->generalCntrl->appDownload($chatId, $messageId),
            'subAppDownloadOs' => $this->generalCntrl->subAppDownloadOs($chatId, $params[0] ?? null, $messageId),
            'subAppDownloadApp' => $this->generalCntrl->subAppDownloadApp($chatId, $params[0] ?? null, $messageId),
            'support' => $this->generalCntrl->subSupport($chatId, $params[0] ?? null),
            'giftCard' => $this->generalCntrl->subGiftCard($chatId, $params[0] ?? null),
            'referral' => $this->generalCntrl->subReferral($chatId),

            'confirmReceipt' => $this->webhookCtrl ? $this->webhookCtrl->handleConfirmReceipt(
                $chatId,
                $params[0] ?? null,
                $callbackQueryId,
                $messageId,
                $params[1] ?? null
            ) : '',
            'cancelReceipt' => $this->webhookCtrl ? $this->webhookCtrl->handleCancelReceipt(
                $chatId,
                $params[0] ?? null,
                $callbackQueryId,
                $messageId,
                $params[1] ?? null
            ) : '',

            default => $this->customTextCtrl->getText('error.action.not_found')
        };
    }
}
