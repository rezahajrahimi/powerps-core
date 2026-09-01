<?php
namespace App\Http\Controllers;
// add_order_crypto_by_nowpayment
use App\Http\Controllers\CustomTextController;
use App\Models\MainMenuItem;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\BotUser;
use App\Models\TransactionSetting;
use App\Services\TelegramMessageFormatter;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class GeneralController extends Controller
{
    private CustomTextController $customTextCtrl;
    private TelegramService $telegramService;
    private AccountBallanceController $accBlCtrl;
    private ReferralWalletController $referralCntrl;
    private ProductCategoryController $prCatCntrl;
    private PannelController $panelCntrl;
    private MainMenuItemController $menuItemCntrl;
    private PaymentTypeController $pymntCntrl;
    private PaymentMenuItemController $pymMenCntrl;
    private CryptoPaymentController $cryptoPymentCntrl;
    private TransactionController $trCntrl;
    private BillController $billCntrl;
    private MainMenuItem $mainMenuItem;
    private ProductCategory $productCategory;
    private PaymentSettingController $paymnetSettingCntrl;
    private ChannelLockMenuItemController $channelLockMenuItemCntrl;
    private CronJobController $cronJobCntrl;
    private GiftCardMenuItemController $giftCardMenuItemCntrl;
    private SettingController $settingCntrl;
    private PaymentSettingController $pymntSettingCntrl;
    private CryptoPaymentController $cryptoPaymentCntrl;
    public function __construct()
    {
        $this->customTextCtrl = new CustomTextController();
        $this->telegramService = new TelegramService();
        $this->accBlCtrl = new AccountBallanceController();
        $this->referralCntrl = new ReferralWalletController();
        $this->prCatCntrl = new ProductCategoryController();
        $this->panelCntrl = new PannelController();
        $this->menuItemCntrl = new MainMenuItemController();
        $this->pymntCntrl = new PaymentTypeController();
        $this->pymMenCntrl = new PaymentMenuItemController();
        $this->cryptoPymentCntrl = new CryptoPaymentController();
        $this->trCntrl = new TransactionController();
        $this->billCntrl = new BillController();
        $this->mainMenuItem = new MainMenuItem();
        $this->productCategory = new ProductCategory();
        $this->paymnetSettingCntrl = new PaymentSettingController();
        $this->channelLockMenuItemCntrl = new ChannelLockMenuItemController();
        $this->cronJobCntrl = new CronJobController();
        $this->giftCardMenuItemCntrl = new GiftCardMenuItemController();
        $this->settingCntrl = new SettingController();
        $this->pymntSettingCntrl = new PaymentSettingController();
        $this->cryptoPaymentCntrl = new CryptoPaymentController();
    }
    public function boot_seeding_data()
    {

        // add default menu items
        $this->menuItemCntrl->seed();
        // add default channel lock menu items
        $this->channelLockMenuItemCntrl->seed();
        // add default cron jobs
        $this->cronJobCntrl->seed();
        // add default crypto payment
        $this->cryptoPymentCntrl->createNowPaymentData();
        // add default gift card menu items
        $this->giftCardMenuItemCntrl->seed();
        // add default setting
        $this->settingCntrl->seed();
        // add default payment types
        $this->pymntCntrl->seed();
        // add default payment menu items
        $this->pymMenCntrl->seed();
        // add default custom texts
        $this->customTextCtrl->seed();
        // add default transaction settings
        $this->pymntSettingCntrl->seed();
        // crypto payment
        $this->cryptoPaymentCntrl->seed();


    }
    public function get_license_type()
    {
        $authCntrl = new AuthController();
        return $authCntrl->getPowerPsLicenseType();
    }
    public function getDashboardAnalytics()
    {
        try {
            $botUsetCntrl = new BotUserController();
            $getLast10Users = $botUsetCntrl->getLast10Users();
            $logCntrl = new LogController();
            $getTop20Log = $logCntrl->getAllLogs(20);
            $transactionCntrl = new TransactionController();
            $last10ConfirmedTransaction = $transactionCntrl->getConfirmedTransactions(request(), 10);
            if (method_exists($last10ConfirmedTransaction, 'items')) {
                $last10ConfirmedTransaction = $last10ConfirmedTransaction->items();
            }
            $unConfirmedTransaction = $transactionCntrl->getUnConfirmedTransactions(request(), 10);

            $productCatCntrl = new ProductCategoryController();
            $mostSelledProductCategory = $productCatCntrl->mostSelledProductCategory(10);
            $prCntrl = new ProductController();
            $last10ProductSelled = $prCntrl->getLastProductSelled(10);

            // Financial Summary
            $todaySales = \App\Models\Transaction::where('confirmed', true)
                ->whereDate('created_at', \Carbon\Carbon::today())
                ->sum('amount');
            $weekSales = \App\Models\Transaction::where('confirmed', true)
                ->whereBetween('created_at', [\Carbon\Carbon::now()->startOfWeek(), \Carbon\Carbon::now()->endOfWeek()])
                ->sum('amount');
            $monthSales = \App\Models\Transaction::where('confirmed', true)
                ->whereMonth('created_at', \Carbon\Carbon::now()->month)
                ->sum('amount');

            // Panel list (live status fetched separately per panel — avoids blocking dashboard)
            $pannels = \App\Models\Pannel::all();
            $pannelsStatus = [];
            foreach ($pannels as $pannel) {
                $totalUsers = \App\Models\Product::whereHas('product_category', function ($query) use ($pannel) {
                    $query->where('pannel_id', $pannel->id);
                })->count();

                $pannelsStatus[] = [
                    'id' => $pannel->id,
                    'type' => $pannel->type,
                    'location' => $pannel->location,
                    'total_users' => $totalUsers,
                ];
            }

            return response()->json(
                [
                    'Last10User' => $getLast10Users,
                    'Last20Logs' => $getTop20Log,
                    'Last10ConfirmedTransaction' => $last10ConfirmedTransaction,
                    'UnConfirmedTransaction' => $unConfirmedTransaction,
                    'MostSelledProductCategory' => $mostSelledProductCategory,
                    'last10ProductSelled' => $last10ProductSelled,
                    'PannelsStatus' => $pannelsStatus,
                    'FinancialSummary' => [
                        'today' => $todaySales,
                        'week' => $weekSales,
                        'month' => $monthSales,
                    ],
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("error on getDashboardAnalytics-> $th");
            return response()->json(null, 500);
        }
    }

    /**
     * Lightweight per-panel status for dashboard widgets (short timeout, independent of main analytics).
     */
    public function getPanelDashboardStatus($pannelID)
    {
        try {
            $pannel = \App\Models\Pannel::find($pannelID);
            if (!$pannel) {
                return response()->json(['success' => false, 'message' => 'Panel not found'], 404);
            }

            $totalUsers = \App\Models\Product::whereHas('product_category', function ($query) use ($pannel) {
                $query->where('pannel_id', $pannel->id);
            })->count();

            $isOnline = false;
            $onlineUsers = 0;

            if ($pannel->type === 'sanaei') {
                $status = (new SanaeiPannelController())->dashboardStatus($pannel);
                $isOnline = (bool) ($status['is_online'] ?? false);
                $onlineUsers = (int) ($status['online_users'] ?? 0);
            } elseif ($pannel->type === 'hiddify') {
                $url = rtrim((string) $pannel->admin_url, '/') . '/api/v2/admin/server_status/';
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                    ->withHeaders(['Hiddify-API-Key' => $pannel->secret_code ?? ''])
                    ->timeout(6)
                    ->connectTimeout(6)
                    ->get($url);
                $isOnline = $response->ok();
            } elseif ($pannel->isMarzbanCompatible()) {
                $controller = MarzbanPannelController::resolve($pannel);
                $isOnline = $controller->isOnline($pannel);
            }

            return response()->json([
                'success' => true,
                'id' => $pannel->id,
                'type' => $pannel->type,
                'location' => $pannel->location,
                'total_users' => $totalUsers,
                'online_users' => $onlineUsers,
                'is_online' => $isOnline,
            ], 200);
        } catch (\Throwable $th) {
            \Log::debug('getPanelDashboardStatus failed: ' . $th->getMessage());

            return response()->json([
                'success' => true,
                'id' => (int) $pannelID,
                'is_online' => false,
                'online_users' => 0,
                'error' => $th->getMessage(),
            ], 200);
        }
    }

    public function getAgentDashboardAnalytics()
    {
        try {
            $accCntrl = new AccountBallanceController();
            $accBallance = $accCntrl->getLoggedUserBallancce();
            $agentPrCntrl = new AgentProductController();
            $products = $agentPrCntrl->getProductsOfLoggedAgent();
            // $boughtProducts =  $agentPrCntrl->getAgentSelledProducts(10);
            $logCntrl = new LogController();
            $getTop20Log = $logCntrl->getAllLogsOfLoggedAgent(20);
            $agentPermisson = \App\Models\AgentPermisson::where('user_id', auth()->user()->id)->first();
            $agentPrCntrl = new AgentProductController();
            $agentLimitUsage = $agentPrCntrl->getAgentLimitUsage(auth()->user()->id);
            return response()->json(
                [
                    'accBallance' => $accBallance,
                    'products' => $products,
                    // 'boughtProducts' => $boughtProducts,
                    'Last20Logs' => $getTop20Log,
                    'agentPermisson' => $agentPermisson,
                    'agentLimitUsage' => $agentLimitUsage,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("error on getAgentDashboardAnalytics-> $th");
            return response()->json(null, 500);
        }
    }
    public function getAgentPaymentWays()
    {
        try {
            $paymentAccessService = new \App\Services\PaymentAccessService();
            $user = auth('sanctum')->user();
            $group = $user ? $paymentAccessService->getGroupForUser($user) : null;

            $pymntCntrl = new PaymentTypeController();
            $pymentType = $pymntCntrl->getAllActivePaymentTypesWithZarinpalMerchentIDFilter();
            $cryptoPymentCntrl = new CryptoPaymentController();
            $cryptiPymentIsActive = $cryptoPymentCntrl->getNowPaymentsStatus();

            if ($group && $user) {
                $pymentType = collect($pymentType)->filter(function ($payment) use ($paymentAccessService, $group, $user) {
                    if ($payment->name === 'زرین پال') {
                        return $paymentAccessService->isAllowedForUserAndGroup($user, $group, 'zarinpal');
                    }
                    if ($payment->type === 'offline') {
                        return $paymentAccessService->isAllowedForUserAndGroup($user, $group, 'offline');
                    }

                    return true;
                })->values();

                $allowCrypto = $paymentAccessService->isAllowedForUserAndGroup($user, $group, 'usd_transaction')
                    && (
                        $paymentAccessService->isAllowedForUserAndGroup($user, $group, 'nowpayments')
                        || $paymentAccessService->isAllowedForUserAndGroup($user, $group, 'cryptomus')
                        || $paymentAccessService->isAllowedForUserAndGroup($user, $group, 'swappay')
                    );

                if (!$allowCrypto) {
                    $cryptiPymentIsActive = false;
                }
            }

            $swappayActive = $cryptoPymentCntrl->getCryptoPaymentStatusByKey('swappay');
            if ($group && $user) {
                $swappayActive = $swappayActive
                    && $paymentAccessService->isAllowedForUserAndGroup($user, $group, 'usd_transaction')
                    && $paymentAccessService->isAllowedForUserAndGroup($user, $group, 'swappay');
            }

            return response()->json([
                'active_payment' => $pymentType,
                'crypto_payment_status' => $cryptiPymentIsActive,
                'swappay_payment_status' => (bool) $swappayActive,
            ], 200);
        } catch (\Throwable $th) {
            \Log::info("error on getAgentPaymentWays-> $th");
            return response()->json(null, 500);
        }
    }

    public function getUserDashboardAnalytics()
    {
        try {
            $accCntrl = new AccountBallanceController();
            $accBallance = $accCntrl->getLoggedUserBallancce();
            $prCatCntrl = new ProductCategoryController();
            $userGroupId = auth('sanctum')->user()?->user_group_id;

            $products = $prCatCntrl->getAllActiveProdctCategoryOrderByPrice($userGroupId, true);
            // $boughtProducts =  $agentPrCntrl->getAgentSelledProducts(10);
            $logCntrl = new LogController();
            $getTop20Log = $logCntrl->getAllLogsOfLoggedAgent(20);
            return response()->json(
                [
                    'accBallance' => $accBallance,
                    'products' => $products,
                    // 'boughtProducts' => $boughtProducts,
                    'Last20Logs' => $getTop20Log,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("error on getAgentDashboardAnalytics-> $th");
            return response()->json(null, 500);
        }
    }
    public function get_zarinpal_payment_link_from_html($htmlText)
    {
        // $htmlText = '<!DOCTYPE html>...'; // your HTML text here

        $crawler = new Crawler();
        $crawler->addHtmlContent($htmlText, 'UTF-8');

        $formTag = $crawler->filter('form')->first();

        if ($formTag) {
            $actionUrl = $formTag->attr('action');
            return $actionUrl; // Output: https://www.zarinpal.com/pg/StartPay/A000000000000000000000000000l353wx62
        } else {
            return '';
        }
    }
    public function get_nowpayment_payment_link_from_html($htmlText)
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($htmlText, 'UTF-8');

        $metaTag = $crawler->filter('meta[http-equiv="refresh"]')->first();

        if ($metaTag) {
            $redirectLink = $metaTag->attr('content');
            $redirectLink = explode(';', $redirectLink);
            $redirectLink = trim($redirectLink[1]);
            $redirectLink = str_replace("url='", '', $redirectLink);
            $redirectLink = str_replace("'", '', $redirectLink);
            return $redirectLink; // Output: https://nowpayments.io/payment/?iid=5096100130
        } else {
            $linkTag = $crawler->filter('a')->first();
            if ($linkTag) {
                $redirectLink = $linkTag->attr('href');
                return $redirectLink; // Output: https://nowpayments.io/payment/?iid=5096100130
            } else {
                return '';
            }
        }
    }
    public function return_main_menu_items($chat_id, $message)
    {
        $menu = new MainMenuItemController();
        $menuItem = $menu->getAllActivatedMainMenuItems();
        $keyboardConfig = new \App\Services\BotKeyboardConfigService();
        $opr = $keyboardConfig->buildMainMenuKeyboard($menuItem);

        $result = $this->telegramService->sendMessageWithKeyboard($chat_id, $message, $opr);

        return '';

    }
    public function return_exist_hiddify_config_telegram_text($selectedProduct, $selectedProductCategory, $pannel, $chat_id)
    {
        $hiddifcCntrl = new HiddifyPannelController();
        $pnlCntrl = new PannelController();
        $userPannelLink = $hiddifcCntrl->get_hiddify_subscription_link($pannel->user_link, $selectedProduct->panel_link);
        $userSubscriptionLInk = $hiddifcCntrl->get_hiddify_subscription_link($pannel->user_link, $selectedProduct->subscription_link);
        $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
        $text = '';
        $agentCntrl = new AgentProductController();
        $configStatus = $agentCntrl->resolveBoughtProductStatusFromServer($selectedProduct->id);

        if (is_array($configStatus)) {
            $enableText = $configStatus['enable'] == true ? 'فعال' : 'غیر فعال';
            $text = "📦 وضعیت بسته: {$enableText} \r\n";
            $usageGB = $configStatus['current_usage_GB'];
            $usageGB = round($usageGB, 2);
            $limitGB = $configStatus['usage_limit_GB'];
            $text .= "📊 میزان حجم مصرف شده:  {$usageGB}GB از {$limitGB}GB \r\n";

            $startDate = $configStatus['start_date'];
            $startDate = Carbon::parse($startDate);
            $package_days = $configStatus['package_days'];
            $package_days = intval($package_days);
            $expireDate = Carbon::parse($startDate);
            $expireDate->addDays($package_days);

            $expireDate = $expireDate->toJalali()->format('Y.m.d');
            $startDate = $startDate->toJalali()->format('Y.m.d');

            $text .= "🗓️ تاریخ شروع: {$startDate} \r\n";
            $text .= "⏳ تاریخ انقضا: {$expireDate} \r\n";
        }
        if ($selectedProductCategory->show_pannel_link == 1) {
            $text .= "🔗 لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:\r\n{$userPannelLink} \r\n";
        }
        if ($selectedProductCategory->show_subscription_link == 1) {
            $text .= "🔗 لینک سابسکریپشن: \r\n{$userSubscriptionLInk} \r\n";
        }
        app('telegram_bot')->sendMessage($text, $chat_id, null, 'MarkDown');

        $text = "ℹ️ همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";
        return app('telegram_bot')->imageMessageByLink($image, $chat_id, $text);
    }
    public function new_hiddify_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $chat_id, $productID)
    {
        try {
            $hiddifcCntrl = new HiddifyPannelController();
            $pnlCntrl = new PannelController();
            $accountLabel = BotUser::resolveConfigAccountLabel($chat_id, $productID);

            $req = new Request();
            $req->accountId = $accountLabel;
            $req->chat_id = $chat_id;
            $req->product_id = $productID;
            $req->pannelID = $selectedPrCat->pannel_id;
            $req->vol = $volume;
            $req->day = $day;

            $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req); // api v2
            if ($newUUID == false) {
                \Log::error('new_hiddify_config_telegram_text: addUserToHiddifyPanel returned false', [
                    'chat_id' => $chat_id,
                    'pannel_id' => $selectedPrCat->pannel_id,
                    'product_id' => $productID,
                ]);

                return false;
            }
            // $newUUID = $hiddifcCntrl->addUserToHiddifyPanelOldApi($req); // api v1

            $userLink = $pannel->user_link;
            // check $pannel->user_link ended with "/" if be remove it
            if (substr($userLink, -1) == '/') {
                $userLink = substr($userLink, 0, -1);
            }

            // $userSubscriptionLInk = "$userLink/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $userSubscriptionLInk = "$userLink/{$newUUID}/#{$req->accountId}";
            $userPannelLink = "$userLink/{$newUUID}/#{$req->accountId}";

            $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
            $text = $this->customTextCtrl->getText('action.subscription.hiddify', [
                'panel_link' => $userPannelLink,
                'subscription_link' => $userSubscriptionLInk,
            ]);
            $formatter = new TelegramMessageFormatter($this->telegramService);
            $text = $formatter->addFormattedText('', $text)->getMessage();
            // save as dectivate product, So we can use it in future when user want to recharge it;
            $resualt = $this->telegramService->sendPhotoFile($chat_id, $image, $text);

            $request = new Request();
            $request->account_id = $chat_id;
            $request->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $request->product_categories_id = $selectedPrCat->id;
            $request->panel_link = "/{$newUUID}/#{$req->accountId}";
            $request->configs = '';
            $request->remark = $accountLabel;
            $request->product_id = $productID;
            $prCntrl = new ProductController();
            $prCntrl->addAutomatedProductDetails($request);
            return $newUUID;
        } catch (\Throwable $th) {
            \Log::info("error on new_hiddify_config_telegram_text-> $th");
            return false;
        }

    }
    public function new_sanaei_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $chat_id, $productID)
    {
        try {
            $snCtrl = new SanaeiPannelController();
            $accountLabel = BotUser::resolveConfigAccountLabel($chat_id, $productID);
            $category = ProductCategory::query()->find($selectedPrCat->id) ?? $selectedPrCat;
            $inboundIds = $category->resolveInboundIds();
            \Log::info('Sanaei create client inbound_ids', [
                'category_id' => $category->id,
                'category_name' => $category->category_name,
                'inbound_ids' => $inboundIds,
            ]);

            $req = new Request();
            $req->merge([
                'accountId' => $accountLabel,
                'chat_id' => $chat_id,
                'product_id' => $productID,
                'pannelID' => $category->pannel_id,
                'vol' => $volume,
                'day' => $day,
                'inbound_ids' => $inboundIds,
                'inbound_id' => $inboundIds[0] ?? $category->inbound_id,
                'ip_limit' => $category->ip_limit,
            ]);

            $result = $snCtrl->addUserToSanaeiPanel($req, $inboundIds);
            if ($result === false) {
                return false;
            }
            if (is_array($result)) {
                $uuid = $result['uuid'];
                $subId = $result['subId'];
                $clientEmail = $result['email'] ?? '';
            } else {
                $uuid = $result;
                $subId = $uuid;
                $clientEmail = '';
            }

            // Generate client links and QR codes
            $links = $snCtrl->getUserLinks($pannel, $uuid, $accountLabel, $selectedPrCat->inbound_id, $clientEmail ?: null);

            $subLink = $snCtrl->buildSubscriptionLink($pannel, $subId);

            $pnlCntrl = new PannelController();
            $subText = "لینک اشتراک شما:\n" . $subLink;
            $this->telegramService->sendMessage($chat_id, $subText);
            $image = $pnlCntrl->generateQrMOC($subLink);
            $this->telegramService->sendPhotoFile($chat_id, $image, $subLink);

            if ($this->categoryShouldSendConfigToUser($selectedPrCat) && ! empty($links)) {
                if (! empty($selectedPrCat->sample_inbound)) {
                    $config = preg_replace('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $uuid, $selectedPrCat->sample_inbound);
                    $links[0] = $config;
                }

                foreach ($links as $link) {
                    $linkText = $this->formatCustomTelegramText('action.subscription.sanaei_without_subscription', [
                        'uuid' => $link,
                    ]);
                    $linkImage = $pnlCntrl->generateQrMOC($link);
                    $this->telegramService->sendPhotoFile($chat_id, $linkImage, $linkText);
                }
            }

            $request = new Request();
            $request->account_id = $chat_id;
            $request->subscription_link = $subLink;
            $request->product_categories_id = $selectedPrCat->id;
            $request->panel_link = $subLink;
            $request->configs = json_encode([
                'uuid' => $uuid,
                'email' => $clientEmail,
                'subId' => $subId,
                'links' => $links ?? [],
            ]);
            $request->remark = $accountLabel;
            $request->product_id = $productID;
            $prCntrl = new ProductController();
            $prCntrl->addAutomatedProductDetails($request);
            return $uuid;
        } catch (\Throwable $th) {
            \Log::info("error on new_sanaei_config_telegram_text-> $th");
            return false;
        }
    }

    public function new_marzban_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $chat_id, $productID, ?string $username = null, ?string $textKey = null)
    {
        try {
            $mbCtrl = MarzbanPannelController::resolve($pannel);
            $pnlCntrl = new PannelController();
            $username = $username ?? $mbCtrl->buildBotUsername($chat_id, $productID);
            $textKey = $textKey ?? $pannel->customTextKey('action.subscription.marzban');

            $category = $selectedPrCat instanceof ProductCategory
                ? (ProductCategory::query()->find($selectedPrCat->id) ?? $selectedPrCat)
                : null;
            $marzbanInbounds = $category?->resolveMarzbanInbounds() ?? [];
            $pasarguardGroupIds = $category?->resolvePasarguardGroupIds() ?? [];
            \Log::info('Marzban create client inbounds', [
                'category_id' => $category?->id,
                'marzban_inbounds' => $marzbanInbounds,
                'pasarguard_group_ids' => $pasarguardGroupIds,
            ]);

            $userData = $mbCtrl->createUser(
                $pannel,
                $username,
                (int) $day,
                $volume,
                $marzbanInbounds !== [] ? $marzbanInbounds : null,
                $pasarguardGroupIds !== [] ? $pasarguardGroupIds : null
            );
            if ($userData === false) {
                return false;
            }

            $username = $userData['username'];
            $userSub = $userData['subscription_link'];
            $links = $userData['links'] ?? [];

            $text = $this->formatCustomTelegramText($textKey, [
                'panel_link' => $userSub,
                'subscription_link' => $userSub,
            ]);

            $image = $pnlCntrl->generateQrMOC($userSub);
            $this->telegramService->sendPhotoFile($chat_id, $image, $text);

            if ($this->categoryShouldSendConfigToUser($selectedPrCat)) {
                $linkTextKey = $pannel->customTextKey('action.subscription.marzban.link');
                $helpTextKey = $pannel->customTextKey('action.subscription.marzban.help');
                foreach ($links as $link) {
                    $linkText = $this->formatCustomTelegramText($linkTextKey, [
                        'link' => $link,
                    ]);
                    $linkImage = $pnlCntrl->generateQrMOC($link);
                    $this->telegramService->sendPhotoFile($chat_id, $linkImage, $linkText);
                }

                $helpText = $this->formatCustomTelegramText($helpTextKey);
                if ($helpText !== '') {
                    $this->telegramService->sendMessage($chat_id, $helpText);
                }
            }

            $request = new Request();
            $request->account_id = $chat_id;
            $request->subscription_link = $userData['subscription_url'] ?? '';
            $request->product_categories_id = $selectedPrCat->id;
            $request->panel_link = $userSub;
            $request->configs = json_encode([
                'username' => $username,
                'links' => $links,
            ]);
            $request->remark = $username;
            $request->product_id = $productID;
            $prCntrl = new ProductController();
            $prCntrl->addAutomatedProductDetails($request);

            return $username;
        } catch (\Throwable $th) {
            \Log::info("error on new_marzban_config_telegram_text-> $th");

            return false;
        }
    }

    private function categoryShouldSendConfigToUser($category): bool
    {
        if ($category instanceof ProductCategory) {
            return $category->shouldSendConfigToUser();
        }

        if (is_object($category) && isset($category->send_config_to_user)) {
            return filter_var($category->send_config_to_user, FILTER_VALIDATE_BOOLEAN);
        }

        return true;
    }

    private function formatCustomTelegramText(string $key, array $variables = []): string
    {
        $text = $this->customTextCtrl->getText($key, $variables);
        if (is_array($text)) {
            return $this->telegramService->formatText($text);
        }

        $formatter = new TelegramMessageFormatter($this->telegramService);

        return $formatter->addFormattedText('', (string) $text)->getMessage();
    }

    public function send_using_subscription_manual_message($chat_id, $recharge = null, $productID = null, $inventoryOnly = false)
    {
        $opr = [];
        // check faq is active in menu
        $faqItemAliasName = $this->mainMenuItem->getAliasNameByName('آموزش استفاده و سوالات متداول');
        $faqItem = $this->mainMenuItem->isActiveByAliasName($faqItemAliasName);
        if ($faqItem == true || $faqItem == 1) {
            $text = $this->customTextCtrl->getText('action.help.faq');
            if (is_array($text)) {
                // use format text service
                $text = $this->telegramService->formatText($text);
            }
            $opr[] = [
                $text => "toturial-faqs",
            ];
        }
        $appDownloadItemAliasName = $this->mainMenuItem->getAliasNameByName('دانلود برنامه');
        $appDownloadItem = $this->mainMenuItem->isActiveByAliasName($appDownloadItemAliasName);
        if ($appDownloadItem == true || $appDownloadItem == 1) {
            $text = $this->customTextCtrl->getText('action.help.appDownload');
            if (is_array($text)) {
                // use format text service
                $text = $this->telegramService->formatText($text);
            }
            $opr[] = [
                $text => "toturial-appDownload",
            ];
        }
        if ($recharge != null && ! $inventoryOnly) {
            $product = $productID !== null
                ? Product::with('product_category.pannel')->find($productID)
                : null;
            $isInventoryProduct = $product?->product_category?->pannel?->isInventoryPanel() ?? false;

            if (! $isInventoryProduct) {
                $text = $this->customTextCtrl->getText('action.history.buttun.recharge');
                if (is_array($text)) {
                    // use format text service
                    $text = $this->telegramService->formatText($text);
                }
                $opr[] = [
                    $text => "recharge-{$productID}",
                ];
                if ($product?->product_category?->pannel?->supportsRemarkRename() ?? false) {
                    $text = $this->customTextCtrl->getText('action.history.buttun.remark');
                    if (is_array($text)) {
                        // use format text service
                        $text = $this->telegramService->formatText($text);
                    }
                    $opr[] = [
                        $text => "remark-{$productID}",
                    ];
                }
                $text = $this->customTextCtrl->getText('action.history.buttun.delete');
                if (is_array($text)) {
                    $text = $this->telegramService->formatText($text);
                }
                $opr[] = [
                    $text => "deleteHistory-{$productID}",
                ];
            }
        }

        $text = $this->customTextCtrl->getText('action.help.using_subscription');
        $this->telegramService->sendMessageWithInlineKeyboard($chat_id, $text, $opr);
    }
    public function send_insufficient_balance_message(
        $chat_id,
        $productCategoryID,
        $overridePriceToman = null,
        $overridePriceDollar = null,
        $promoCode = null
    ) {
        try {
            $productCategory = $this->productCategory->find($productCategoryID);

            if ($productCategory == null) {
                \Log::info("productCategory is null in send_insufficient_balance_message , productCategoryID: $productCategoryID");
                return;
            }

            $agentProductCtrl = new AgentProductController();
            $pricing = $agentProductCtrl->resolveProductPricingForAccount($chat_id, $productCategoryID);
            if ($pricing === null) {
                $this->telegramService->sendMessage($chat_id, 'این بسته برای شما در دسترس نیست.');
                return false;
            }

            $productPriceInToman = $overridePriceToman !== null
                ? (float) $overridePriceToman
                : $pricing['price'];
            $productPriceInDollar = $overridePriceDollar !== null
                ? (float) $overridePriceDollar
                : $pricing['price_in_dollar'];
            $productCategory = $pricing['category'];

            if (is_string($promoCode) && trim($promoCode) !== '') {
                (new \App\Services\PromoCodeService())->rememberPendingCode(
                    (string) $chat_id,
                    (int) $productCategoryID,
                    $promoCode
                );
            }

            $user_ballance = $this->accBlCtrl->getLoggedUserBallancce($chat_id);
            $user_ballance_in_toman = $user_ballance->ballance;
            $user_ballance_in_toman = number_format($user_ballance_in_toman, 0, ',', '.');
            $user_ballance_in_toman = $user_ballance_in_toman . ' تومان';
            // calculate the diffrence between user_ballance and productPriceInToman
            $mainDiffrenceInToman = $diffrence = $productPriceInToman - $user_ballance->ballance;
            $diffrence = number_format($diffrence, 0, ',', '.');
            $diffrence = $diffrence . ' تومان';
            $productPriceInToman = number_format($productPriceInToman, 0, ',', '.');
            $productPriceInToman = $productPriceInToman . ' تومان';
            $mainDiffrenceInDollar = $diffrence_in_dollar = 0.00;

            $dollarTransaction = $this->paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');
            $text = '';
            if ($dollarTransaction == true || $dollarTransaction == 1) {
                $user_ballance_in_dollar = $user_ballance->account_ballance_in_dollar;
                $mainDiffrenceInDollar = $diffrence_in_dollar = $productPriceInDollar - $user_ballance_in_dollar;
                $productPriceInDollar = number_format($productPriceInDollar, 2, ',', '.');
                $productPriceInDollar = $productPriceInDollar . ' دلار';
                $user_ballance_in_dollar = number_format($user_ballance_in_dollar, 2, ',', '.');
                $user_ballance_in_dollar = $user_ballance_in_dollar . ' دلار';
                $diffrence_in_dollar = number_format($diffrence_in_dollar, 2, ',', '.');
                $diffrence_in_dollar = $diffrence_in_dollar . ' دلار';

                $text = $this->customTextCtrl->getText('action.process.insufficient_balance_with_dollar', [
                    'product_category_name' => $productCategory->category_name,
                    'product_price_in_toman' => $productPriceInToman,
                    'product_price_in_dollar' => $productPriceInDollar,
                    'user_balance_in_toman' => $user_ballance_in_toman,
                    'user_balance_in_dollar' => $user_ballance_in_dollar,
                    'difference_in_toman' => $diffrence,
                    'difference_in_dollar' => $diffrence_in_dollar,
                ]);
                $formatter = new TelegramMessageFormatter($this->telegramService);
                $text = $formatter->addFormattedText('', $text)->getMessage();

            } else {
                $text = $this->customTextCtrl->getText('action.process.insufficient_balance', [
                    'product_category_name' => $productCategory->category_name,
                    'product_price_in_toman' => $productPriceInToman,
                    'user_balance_in_toman' => $user_ballance_in_toman,
                    'difference_in_toman' => $diffrence,
                ]);
            }
            if (is_array($text)) {
                $formatter = new TelegramMessageFormatter($this->telegramService);
                $text = $formatter->addFormattedText('', $text)->getMessage();
            }
            $this->telegramService->sendMessage($chat_id, $text);
            (new \App\Services\PurchaseIntentService())->record($chat_id, (int) $productCategoryID, 'insufficient_balance');
            $this->send_add_ballance_option_message($chat_id, $mainDiffrenceInToman, $mainDiffrenceInDollar, $productCategoryID);
            return true;
        } catch (\Throwable $th) {
            \Log::info("error on send_insufficient_balance_message-> $th");
            return false;
        }
    }
    public function send_admin_message_to_botuser(Request $request)
    {
        try {
            if ($request->message == "") {
                return response()->json(false, 400);
            }

            $botUser = BotUser::find($request->userID)
                ?? BotUser::where('account_id', $request->userID)->first();

            if (!$botUser) {
                return response()->json(['message' => 'کاربر یافت نشد'], 404);
            }

            $accountId = $botUser->account_id;
            $message = $request->message;

            $response = $this->telegramService->sendMessage($accountId, $message);
            if (!($response['ok'] ?? false)) {
                $response = $this->telegramService->sendPlainMessage($accountId, $message);
            }

            if (!($response['ok'] ?? false)) {
                $error = $response['description'] ?? 'خطا در ارسال پیام به تلگرام';
                \Log::error('send_admin_message_to_botuser failed', [
                    'account_id' => $accountId,
                    'error' => $error,
                ]);
                return response()->json(['message' => $error], 422);
            }

            $this->addNewBotLog("send", $message, $accountId, "");
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("errer on send_admin_message_to_botuser" . $th->getMessage());
            return response()->json(false, 500);

        }
    }
    public function send_add_ballance_option_message($chat_id, $estimatedPrice, $estimatedPriceInDollar, ?int $productCategoryId = null)
    {
        $opr = [];
        $hasZarinPal = $this->pymntCntrl->getZarinpalStatus();
        if ($hasZarinPal == true || $hasZarinPal == 1) {
            $newOpr = $this->createZarinpalPaymentLink($chat_id, $estimatedPrice);
            array_push($opr, $newOpr);
        }

        $hasDollarPay = $this->paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');
        if ($hasDollarPay == true || $hasDollarPay == 1) {
            // chack nowpayments is active
            $cryptoPymentCntrl = new CryptoPaymentController();
            $nowpayments = $cryptoPymentCntrl->getCryptoPaymentStatusByKey('nowpayments');
            if ($nowpayments == true || $nowpayments == 1) {
                $newOpr = $this->createNowPaymentsLink($chat_id, $estimatedPriceInDollar);
                array_push($opr, $newOpr);
            }
            $cryptomus = $cryptoPymentCntrl->getCryptoPaymentStatusByKey('cryptomus');
            if ($cryptomus == true || $cryptomus == 1) {
                $cryptomusOpr = $this->createCryptomusLink($chat_id, $estimatedPriceInDollar);
                array_push($opr, $cryptomusOpr);
            }
            $swappay = $cryptoPymentCntrl->getCryptoPaymentStatusByKey('swappay');
            if ($swappay == true || $swappay == 1) {
                $swappayOpr = $this->createSwapPayLink($chat_id, $estimatedPriceInDollar);
                if (! empty($swappayOpr) && ! empty($swappayOpr['url'])) {
                    array_push($opr, $swappayOpr);
                }
            }
        }

        if (count($opr) > 0) {
            $text = $this->customTextCtrl->getText('action.process.add_online_balance');
            if (is_array($text)) {
                // use format text service
                $text = $this->telegramService->formatText($text);
            }
            $this->telegramService->sendMessageWithLinkButtons($chat_id, $text, $opr);
        }

        // send offline item
        // check for shetab verify and if it is active then add it to the offline payment

        $offlinePayment = $this->pymntCntrl->getAllActiveOfflinePaymentTypes();
        if ($offlinePayment != null) {
            if ($hasZarinPal == true || $hasZarinPal == 1 || $hasDollarPay == true || $hasDollarPay == 1) {
                $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option_and_online_balance');
                if (is_array($text)) {
                    // use format text service
                    $text = $this->telegramService->formatText($text);
                }
            } else {
                $text = $this->customTextCtrl->getText('action.process.add_offline_balance_option');
                if (is_array($text)) {
                    // use format text service
                    $text = $this->telegramService->formatText($text);
                }
            }

            // clear $opr
            $opr = [];
            $shetabVerify = $this->paymnetSettingCntrl->getPaymentSettingStatusByKey('shetab_verify');
            if ($shetabVerify == true || $shetabVerify == 1) {
                $shetabVerify_text = $this->customTextCtrl->getText('action.process.add_online_balance.shetab_verify');
                if (is_array($shetabVerify_text)) {
                    // use format text service
                    $shetabVerify_text = $this->telegramService->formatText($shetabVerify_text);
                }
                $amountForCallback = max(1, (int) ceil((float) $estimatedPrice));
                $shetabAutoCallback = $productCategoryId
                    ? "shetabVerifyAuto-{$productCategoryId}-{$amountForCallback}"
                    : "shetabVerifyAuto-{$amountForCallback}";
                $opr[] = [
                    $shetabVerify_text => $shetabAutoCallback,
                ];
            }


            foreach ($offlinePayment as $key => $value) {
                $opr[] = [
                    "$value->name" => "offlineGateway-$value->id ",
                ];
            }

        }

        $this->telegramService->sendMessageWithInlineKeyboard($chat_id, $text, $opr);
        return "";

    }
    public function createZarinpalPaymentLink($chat_id, $estimatedPrice)
    {
        try {
            $request = new Request();
            $request->account_id = $chat_id;
            $request->amount = $estimatedPrice;
            $bill = $this->billCntrl->createNewBill($request);

            $trRequest = new Request();
            $trRequest->invoiceID = $bill->bill_id;
            $trRequest->account_id = $chat_id;
            $trRequest->amount = $estimatedPrice;
            $paymentLink = $this->trCntrl->add_order($trRequest);

            // format $estimatedPrice to 0 decimal
            $formattedPrice = number_format($estimatedPrice, 0, ',', '.');
            $text = $this->customTextCtrl->getText('action.process.add_online_balance.zarinpal');
            if (is_array($text)) {
                // use format text service
                $text = $this->telegramService->formatText($text);
            }

            return [
                'text' => $text . " $formattedPrice تومان",
                'url' => $paymentLink,
            ];
        } catch (\Throwable $th) {
            \Log::error("createZarinpalPaymentLink: " . $th);
            return [];
        }
    }
    public function createNowPaymentsLink($chat_id, $estimatedPriceInDollar)
    {
        try {

            $request = new Request();
            $request->account_id = $chat_id;
            $request->amount = $estimatedPriceInDollar;
            $bill = $this->billCntrl->createNewBillInDollar($request);

            $openLink = $this->pymntCntrl->getNowPaymentsLink();

            $trCryptoCntrl = new TransactionCryptoController();
            $trRequest = new Request();
            $trRequest['gateway'] = "nowpayments";
            $trRequest['invoiceID'] = $bill->bill_id;
            $trRequest['account_id'] = $chat_id;
            // $trRequest->currency     = $estimatedPriceInDollar;
            // $paymentLink           = $trCryptoCntrl->add_order_crypto_by_nowpayment($trRequest);
            $paymentLink = $trCryptoCntrl->initiateCryptoPayment($trRequest);

            // $nowpaymentLink        = $this->get_nowpayment_payment_link_from_html($paymentLink);

            // format $estimatedPrice to 0 decimal
            $formattedPrice = number_format($estimatedPriceInDollar, 0, ',', '.');
            $text = $this->customTextCtrl->getText('action.process.add_online_balance.dollarpay.nowpayment');
            if (is_array($text)) {
                // use format text service
                $text = $this->telegramService->formatText($text);
            }

            return [
                'text' => $text . " $formattedPrice دلار",
                'url' => $paymentLink,
            ];
        } catch (\Throwable $th) {
            \Log::error("createNowPaymentsLink: " . $th);
            return [];
        }
    }
    public function createCryptomusLink($chat_id, $estimatedPriceInDollar)
    {
        $request = new Request();
        $request->account_id = $chat_id;
        $request->amount = $estimatedPriceInDollar;
        $bill = $this->billCntrl->createNewBillInDollar($request);

        $trCryptoCntrl = new TransactionCryptoController();
        $trRequest = new Request();
        $trRequest['gateway'] = "cryptomus";
        $trRequest['invoiceID'] = $bill->bill_id;
        $trRequest['account_id'] = $chat_id;
        $paymentLink = $trCryptoCntrl->initiateCryptoPayment($trRequest);
        \Log::info("createCryptomusLink: " . $paymentLink);

        $formattedPrice = number_format($estimatedPriceInDollar, 0, ',', '.');
        $text = $this->customTextCtrl->getText('action.process.add_online_balance.dollarpay.cryptomus');
        if (is_array($text)) {
            // use format text service
            $text = $this->telegramService->formatText($text);
        }
        return [
            'text' => $text . " $formattedPrice دلار",
            'url' => $paymentLink,

        ];
    }

    public function createSwapPayLink($chat_id, $estimatedPriceInDollar)
    {
        try {
            $request = new Request();
            $request->account_id = $chat_id;
            $request->amount = $estimatedPriceInDollar;
            $bill = $this->billCntrl->createNewBillInDollar($request);
            if ($bill === null) {
                \Log::error('createSwapPayLink: failed to create bill');

                return [];
            }

            $trCryptoCntrl = new TransactionCryptoController();
            $trRequest = new Request([
                'gateway' => 'swappay',
                'invoiceID' => $bill->bill_id,
                'account_id' => $chat_id,
                'preferred_link' => 'TELEGRAM_BOT',
            ]);
            $paymentLink = $trCryptoCntrl->initiateCryptoPayment($trRequest);
            $paymentUrl = $this->extractCryptoPaymentUrl($paymentLink);
            \Log::info('createSwapPayLink: ' . ($paymentUrl ?? json_encode($paymentLink)));

            if (! TelegramService::isInlineUrlButtonValid($paymentUrl)) {
                $error = $this->extractCryptoPaymentError($paymentLink);

                return [
                    'error' => $error ?: \App\Services\SwapPayService::missingApplicationMessage(),
                ];
            }

            $formattedPrice = $this->formatDollarAmount($estimatedPriceInDollar);
            $text = $this->customTextCtrl->getText('action.process.add_online_balance.dollarpay.swappay');
            if (is_array($text)) {
                $text = $this->telegramService->formatText($text);
            }
            if ($text === null || $text === '' || $text === false) {
                $text = 'پرداخت آنلاین با SwapPay';
            }

            return [
                'text' => $text . " $formattedPrice دلار",
                'url' => $paymentUrl,
            ];
        } catch (\Throwable $th) {
            \Log::error('createSwapPayLink: ' . $th);

            return [];
        }
    }

    public function extractCryptoPaymentUrl(mixed $paymentLink): ?string
    {
        if (is_string($paymentLink)) {
            $url = trim($paymentLink);

            return $url !== '' ? $url : null;
        }

        if ($paymentLink instanceof \Illuminate\Http\JsonResponse) {
            $data = $paymentLink->getData(true);
            if (! is_array($data)) {
                return null;
            }
            foreach (['url', 'payment_url', 'invoice_url', 'pay_url'] as $key) {
                if (! empty($data[$key]) && is_string($data[$key])) {
                    return trim($data[$key]);
                }
            }

            return null;
        }

        if (is_array($paymentLink)) {
            foreach (['url', 'payment_url'] as $key) {
                if (! empty($paymentLink[$key]) && is_string($paymentLink[$key])) {
                    return trim($paymentLink[$key]);
                }
            }
        }

        return null;
    }

    public function extractCryptoPaymentError(mixed $paymentLink): ?string
    {
        if ($paymentLink instanceof \Illuminate\Http\JsonResponse) {
            $data = $paymentLink->getData(true);
            if (is_array($data) && ! empty($data['message']) && is_string($data['message'])) {
                return $data['message'];
            }
        }

        if (is_array($paymentLink) && ! empty($paymentLink['message']) && is_string($paymentLink['message'])) {
            return $paymentLink['message'];
        }

        return null;
    }

    private function formatDollarAmount(mixed $amount): string
    {
        $formatted = number_format((float) $amount, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
    public function getFaqs($chatId, $messageId = null)
    {
        $this->addNewBotLog('faq', 'نمایش سوالات متداول به کاربر.', $chatId, 'show');
        $faqCtrl = new FaqController();
        $faqs = $faqCtrl->getFaqList();
        $opr = [];
        if ($faqs != null) {
            foreach ($faqs as $key => $faq) {
                $opr[] = [
                    $faq->question => "faq-{$faq->id}",
                ];
            }
        }
        $text = $this->customTextCtrl->getText('action.help.faq');

        if ($messageId) {
            $this->telegramService->editMessageWithInlineKeyboard($chatId, $messageId, $text, $opr);
        } else {
            $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
        }
        return "";

    }
    public function subFaq($chatId, $selectedFaqID)
    {
        $this->addNewBotLog('faq', 'نمایش سوالات متداول به کاربر.', $chatId, 'show');
        $faqCtrl = new FaqController();
        $faq = $faqCtrl->getFaqById($selectedFaqID);
        $this->telegramService->sendMessage($chatId, $faq->answer);
        return "";
    }
    public function appDownload($chatId, $messageId = null)
    {
        $appCtrl = new ApplicationController();
        $oses = $appCtrl->getApplicationOSes();
        $opr = [];
        if ($oses != null) {
            foreach ($oses as $key => $os) {
                $opr[] = [
                    $os->os => "subAppDownloadOs-{$os->os}",
                ];
            }
        }
        $text = $this->customTextCtrl->getText('action.help.appDownload.os');

        if ($messageId) {
            $this->telegramService->editMessageWithInlineKeyboard($chatId, $messageId, $text, $opr);
        } else {
            $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
        }
        return "";
    }
    public function subAppDownloadOs($chatId, $selectedOsID, $messageId = null)
    {
        $appCtrl = new ApplicationController();
        $app = $appCtrl->getAllActiveAplicationListByOS($selectedOsID);
        // log count of app
        $opr = [];
        if ($app != null) {
            foreach ($app as $key => $app) {
                $opr[] = [
                    $app->name => "subAppDownloadApp-{$app->id}",
                ];
            }
        }
        $text = $this->customTextCtrl->getText('action.help.appDownload.app');
        if (is_array($text)) {
            // use format text service
            $text = $this->telegramService->formatText($text);
        }

        if ($messageId) {
            $this->telegramService->editMessageWithInlineKeyboard($chatId, $messageId, $text, $opr);
        } else {
            $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
        }
        return "";
    }
    public function subAppDownloadApp($chatId, $selectedAppID, $messageId = null)
    {
        $appCtrl = new ApplicationController();
        $app = $appCtrl->getActiveAplicationByID($selectedAppID);
        $name = $app->name;
        $description = $app->description;
        $download_link = $app->download_link;
        // $file_src = $app->file_src;
        $how_to_use = $app->how_to_use;
        $youtube_link = $app->youtube_link;
        $text = $this->customTextCtrl->getText('action.help.appDownload.app.name_description', [
            'name' => $name,
            'description' => $description,
            'download_link' => $download_link,
            'how_to_use' => $how_to_use,
            'youtube_link' => $youtube_link,
        ]);
        if (is_array($text)) {
            // use format text service
            $text = $this->telegramService->formatText($text);
        }

        if ($messageId) {
            $this->telegramService->editMessageText($chatId, $messageId, $text);
        } else {
            $this->telegramService->sendMessage($chatId, $text);
        }
        return "";
    }
    public function support($chatId)
    {
        $this->addNewBotLog('support', 'نمایش گزینه های پشتیبانی به کاربر.', $chatId, 'show');
        $supportCtrl = new SupportController();
        $supports = $supportCtrl->getSupporstList();
        $opr = [];
        if ($supports != null) {
            foreach ($supports as $key => $support) {
                $opr[] = [
                    $support->question => "support-{$support->id}",
                ];
            }
        }
        $text = $this->customTextCtrl->getText('action.help.support.title');
        if ($opr === []) {
            $this->telegramService->sendMessage($chatId, $text);

            return "";
        }

        $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
        return "";
    }
    public function subSupport($chatId, $supportId)
    {
        $supportCtrl = new SupportController();
        $support = $supportCtrl->getSupportById($supportId);
        $this->telegramService->sendMessage($chatId, $support->answer);
        return "";

    }
    public function testAccount($chatId)
    {
        $this->addNewBotLog('test_account', 'تست اکانت آزمایشی به کاربر.', $chatId, 'show');
        $testAccountCntrl = new TestAccountController();
        $testAccount = $testAccountCntrl->getTestAccountDetails();
        if ($testAccount == null) {
            \Log::error('testAccount: TestAccount config not found', ['chat_id' => $chatId]);
            $text = $this->customTextCtrl->getText('error.server_error');
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }

        $usedTestAccountCntrl = new UsedTestAccountController();
        if ($usedTestAccountCntrl->checkUserHasTestAccount($chatId, $testAccount->id)) {
            $text = $this->customTextCtrl->getText('error.test_account.exist');
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }

        $selectedPrCat = $this->prCatCntrl->getProdctCategoryByCategoryName(TestAccountController::CATEGORY_NAME);
        if ($selectedPrCat == null) {
            $selectedPrCat = $testAccountCntrl->ensureTestProductCategory($testAccount);
        }
        if ($selectedPrCat == null) {
            \Log::error('testAccount: ProductCategory اکانت آزمایشی not found', ['chat_id' => $chatId]);
            $text = $this->customTextCtrl->getText('error.server_error');
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }

        $panelCntrl = new PannelController();
        $pannel = $panelCntrl->getPannelById($testAccount->pannel_id);
        if ($pannel == null) {
            \Log::error('testAccount: panel not found', [
                'chat_id' => $chatId,
                'pannel_id' => $testAccount->pannel_id,
            ]);
            $text = $this->customTextCtrl->getText('error.server_error');
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }

        $day = $testAccount->expire_day;
        $volume = $testAccount->volume;
        $created = false;

        if ($pannel->type == 'hiddify') {
            $created = $this->new_hiddify_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $chatId, $selectedPrCat->id) !== false;
            if ($created) {
                $this->send_using_subscription_manual_message($chatId);
            }
        } elseif ($pannel->type == 'sanaei') {
            $created = $this->new_sanaei_config_telegram_text(
                $selectedPrCat,
                $pannel,
                $volume,
                $day,
                $chatId,
                $selectedPrCat->id
            ) !== false;
        } elseif ($pannel->isMarzbanCompatible()) {
            $mbCtrl = MarzbanPannelController::resolve($pannel);
            $created = $this->new_marzban_config_telegram_text(
                $selectedPrCat,
                $pannel,
                $volume,
                $day,
                $chatId,
                $selectedPrCat->id,
                $mbCtrl->buildTestAccountUsername($chatId),
                $pannel->customTextKey('action.test_account.marzban')
            ) !== false;
            if ($created) {
                $this->send_using_subscription_manual_message($chatId);
            }
        }

        if ($created) {
            $usedTestAccountCntrl->markTestAccountUsed($chatId, $testAccount->id);
            $text = $this->customTextCtrl->getText('action.test_account.success');
            $this->telegramService->sendMessage($chatId, $text);
        } else {
            \Log::error('testAccount: failed to create test account on panel', [
                'chat_id' => $chatId,
                'panel_type' => $pannel->type ?? null,
            ]);
            $text = $this->customTextCtrl->getText('error.server_error');
            $this->telegramService->sendMessage($chatId, $text);
        }

        return "";
    }
    public function giftCard($chatId)
    {
        $text = $this->customTextCtrl->getText('action.help.giftCard');
        $this->telegramService->sendMessage($chatId, $text);
        return "";
    }
    public function subGiftCard($chatId, $giftCard)
    {
        try {
            // بررسی محدودیت تلاش کاربر
            $attemptsCacheKey = "gift_card_attempts_{$chatId}";
            $blockedCacheKey = "gift_card_blocked_{$chatId}";

            // بررسی اینکه آیا کاربر مسدود شده است
            if (Cache::has($blockedCacheKey)) {
                $blockExpiresIn = now()->diffInMinutes(Cache::get($blockedCacheKey));
                $text = $this->customTextCtrl->getText('error.giftCard.too_many_attempts', [
                    'minutes' => $blockExpiresIn,
                ]);
                $this->telegramService->sendMessage($chatId, $text);
                return "";
            }

            // افزایش تعداد تلاش‌ها
            $attempts = Cache::get($attemptsCacheKey, 0) + 1;
            Cache::put($attemptsCacheKey, $attempts, now()->addHour());

            $giftCardCntrl = new GiftCardController();
            $giftCard = $giftCardCntrl->getGiftCardByCode($giftCard);

            if ($giftCard == null) {
                // اگر تعداد تلاش‌ها از حد مجاز بیشتر شد
                if ($attempts >= 3) {
                    Cache::put($blockedCacheKey, now()->addHour(), now()->addHour());
                    Cache::forget($attemptsCacheKey);

                    $text = $this->customTextCtrl->getText('error.giftCard.blocked');
                    $this->telegramService->sendMessage($chatId, $text);
                    return "";
                }

                $text = $this->customTextCtrl->getText('error.giftCard.not_found');
                $this->telegramService->sendMessage($chatId, $text);
                return "";
            }

            // اگر گیفت کارت معتبر بود، کش را پاک می‌کنیم
            Cache::forget($attemptsCacheKey);

            // ادامه منطق موجود
            $usedGiftCntrl = new UsedGiftCardController();
            $userUsedItemCount = $usedGiftCntrl->getCountOfUsePerUser($giftCard->id, $chatId);
            if ($userUsedItemCount >= $giftCard->count_of_use_per_user) {
                $text = $this->customTextCtrl->getText('error.giftCard.already_used');
                $this->telegramService->sendMessage($chatId, $text);
                return "";
            }
            $reualt = $usedGiftCntrl->addGiftCardToUserAccount($giftCard->id, $chatId, $giftCard->code);
            if ($reualt) {
                $text = $this->customTextCtrl->getText('action.help.giftCard.success');
                $this->telegramService->sendMessage($chatId, $text);
                return "";
            }
            $text = $this->customTextCtrl->getText('error.giftCard.already_used');
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        } catch (\Throwable $th) {
            \Log::error("subGiftCard: " . $th);
            $text = $this->customTextCtrl->getText('error.server_error');
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }
    }
    public function referral($chatId)
    {
        try {
            $referralSettingCntrl = new ReferralSettingController();
            if (!$referralSettingCntrl->check_referral_setting_is_active()) {
                $this->telegramService->sendMessage($chatId, 'سیستم بازاریابی در حال حاضر غیرفعال است.');
                return '';
            }

            $text = $this->customTextCtrl->getText('action.referral.title');
            $opr = [];
            $opr[] = [
                $text => "referral-{$chatId}",
            ];
            if (is_array($text)) {
                // use format text service
                $text = $this->telegramService->formatText($text);
            }
            $this->telegramService->sendMessageWithInlineKeyboard($chatId, $text, $opr);
            return "";
        } catch (\Throwable $th) {
            \Log::error("referral: " . $th);
            $text = $this->customTextCtrl->getText('error.server_error');
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }
    }
    public function subReferral($chatId)
    {
        try {
            $referralSettingCntrl = new ReferralSettingController();
            if (!$referralSettingCntrl->check_referral_setting_is_active()) {
                $text = $this->customTextCtrl->getText('error.server_error');
                $this->telegramService->sendMessage($chatId, 'سیستم بازاریابی در حال حاضر غیرفعال است.');
                return '';
            }

            $settingCntrl = new SettingController();
            $botName = $settingCntrl->get_bot_name();
            $inviteUrl = "https://t.me/{$botName}?start={$chatId}";

            $referralPercent = $referralSettingCntrl->get_referral_setting_referral_percent();
            if ($referralPercent == null) {
                $referralPercent = 0;
            }
            $referralPercentStr = \App\Models\ReferralSetting::formatPercentValue($referralPercent);

            $text = $this->customTextCtrl->getText('action.referral.text', [
                'link' => $inviteUrl,
                'percent' => $referralPercentStr,
            ]);
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        } catch (\Throwable $th) {
            \Log::error("subReferral: " . $th);
            $text = $this->customTextCtrl->getText('error.server_error');
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }
    }
    public function block_user_command(string $type, string $chatId, ?string $reason = null)
    {
        try {
            $blockedUserCntrl = new BlockedUserController();
            $text = null;
            if ($type == 'block') {
                $blockedUserCntrl->addBlockedUser(new Request(['accountId' => $chatId, 'reason' => $reason]));
                $this->addNewBotLog($type, "توسط مدیر مسدود شد.", $chatId, 'show');
            } else {
                $blockedUserCntrl->removeBlockedUser(new Request(['accountId' => $chatId]));
                $this->addNewBotLog($type, "رفع مسدودی توسط مدید", $chatId, 'show');
            }
            return "";
        } catch (\Throwable $th) {
            \Log::error("block_user_command: " . $th);
            $text = $this->customTextCtrl->getText('error.server_error');
            $this->telegramService->sendMessage($chatId, $text);
            return "";
        }
    }
    private function addNewBotLog($type, $message, $chatId, $opr)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $chatId, "", $opr);
        return true;
    }

}
