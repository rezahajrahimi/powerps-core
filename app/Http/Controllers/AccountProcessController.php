<?php
namespace App\Http\Controllers;

use App\Models\BotUser;
use App\Models\ReferralLogs;
use App\Models\Transaction;
use App\Models\TransactionSetting;
use App\Models\User;
use App\Models\UserState;
use App\Services\LoyaltyPointsService;
use App\Services\TelegramMessageFormatter;
use App\Services\TelegramService;
// add cache
use Illuminate\Support\Facades\Cache;
// add Request
use Illuminate\Http\Request;

// add cache

// add BotUser model
// add cache

class AccountProcessController extends Controller
{
    private const LOYALTY_HISTORY_PER_PAGE = 8;

    private TelegramService $telegramService;
    private CustomTextController $customTextCtrl;
    private SubscriptionProcessController $subscriptionProcessCtrl;
    private TransactionController $transactionCntrl;
    private GeneralController $generalCntrl;
    private ReferralWalletController $referralWalletCtrl;
    private AccountBallanceController $accBlCtrl;
    private BotUser $botUser;
    private LogController $logCtrl;
    private $chatId;
    private TransactionController $trCntrl;
    private PaymentTypeController $pymntCntrl;
    private PaymentMenuItemController $pymMenCntrl;
    private PaymentSettingController $paymnetSettingCntrl;
    private ShetabVerifyController $shetabVerifyCntrl;
    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
        $this->customTextCtrl = new CustomTextController();
        $this->subscriptionProcessCtrl = new SubscriptionProcessController($this->telegramService);
        $this->transactionCntrl = new TransactionController($this->telegramService);
        $this->generalCntrl = new GeneralController();
        $this->referralWalletCtrl = new ReferralWalletController();
        $this->accBlCtrl = new AccountBallanceController();
        $this->botUser = new BotUser();
        $this->logCtrl = new LogController();
        $this->pymntCntrl = new PaymentTypeController();
        $this->pymMenCntrl = new PaymentMenuItemController();
        $this->trCntrl = new TransactionController();
        $this->paymnetSettingCntrl = new PaymentSettingController();
        $this->shetabVerifyCntrl = new ShetabVerifyController();
    }
    public function accountDetails($chatId)
    {
        try {
            $this->chatId = $chatId;
            $botUser = BotUser::where('account_id', $chatId)->first();
            if ($botUser == null) {
                return $this->generalCntrl->return_main_menu_items($chatId, $this->customTextCtrl->getText('error.user_not_found'));
            }

            $ballance = $this->accBlCtrl->getUserAccuntBalance($chatId);
            $ballanceInDollar = $this->accBlCtrl->getUserAccuntBalanceInDollar($chatId);
            $referralAmount = $this->referralWalletCtrl->get_amount_of_ref_wallet_by_account_id($chatId);
            $loyaltyService = new LoyaltyPointsService();
            $loyaltyPoints = $loyaltyService->getBalanceByAccountId($chatId);
            $ballance = number_format($ballance, 0, '.', ',');
            $ballanceInDollar = number_format($ballanceInDollar, 0, '.', ',');
            $referralAmount = number_format($referralAmount, 0, '.', ',');
            $loyaltyPointsFormatted = number_format($loyaltyPoints, 0, '.', ',');
            $text = $this->customTextCtrl->getText('action.account.details', [
                'username' => $botUser->username,
                'name' => $botUser->first_name,
                'last_name' => $botUser->last_name,
                'account_id' => $botUser->account_id,
                'balance' => "$ballance تومان",
                'balance_in_dollar' => "$ballanceInDollar دلار",
                'referral_balance' => "$referralAmount تومان",
                'loyalty_balance' => "$loyaltyPointsFormatted امتیاز",
            ]);

            $formatter = new TelegramMessageFormatter($this->telegramService);
            try {
                $text = $formatter->addFormattedText('', $text)->getMessage();
            } catch (\Throwable $th) {
                \Log::error(["unable to format text: "]);
            }

            $this->generalCntrl->return_main_menu_items($chatId, $text);
            $this->show_additional_options($chatId);
            $this->addNewBotLog('account', 'وارد بخش جزئیات حساب شد.', 'show');
            return "";
        } catch (\Throwable $th) {
            \Log::error(["accountDetails: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    private function show_additional_options($chatId)
    {
        try {
            $opr = [];
            $text = $this->customTextCtrl->getText('action.account.additional_options.transactions');
            if (is_array($text)) {
                // use format text service
                $text = $this->telegramService->formatText($text);
            }
            $opr[] = [
                $text => "accountTransactions",
            ];
            $loyaltyService = new LoyaltyPointsService();
            if ($loyaltyService->isActive()) {
                $text = $this->customTextCtrl->getText('action.account.additional_options.loyalty_history');
                if (is_array($text)) {
                    $text = $this->telegramService->formatText($text);
                }
                $opr[] = [
                    $text => 'accountLoyaltyHistory',
                ];
            }
            $text = $this->customTextCtrl->getText('action.account.additional_options.sub_accounts');
            if (is_array($text)) {
                // use format text service
                $text = $this->telegramService->formatText($text);
            }
            $opr[] = [
                $text => "accountSubAccounts",
            ];

            $text = $this->customTextCtrl->getText('action.account.additional_options.add_balance');
            if (is_array($text)) {
                // use format text service
                $text = $this->telegramService->formatText($text);
            }
            $opr[] = [
                $text => "accountAddBalance",
            ];
            $text = $this->customTextCtrl->getText('action.account.additional_options');

            $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
            return "";
        } catch (\Throwable $th) {
            \Log::error(["show_additional_options: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function accountTransactions($chatId)
    {
        try {
            $this->telegramService->sendChatAction($chatId, 'typing');
            $this->chatId = $chatId;
            // $this->show_additional_options($chatId);
            $this->addNewBotLog('account', 'وارد بخش سابقه تراکنش‌ها شد.', 'show');
            $botUser = BotUser::where('account_id', $chatId)->first();
            if ($botUser == null) {
                return $this->generalCntrl->return_main_menu_items($chatId, $this->customTextCtrl->getText('error.server_error'));
            }

            $transactions = Transaction::where('account_id', $botUser->account_id)->get();
            $transactions = $transactions->sortByDesc('created_at');
            $transactions = $transactions->take(10);
            $text = $this->customTextCtrl->getText('action.account.transactions.title');
            $this->telegramService->sendMessage($chatId, $text);
            $text = "";
            if ($transactions->count() > 0) {
                foreach ($transactions as $transaction) {

                    $text .= $transaction->getTransactionText() . "\n";
                }
            } else {
                $text = $this->customTextCtrl->getText('action.account.transactions.no_transactions');
            }

            $this->telegramService->sendMessage($chatId, $text);
            return "";
        } catch (\Throwable $th) {
            \Log::error(["accountTransactions: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }

    public function accountLoyaltyHistory($chatId, $page = 1, $messageId = null)
    {
        try {
            $this->telegramService->sendChatAction($chatId, 'typing');
            $this->chatId = $chatId;
            $page = max(1, (int) $page);

            if ($page === 1 && $messageId === null) {
                $this->addNewBotLog('account', 'وارد بخش تاریخچه امتیاز شد.', 'show');
            }

            $loyaltyService = new LoyaltyPointsService();
            if (! $loyaltyService->isActive()) {
                return $this->generalCntrl->return_main_menu_items(
                    $chatId,
                    $this->customTextCtrl->getText('error.action.not_found')
                );
            }

            $user = User::where('account_id', $chatId)->first();
            if ($user === null) {
                return $this->generalCntrl->return_main_menu_items(
                    $chatId,
                    $this->customTextCtrl->getText('error.user_not_found')
                );
            }

            $balance = $loyaltyService->getBalanceByAccountId($chatId);
            $settings = $loyaltyService->getSettings();
            $baseQuery = \App\Models\LoyaltyTransaction::where('user_id', $user->id);
            $total = (clone $baseQuery)->count();

            if ($total === 0) {
                $text = $this->customTextCtrl->getText('action.account.loyalty_history.no_records');
                if (is_array($text)) {
                    $text = $this->telegramService->formatText($text);
                }
                $this->telegramService->sendMessage($chatId, $text);

                return '';
            }

            $summary = [
                'total' => $total,
                'earn_count' => (clone $baseQuery)->where('points', '>', 0)->count(),
                'redeem_count' => (clone $baseQuery)->where('points', '<', 0)->count(),
                'total_earned' => (int) (clone $baseQuery)->where('points', '>', 0)->sum('points'),
            ];

            $lastPage = max(1, (int) ceil($total / self::LOYALTY_HISTORY_PER_PAGE));
            $page = min($page, $lastPage);

            $transactions = (clone $baseQuery)
                ->orderByDesc('id')
                ->forPage($page, self::LOYALTY_HISTORY_PER_PAGE)
                ->get();

            $text = $this->buildLoyaltyHistoryMessage(
                $balance,
                $settings,
                $summary,
                $transactions,
                $page,
                $lastPage
            );
            $buttons = $this->buildLoyaltyHistoryPaginationButtons($page, $lastPage);

            if ($messageId !== null) {
                $this->telegramService->editMessageWithInlineKeyboard($chatId, $messageId, $text, $buttons);
            } else {
                $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $buttons);
            }

            return '';
        } catch (\Throwable $th) {
            \Log::error(['accountLoyaltyHistory: ' . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));

            return '';
        }
    }

    private function buildLoyaltyHistoryMessage(
        int $balance,
        $settings,
        array $summary,
        $transactions,
        int $page,
        int $lastPage
    ): string {
        $formatter = new TelegramMessageFormatter($this->telegramService);
        $balanceFormatted = number_format($balance, 0, '.', ',');
        $tomanPerPoint = number_format((int) ($settings?->toman_per_point ?? 10), 0, '.', ',');
        $totalEarnedFormatted = number_format($summary['total_earned'], 0, '.', ',');

        $formatter
            ->addBold('⭐ باشگاه مشتریان')
            ->addNewLine()
            ->addNewLine()
            ->addText("💰 موجودی: {$balanceFormatted} امتیاز")
            ->addNewLine()
            ->addText("💵 ارزش هر امتیاز: {$tomanPerPoint} تومان")
            ->addNewLine()
            ->addNewLine()
            ->addBold('📊 آمار کلی')
            ->addNewLine()
            ->addText("• کل رویدادها: {$summary['total']}")
            ->addNewLine()
            ->addText("• امتیازدهی: {$summary['earn_count']}")
            ->addNewLine()
            ->addText("• مصرف امتیاز: {$summary['redeem_count']}")
            ->addNewLine()
            ->addText("• جمع امتیاز کسب‌شده: {$totalEarnedFormatted}")
            ->addNewLine()
            ->addNewLine()
            ->addBold("📋 تاریخچه فعالیت‌ها (صفحه {$page} از {$lastPage})")
            ->addNewLine()
            ->addText('─────────────────');

        foreach ($transactions as $transaction) {
            $sign = $transaction->points > 0 ? '+' : '';
            $icon = $transaction->points > 0 ? '🟢' : '🔴';
            $pointsFormatted = number_format(abs((int) $transaction->points), 0, '.', ',');
            $eventLabel = $transaction->eventLabel();

            $formatter
                ->addNewLine()
                ->addNewLine()
                ->addText("{$icon} {$sign}{$pointsFormatted} امتیاز — {$eventLabel}")
                ->addNewLine()
                ->addText('   🕐 '.$this->formatLoyaltyHistoryDate($transaction->created_at));

            $description = trim((string) ($transaction->description ?? ''));
            if ($description !== '') {
                $formatter->addNewLine()->addText("   📝 {$description}");
            }
        }

        return $formatter->getMessage();
    }

    private function formatLoyaltyHistoryDate($createdAt): string
    {
        try {
            if ($createdAt instanceof \DateTimeInterface) {
                return verta($createdAt)->format('Y/m/d H:i');
            }

            $value = trim((string) $createdAt);
            if ($value === '') {
                return '—';
            }

            return verta($value)->format('Y/m/d H:i');
        } catch (\Throwable) {
            if ($createdAt instanceof \DateTimeInterface) {
                return $createdAt->format('Y-m-d H:i');
            }

            $value = trim((string) $createdAt);

            return $value !== '' ? $value : '—';
        }
    }

    private function buildLoyaltyHistoryPaginationButtons(int $page, int $lastPage): array
    {
        if ($lastPage <= 1) {
            return [];
        }

        $buttons = [];
        if ($page > 1) {
            $buttons['◀️ قبلی'] = 'accountLoyaltyHistoryPage-'.($page - 1);
        }
        if ($page < $lastPage) {
            $buttons['بعدی ▶️'] = 'accountLoyaltyHistoryPage-'.($page + 1);
        }

        return $buttons === [] ? [] : [$buttons];
    }

    public function accountSubAccounts($chatId)
    {
        try {
            // todo check on production
            $this->chatId = $chatId;
            $this->addNewBotLog('account', 'وارد بخش زیر مجموعه ها شد.', 'show');
            $botUser = BotUser::where('account_id', $chatId)->first();
            if ($botUser == null) {
                return $this->generalCntrl->return_main_menu_items($chatId, $this->customTextCtrl->getText('error.server_error'));
            }
            $user = User::where('account_id', $chatId)->first();
            if ($user == null) {
                return $this->generalCntrl->return_main_menu_items($chatId, $this->customTextCtrl->getText('error.server_error'));
            }
            $subAccounts = ReferralLogs::where('referral_user_id', $user->id)
                ->whereNull('transaction_id')
                ->with('referral_to')
                ->get();
            $text = $this->customTextCtrl->getText('action.account.sub_accounts.title');
            $this->telegramService->sendMessage($chatId, $text);
            $text = "";

            if ($subAccounts->count() > 0) {
                foreach ($subAccounts as $subAccount) {
                    $text .= $subAccount->getReferralLogsText() . "\n";
                }
            } else {
                $text = $this->customTextCtrl->getText('action.account.sub_accounts.no_sub_accounts');
            }
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        } catch (\Throwable $th) {
            \Log::error(["accountSubAccounts: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function accountAddBalance($chatId, $actionList = null)
    {
        try {
            $this->chatId = $chatId;
            $this->addNewBotLog('account', 'وارد بخش افزایش اعتبار حساب شد.', 'show'); // check if actionList is array and have not more than 1  elements

            $this->return_payment_options();
            return "";
        } catch (\Throwable $th) {
            \Log::error(["accountAddBalance: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    private function return_payment_options()
    {
        try {
            $paymentAccessService = new \App\Services\PaymentAccessService();
            $opr = [];

            $hasZarinPal = $this->pymntCntrl->getZarinpalStatus()
                && $paymentAccessService->isAllowedForAccountId($this->chatId, 'zarinpal');
            if ($hasZarinPal == true || $hasZarinPal == 1) {
                $text = $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal');
                if (is_array($text)) {
                    // use format text service
                    $text = $this->telegramService->formatText($text);
                }

                $newOpr = [
                    $text => "accountSubAccountsZarinpal",
                ];
                array_push($opr, $newOpr);
            }


            $hasDollarPay = $this->paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction')
                && $paymentAccessService->isAllowedForAccountId($this->chatId, 'usd_transaction');
            \Log::info(["hasDollarPay: " . $hasDollarPay]);
            if ($hasDollarPay == true || $hasDollarPay == 1) {
                // $text = $this->customTextCtrl->getText('action.process.add_online_balance.dollarpay');
                // if (is_array($text)) {
                //     // use format text service
                //     $text = $this->telegramService->formatText($text);
                // }
                // $newOpr = [
                //     $text => "accountSubAccountsDollarPay",
                // ];
                // array_push($opr, $newOpr);

                $cryptoPymentCntrl = new CryptoPaymentController();
                $nowpayments = $cryptoPymentCntrl->getCryptoPaymentStatusByKey('nowpayments')
                    && $paymentAccessService->isAllowedForAccountId($this->chatId, 'nowpayments');
                if ($nowpayments == true || $nowpayments == 1) {
                    $text = $this->customTextCtrl->getText('action.process.add_online_balance.dollarpay.nowpayment');
                    if (is_array($text)) {
                        // use format text service
                        $text = $this->telegramService->formatText($text);
                    }
                    $newOpr = [
                        $text => "accountSubAccountsNowpayment",
                    ];
                    array_push($opr, $newOpr);
                }
                $cryptomus = $cryptoPymentCntrl->getCryptoPaymentStatusByKey('cryptomus')
                    && $paymentAccessService->isAllowedForAccountId($this->chatId, 'cryptomus');
                if ($cryptomus == true || $cryptomus == 1) {
                    $text = $this->customTextCtrl->getText('action.process.add_online_balance.dollarpay.cryptomus');
                    if (is_array($text)) {
                        // use format text service
                        $text = $this->telegramService->formatText($text);
                    }
                    $newOpr = [
                        $text => "accountSubAccountsCryptomus",
                    ];
                    array_push($opr, $newOpr);
                }
                $swappay = $cryptoPymentCntrl->getCryptoPaymentStatusByKey('swappay')
                    && $paymentAccessService->isAllowedForAccountId($this->chatId, 'swappay');
                if ($swappay == true || $swappay == 1) {
                    $text = $this->customTextCtrl->getText('action.process.add_online_balance.dollarpay.swappay');
                    if (is_array($text)) {
                        $text = $this->telegramService->formatText($text);
                    }
                    if ($text === null || $text === '' || $text === false) {
                        $text = 'پرداخت آنلاین با SwapPay (سواپ‌ولت)';
                    }
                    $newOpr = [
                        $text => "accountSubAccountsSwappay",
                    ];
                    array_push($opr, $newOpr);
                }


            }
            if (count($opr) > 0) {
                $text = $this->customTextCtrl->getText('action.process.add_online_balance');
                // check if the text is json format

                $this->telegramService->sendMessageWithInlineKeyboard($this->chatId, $text, $opr);
            }

            // send offline item
            $opr = [];
            // check payment setting for shetab verify
            $allowOffline = $paymentAccessService->isAllowedForAccountId($this->chatId, 'offline');
            $shetabVerifyStatus = $this->shetabVerifyCntrl->check_shetab_verify_status() && $allowOffline;
            if ($shetabVerifyStatus == true || $shetabVerifyStatus == 1) {
                // $text = $this->paymnetSettingCntrl->getPaymentSettingDescriptionByKey('shetab_verify');
                $text = $this->customTextCtrl->getText('action.process.add_online_balance.shetab_verify');
                if (is_array($text)) {
                    // use format text service
                    $text = $this->telegramService->formatText($text);
                }
                $opr[] = [
                    $text => "shetabVerify-addBalance",
                ];
            }



            $offlinePayment = $allowOffline ? $this->pymntCntrl->getAllActiveOfflinePaymentTypes() : null;
            if ($offlinePayment != null) {
                if ($hasZarinPal == true || $hasZarinPal == 1 || $hasDollarPay == true || $hasDollarPay == 1) {
                    $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option_and_online_balance');
                } else {
                    $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option');
                }



                foreach ($offlinePayment as $key => $value) {
                    $opr[] = [
                        "$value->name" => "offlineGateway-$value->id ",
                    ];
                }

            }
            $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option');

            $this->telegramService->sendMessageWithInlineKeyboard($this->chatId, $text, $opr);
            return true;

        } catch (\Throwable $th) {
            \Log::error(["return_payment_options: " . $th]);
            $this->clearAwaitingReply($this->chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function handleActionAddBalanceZarinpal(string $chatId): string
    {
        try {
            $this->setAwaitingReply($chatId, 'add_balance_reply', 'zarinpal');
            $this->telegramService->forceReply($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal.reply'));
            return "";
        } catch (\Throwable $th) {
            \Log::error(["handleActionAddBalanceZarinpal: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function handleActionAddBalanceNowpayments(string $chatId): string
    {
        try {
            $this->setAwaitingReply($chatId, 'add_balance_reply', 'nowpayments');
            $this->telegramService->forceReply($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.nowpayments.reply'));
            return "";
        } catch (\Throwable $th) {
            \Log::error(["handleActionAddBalanceNowpayments: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function handleActionAddBalanceCryptomus(string $chatId): string
    {
        try {
            $this->setAwaitingReply($chatId, 'add_balance_reply', 'cryptomus');
            $this->telegramService->forceReply($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.cryptomus.reply'));
            return "";
        } catch (\Throwable $th) {
            \Log::error(["handleActionAddBalanceCryptomus: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function handleActionAddBalanceSwappay(string $chatId): string
    {
        try {
            $this->setAwaitingReply($chatId, 'add_balance_reply', 'swappay');
            $reply = $this->customTextCtrl->getText('action.process.add_online_balance.swappay.reply');
            if ($reply === null || $reply === '' || $reply === false) {
                $reply = 'مبلغ دلاری مورد نظر برای پرداخت با SwapPay را وارد کنید:';
            }
            $this->telegramService->forceReply($chatId, $reply);
            return "";
        } catch (\Throwable $th) {
            \Log::error(["handleActionAddBalanceSwappay: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function addBalanceReply(string $chatId, string $text): string
    {
        try {
            if ($this->telegramService->isCancelOrExitText($text)) {
                $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('action.process.reply.cancel_done'));
                return "";
            }
            $amount = $this->telegramService->parseNumericAmount($text);
            if ($amount === null) {
                $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal.reply.invalid_amount'));
                return "";
            }
            $user_state = UserState::where('chat_id', $chatId)
                ->where('state', 'add_balance_reply')
                ->latest()
                ->first();
            $paymentType = $this->resolveAwaitingPaymentType($user_state);
            if ($paymentType === null) {
                $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
                return "";
            }
            if ($paymentType == 'zarinpal') {
                // zarinpal => create a new invoice with amount
                $opr = [];
                $link = $this->generalCntrl->createZarinpalPaymentLink($chatId, $amount);
                array_push($opr, $link);
                $this->telegramService->sendMessageWithLinkButtons($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal.reply.invoice'), $opr);
                $this->clearAwaitingReply($chatId, '');
                return "";
            } elseif ($paymentType == "nowpayments") {
                $opr = [];
                $link = $this->generalCntrl->createNowPaymentsLink($chatId, $amount);
                array_push($opr, $link);

                $this->telegramService->sendMessageWithLinkButtons($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.nowpayments.reply.invoice'), $opr);
                $this->clearAwaitingReply($chatId, '');
                return "";

            } else if ($paymentType == "cryptomus") {
                $opr = [];
                $link = $this->generalCntrl->createCryptomusLink($chatId, $amount);
                array_push($opr, $link);

                $this->telegramService->sendMessageWithLinkButtons($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.cryptomus.reply.invoice'), $opr);
                $this->clearAwaitingReply($chatId, '');
                return "";
            } else if ($paymentType == "swappay") {
                if ($amount < 0.1) {
                    $this->telegramService->sendMessage(
                        $chatId,
                        'حداقل مبلغ پرداخت SwapPay ۰٫۱ دلار است. لطفا مبلغ بزرگ‌تری وارد کنید.'
                    );
                    return "";
                }
                $link = $this->generalCntrl->createSwapPayLink($chatId, $amount);
                if (! $this->isValidPaymentLinkButton($link)) {
                    $error = is_array($link) ? trim((string) ($link['error'] ?? '')) : '';
                    $this->telegramService->sendMessage(
                        $chatId,
                        $error !== ''
                            ? $error
                            : 'ایجاد لینک پرداخت SwapPay ناموفق بود. لطفا دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.'
                    );
                    return "";
                }
                $invoiceText = $this->customTextCtrl->getText('action.process.add_online_balance.swappay.reply.invoice');
                if ($invoiceText === null || $invoiceText === '' || $invoiceText === false) {
                    $invoiceText = 'برای پرداخت روی دکمه زیر بزنید:';
                }
                $this->telegramService->sendMessageWithLinkButtons($chatId, $invoiceText, [$link]);
                $this->clearAwaitingReply($chatId, '');
                return "";
            } elseif ($paymentType == "dollarpay") {
                // create a new invoice with amount
                $this->generalCntrl->createDollarPayPaymentLink($chatId, $amount);
                $this->clearAwaitingReply($chatId, '');
                return "";
            } elseif ($paymentType == "shetab_verify") {
                // create a new invoice with amount
                $this->processShetabVerification($chatId, (string) $amount);
                return "";
            }
            $this->clearAwaitingReply($chatId, '');
            return "";
        } catch (\Throwable $th) {
            \Log::error(["addBalanceReply: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }

    public function adminFastCharge($chat_id, $amount, $user_id)
    {
        try {
            $this->chatId = $chat_id;
            $user = User::where('account_id', $chat_id)->first();
            if ($user == null) {
                return $this->telegramService->sendMessage($chat_id, $this->customTextCtrl->getText('error.user_not_found'));
            }
            if ($user->role != 'admin') {
                return $this->telegramService->sendMessage($chat_id, $this->customTextCtrl->getText('error.user_not_found'));
            }
            // بررسی معتبر بودن مقدار
            if (!is_numeric($amount) || $amount <= 0) {
                return $this->telegramService->sendMessage($chat_id, $this->customTextCtrl->getText('error.invalid_amount'));
            }
            // پیدا کردن کاربر
            $botUser = BotUser::where('account_id', $user_id)->first();
            if ($botUser == null) {
                return $this->telegramService->sendMessage($chat_id, $this->customTextCtrl->getText('error.user_not_found'));
            }

            // افزایش موجودی حساب
            $this->accBlCtrl->incUserAccuntBalance($user_id, $amount);
            // ثبت لاگ
            $this->addNewBotLog('admin', "شارژ سریع حساب کاربر {$user_id} به مبلغ {$amount} تومان", 'charge');

            // ارسال پیام موفقیت به ادمین
            $this->telegramService->sendMessage($chat_id, "حساب کاربر {$user_id} با موفقیت به مبلغ {$amount} تومان شارژ شد.");

            // ارسال پیام به کاربر
            $this->telegramService->sendMessage($user_id, $this->customTextCtrl->getText('action.account.balance_added', [
                'amount' => number_format($amount, 0, '.', ',') . ' تومان',
            ]));
            return "";

        } catch (\Throwable $th) {
            \Log::error(["adminFastCharge: " . $th]);
            $this->telegramService->sendMessage($chat_id, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }
    public function handleActionAddBalanceShetabVerify(string $chatId, string $text)
    {
        try {
            $this->chatId = $chatId;
            $this->setAwaitingReply($chatId, 'add_balance_reply', 'shetab_verify');
            $this->telegramService->forceReply($chatId, $this->customTextCtrl->getText('action.process.add_online_balance.shetab_verify.reply'));
            return "";


        } catch (\Throwable $th) {
            \Log::error(["handleActionAddBalanceShetabVerify: " . $th]);
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));
            return "";
        }
    }

    public function setAwaitingReply(string $chatId, string $type, string $paymentType): void
    {
        try {
            $user_state = new UserState();
            $user_state->chat_id = $chatId;
            $user_state->state = 'add_balance_reply';
            $user_state->data = ['type' => $paymentType];
            $user_state->save();

            // می‌توانید از کش یا دیتابیس استفاده کنید
            Cache::put("awaiting_reply_{$chatId}", $type, now()->addMinutes(5));
        } catch (\Throwable $th) {
            \Log::error(["setAwaitingReply: " . $th]);
        }
    }

    private function resolveAwaitingPaymentType(?UserState $userState): ?string
    {
        if ($userState === null) {
            return null;
        }

        $data = $userState->data;
        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            } else {
                return $data;
            }
        }

        if (is_array($data)) {
            $type = $data['type'] ?? $data['payment_type'] ?? $data[0] ?? null;

            return is_string($type) && $type !== '' ? $type : null;
        }

        return is_scalar($data) ? (string) $data : null;
    }

    private function isValidPaymentLinkButton(mixed $link): bool
    {
        if (! is_array($link)) {
            return false;
        }

        $text = trim((string) ($link['text'] ?? ''));
        $url = trim((string) ($link['url'] ?? ''));

        return $text !== '' && TelegramService::isInlineUrlButtonValid($url);
    }
    private function awaitingReply(string $chatId): bool
    {
        return Cache::has("awaiting_reply_{$chatId}");
    }
    private function getAwaitingReplyType(string $chatId): ?string
    {
        return Cache::get("awaiting_reply_{$chatId}");
    }
    private function clearAwaitingReply(string $chatId, string|array $text): void
    {
        try {
            if (is_array($text)) {
                $text = $this->telegramService->formatText($text);
            }
            Cache::forget("awaiting_reply_{$chatId}");
            $user_state = UserState::where('chat_id', $chatId)->latest()->first();
            if ($user_state != null) {
                $user_state->delete();
            }
            if ($text === '' || $text === null) {
                $text = 'یک گزینه را از منوی اصلی انتخاب کنید.';
            }
            $this->generalCntrl->return_main_menu_items($chatId, $text);
        } catch (\Throwable $th) {
            \Log::error(["clearAwaitingReply: " . $th]);
        }
    }
    private function addNewBotLog($type, $message, $event)
    {
        $logCtrl = new LogController();
        $this->logCtrl->addNewLog($type, $message, $this->chatId, $this->botUser->username, $event);
        return true;
    }

    public function processShetabVerification($chatId, $text, $amountOverride = null)
    {
        try {
            $this->chatId = $chatId;
            $this->botUser = $this->botUser->getUserByAccountID($chatId);

            $user = User::where('account_id', $chatId)->first();
            if (! $user) {
                $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('error.user_not_found'));

                return false;
            }

            $productCategoryId = null;
            $amount = $text;

            $productCategory = \App\Models\ProductCategory::find($text);
            if ($productCategory) {
                $productCategoryId = (int) $productCategory->id;
                if ($amountOverride !== null && is_numeric($amountOverride) && (float) $amountOverride > 0) {
                    $amount = max(1, (int) ceil((float) $amountOverride));
                } else {
                    $balance = $this->accBlCtrl->getLoggedUserBallancce($chatId);
                    $amount = max(1, (int) ceil($productCategory->price - $balance->ballance));
                }

                $this->addNewBotLog(
                    'shetab_verify',
                    "درخواست خرید خودکار با شتاب برای بسته «{$productCategory->category_name}» (مبلغ مورد نیاز: {$amount} تومان)",
                    'auto_purchase_request'
                );
            }

            $request = new Request();
            $request->amount = $amount;
            $request->user_id = $user->id;
            $request->product_category_id = $productCategoryId;

            $shetabVerify_amount = $this->shetabVerifyCntrl->create_new_shetab_verify($request);

            if ($shetabVerify_amount === null) {
                \Log::error(['shetabVerify amount is null', 'chat_id' => $chatId, 'text' => $text]);
                $this->addNewBotLog('shetab_verify', 'صدور فاکتور تایید خودکار شتاب ناموفق بود.', 'failed');
                $this->telegramService->sendMessage($chatId, $this->customTextCtrl->getText('error.server_error'));

                return false;
            }

            $merchant_id = $this->paymnetSettingCntrl->getPaymentSettingDescriptionByKey('shetab_verify');
            $messageText = $this->customTextCtrl->getText('action.process.shetab_verify.new_invoice', [
                'merchant_id' => $merchant_id,
                'amount' => $shetabVerify_amount,
            ]);

            if (is_array($messageText)) {
                $messageText = $this->telegramService->formatText($messageText);
            }

            $this->clearAwaitingReply($chatId, $messageText);

            return '';

        } catch (\Exception $e) {
            \Log::error('Error in processShetabVerification: ' . $e);
            $this->addNewBotLog('shetab_verify', 'خطا در فرآیند تایید خودکار شتاب: ' . $e->getMessage(), 'failed');
            $this->clearAwaitingReply($chatId, $this->customTextCtrl->getText('error.server_error'));

            return false;
        }
    }

}
