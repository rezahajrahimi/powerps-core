<?php
// https://api.telegram.org/bot380422547:AAH38rivvYZvRnIF6zM-mwZpvqanKJCTclk/setwebhook?url=https://ubuntu.powernad.ir/api/telegram/webhooks/inbound

// https://api.telegram.org/bot7449013530:AAEbAaPDU9AUkyKviA2ffhhuVIswN7iMqNQ/setwebhook?url=https://classic-loved-condor.ngrok-free.apphttps://classic-loved-condor.ngrok-free.app/api/telegram/webhooks/inbound
// https://api.telegram.org/bot6650381860:AAFCJka-B2NsIY5RlATIOQvlXiOpKdDqUlM/setwebhook?url=https://laravel-rq3qi6.chbk.run/api/telegram/webhooks/inbound
// in /start command, why $this->stickyMenu() run twice

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Cache;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\AgentProduct;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Pannel;
use App\Models\BotUser;
use App\Models\User;
use App\Models\AgentPermisson;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Hekmatinasser\Verta\Verta;
use App\Services\PackageButtonLayoutService;

class TelegramController extends Controller
{
    public $buySubscriptionLevel = 1;
    public $buySubSelectTypeLevel = 11;
    public $currentMenuLevel = 0;
    public $userCommandArr = [];
    public $from_id;
    public $text;
    public $referenceCode;
    public $first_name;
    public $caption;
    public $chat_id;
    public $last_name;
    public $username;
    public $message_id;
    public $forward_from_name;
    public $forward_from_id;
    public $callbackId;
    public $data;
    public $chat_type;
    public $markup;
    public $fileId;
    private $stickyMenuCalled = false;

    // public function inbound(Request $request)
    // {
    //     $srtyCtrl = new ServiceTypeController();
    //     $prcaCtrl = new ProductCategoryController();
    //     $prCtrl = new ProductController();
    //     $accBlCtrl = new AccountBallanceController();
    //     $pymCtrl = new PaymentTypeController();
    //     $trnsCtrl = new TransactionController();
    //     $ordCtrl = new OrderController();
    //     $settingCtrl = new SettingController();
    //     $botUserCtrl = new BotUserController();
    //     $menuCntrl = new MenuLevelController();
    //     $channelLockCtrl = new ChannelLockController();
    //     $buySubscriptionLevel = 1;
    //     $servicetypeLevel = 2;
    //     $productCategoryLevel = 3;
    //     // \Log::info($request->all());
    //     try {
    //         try {
    //             if (isset($request->message['photo'])) {
    //                 $this->message_id = $request->message['message_id'];
    //                 $this->chat_id = $request->message['chat']['id'];
    //                 $this->username = $request->message['from']['username'] ?? ' ندارد ';
    //                 $this->from_id = $request->message['from']['id'];
    //                 $this->first_name = $request->message['from']['first_name'] ?? '';
    //                 $this->last_name = $request->message['from']['last_name'] ?? '';
    //                 $this->text = $request->message['caption'] ?? '';

    //                 $text = 'رسید شما دریافت شد، منتظر تایید توسط مدیر باشید.';
    //                 $this->fileId = app('telegram_bot')->getImageId($request->message['photo']);
    //                 $this->chat_type = 'image';
    //                 $result = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

    //                 $chId = $this->chat_id;
    //                 $this->sendMessageToAdmin($this->chat_id, $this->fileId, "کاربر: $chId یک تصوبر ارسال کرد ", 'image');
    //                 $transactionCntrl = new TransactionController();
    //                 $imageTrCntrl = new TransactionImageController();
    //                 $transactionID = $transactionCntrl->addUserTranaction($this->chat_id, 0, '000', 0);
    //                 $request = new Request();
    //                 $request->transaction_id = $transactionID;
    //                 $request->img_src = $this->fileId;
    //                 $request->account_id = $this->chat_id;
    //                 $request->user_text = $this->text ?? 'بدون متن';
    //                 $request->image_url = app('telegram_bot')->getImageUrlByFileID($this->fileId);

    //                 // $imageTrCntrl->addNewTransactionImage($request);
    //                 $imageTrCntrl->saveNewTransactionImage($request);
    //                 \Log::info('oneeeeee');
    //                 return response()->json($result, 200);
    //             } elseif (isset($request->message)) {
    //                 $this->from_id = $request->message['from']['id'];
    //                 $this->text = $request->message['text'];
    //                 $this->first_name = $request->message['from']['first_name'];
    //                 $this->caption = $request->message['caption'] ?? '';
    //                 $this->chat_id = $request->message['chat']['id'] ?? 0;
    //                 $this->last_name = $request->message['from']['last_name'] ?? '';
    //                 $this->username = $request->message['from']['username'] ?? ' ندارد ';
    //                 $this->message_id = $request->message['message_id'];
    //                 $this->forward_from_name = $request->message['reply_to_message']['forward_sender_name'] ?? 0;
    //                 $this->forward_from_id = $request->message['reply_to_message']['forward_from']['id'] ?? 0;
    //                 $this->reply_text = $request->message['reply_to_message']['text'] ?? '0';
    //                 $this->chat_type = 'text';

    //                 $result = $this->recogniseTextMessage();

    //                 \Log::info('two');
    //                 return response()->json($result, 200);
    //             } elseif (isset($request->callback_query)) {
    //                 $this->callbackId = $request->callback_query['id'];
    //                 $this->data = $request->callback_query['data'];
    //                 $this->text = $request->callback_query['message']['text'];
    //                 $this->message_id = $request->callback_query['message']['message_id'];
    //                 $this->chat_id = $request->callback_query['message']['chat']['id'];
    //                 $this->chat_type = $request->callback_query['message']['chat']['type'];
    //                 $this->username = $request->callback_query['from']['username'] ?? ' ندارد ';
    //                 $this->from_id = $request->callback_query['from']['id'];
    //                 $this->first_name = $request->callback_query['from']['first_name'] ?? '';
    //                 $this->last_name = $request->callback_query['from']['last_name'] ?? '';

    //                 $this->markup = json_decode(json_encode($request->callback_query['message']['reply_markup']['inline_keyboard']), true);
    //                 $this->chat_type = 'callback';
    //                 $this->recogniseMessage();
    //                 \Log::info('three');
    //                 $result = $this->changeMenuLevel();
    //                 return response()->json($result, 200);
    //             }
    //             \Log::info('last else');
    //             return $this->stickyMenu();
    //         } catch (\Throwable $th) {
    //             \Log::info("Throwable:  $th");

    //             if (isset($request->callback_query)) {
    //                 $this->callbackId = $request->callback_query['id'];
    //                 $this->data = $request->callback_query['data'];
    //                 $this->text = $request->callback_query['message']['text'];
    //                 $this->message_id = $request->callback_query['message']['message_id'];
    //                 $this->chat_id = $request->callback_query['message']['chat']['id'];
    //                 $this->chat_type = $request->callback_query['message']['chat']['type'];
    //                 $this->username = $request->callback_query['from']['username'] ?? ' ندارد ';
    //                 $this->from_id = $request->callback_query['from']['id'];
    //                 $this->first_name = $request->callback_query['from']['first_name'] ?? '';
    //                 $this->last_name = $request->callback_query['from']['last_name'] ?? '';

    //                 $this->markup = json_decode(json_encode($request->callback_query['message']['reply_markup']['inline_keyboard']), true);
    //                 return $this->recogniseMessage();
    //             }
    //         }

    //         // if (!cache()->has("chat_id_{$this->from_id}") && $this->currentMenuLevel == 0) {
    //         // \Log::info("from_id:  $this->from_id");

    //         if ($botUserCtrl->hasRegistred($this->from_id, $this->username, $this->first_name, $this->last_name) == false) {
    //             $this->text = $settingCtrl->getWelcomeMessage();
    //             $clenedText = $this->prepareText($this->text);
    //             cache()->put("chat_id_{$this->from_id}", true, now()->addMinutes(10));
    //             // app('telegram_bot')->sendMessage($clenedText, $this->chat_id, null, 'MarkDown');
    //             $this->stickyMenuCalled = true; // add this line

    //             return $this->stickyMenu($clenedText);
    //         } else {
    //             //
    //             if (!$this->stickyMenuCalled) {
    //                 // add this check
    //                 $this->stickyMenuCalled = true;
    //                 // return $this->stickyMenu();
    //             }
    //             $channelLock = $this->checkIsChannelsMember($this->from_id);
    //             if ($channelLock == true || $channelLock == 1) {
    //                 $this->changeMenuLevel();
    //             } else {
    //                 return $this->channelLockMenu();
    //             }
    //         }
    //     } catch (\Throwable $th) {
    //         \Log::info("Throwable:  $th");
    //         return $this->stickyMenu();
    //     }
    // }
    // add a nullabe parametr to stickyMenu
    // public function stickyMenu($speceficText = null)
    // {
    //     $menu = new MainMenuItemController();
    //     $menuItem = $menu->getAllActivatedMainMenuItems();
    //     $opr = [];

    //     if ($menuItem[0]->name == 'خرید اشتراک') {
    //         array_push($opr, [['text' => $menuItem[0]->alias_name, 'callback_data' => "main-{$menuItem[0]->id}"]]);
    //         // remove first item from menuItem list because we allreade added it to $opr
    //         $menuItem = $menuItem->slice(1);
    //     }
    //     $countOfMenuItem = count($menuItem);
    //     for ($i = 0; $i < $countOfMenuItem; $i += 2) {
    //         $pair = $menuItem->slice($i, 2);
    //         $index = 1;

    //         foreach ($pair as $key => $value) {
    //             if ($index % 2 == 1) {
    //                 $firstRowIndicator = ['text' => $value->alias_name, 'callback_data' => "main-{$value->id}"];
    //                 $index += 1;
    //             } elseif ($index % 2 == 0) {
    //                 array_push($opr, [$firstRowIndicator, ['text' => $value->alias_name, 'callback_data' => "main-{$value->id}"]]);
    //                 $index += 1;
    //                 break;
    //             }
    //         }
    //     }
    //     // because of if count of menuItem is odd we need to add last row indicator
    //     if ($countOfMenuItem % 2 == 1) {
    //         $lastRowIndicator = ['text' => $menuItem[$countOfMenuItem - 1]->alias_name, 'callback_data' => "main-{$menuItem[$countOfMenuItem - 1]->id}"];
    //         array_push($opr, [$firstRowIndicator]);
    //     }

    //     if (strpos($this->text, '/start') !== false && cache()->has("chat_id_{$this->from_id}") !== 1 && $this->stickyMenuCalled == false) {
    //         \Log::info('/start one');
    //         cache()->put("chat_id_{$this->from_id}", true, now()->addMinutes(10));
    //         $this->stickyMenuCalled = true;
    //         $res = app('telegram_bot')->buttonMessage('یک گزینه را انتخاب کنید.', $opr, $this->chat_id, $this->message_id);
    //         return $res;
    //     } elseif (strpos($this->text, '/start') !== false && $this->stickyMenuCalled == false) {
    //         $this->stickyMenuCalled = true;
    //         \Log::info('/start two');
    //         return app('telegram_bot')->buttonMessage('یک گزینه را انتخاب کنید.', $opr, $this->chat_id, $this->message_id);
    //     }
    //     $this->setNewLevel($this->buySubscriptionLevel);

    //     return app('telegram_bot')->buttonMessage($speceficText, $opr, $this->chat_id, $this->message_id);
    // }
    // public function deleteMessage()
    // {
    //     try {
    //         $result = app('telegram_bot')->deleteMessage($this->chat_id, $this->message_id);
    //     } catch (\Throwable $th) {
    //         \Log::info("Throwable deleteMessage: $th");
    //     }
    // }
    // public function editMessage()
    // {
    //     try {
    //         $result = app('telegram_bot')->editMessage($this->chat_id, $this->message_id);
    //     } catch (\Throwable $th) {
    //         \Log::info("Throwable editMessage: $th");
    //     }
    // }
    // public function recogniseMessage()
    // {
    //     try {
    //         if ($this->chat_type == 'image') {
    //             $result = app('telegram_bot')->imageMessage($image_url, $admin_id, $text);

    //             return response()->json($result, 200);
    //         }

    //         $this->userCommandArr = explode('-', $this->data);

    //         $command = $this->userCommandArr[0];

    //         return $this->userCommandArr;
    //     } catch (\Throwable $th) {
    //         $this->userCommandArr = ['start'];
    //         \Log::info("Throwable $th");

    //         return $this->userCommandArr;
    //     }
    // }
    public function recogniseTextMessage()
    {
        if (strpos($this->text, '/start') !== false) {
            // extract text after /start
            $this->referralCode = substr($this->text, strpos($this->text, '/start') + 6);
            // trim referral code
            $this->referralCode = trim($this->referralCode);
            // save refrence code in database
            $referralLogsCntrl = new ReferralLogsController();
            $botUserCtrl = new BotUserController();

            $botUserCtrl->hasRegistred($this->from_id, $this->username, $this->first_name, $this->last_name);
            $saveRef = $referralLogsCntrl->check_user_has_referral_and_create($this->from_id, $this->referralCode);
        }
        $botUserCtrl = new BotUserController();
        $settingCtrl = new SettingController();
        if ($botUserCtrl->hasRegistred($this->from_id, $this->username, $this->first_name, $this->last_name) == false) {
            $this->text = $settingCtrl->getWelcomeMessage();
            cache()->put("chat_id_{$this->from_id}", true, now()->addMinutes(1000));
            // app('telegram_bot')->sendMessage($this->text, $this->chat_id, null, 'MarkDown');
            $clenedText = $this->prepareText($this->text);
            return $this->stickyMenu($clenedText);
        } else {
            $channelLock = $this->checkIsChannelsMember($this->from_id);
            if ($channelLock == true || $channelLock == 1) {
                $this->changeMenuLevel();
            } else {
                return $this->channelLockMenu();
            }
        }
        try {
            // if ($this->chat_type == 'image') {
            //     $result = app('telegram_bot')->imageMessage($image_url, $admin_id, $text);

            //     return response()->json($result, 200);
            // }
            // check is $this->text start with giftcard-
            // if yes return $this->subGiftCard()

            // check is $this->text start with gift- in upper and lower case
            // if yes return $this->subGiftCard()

            if (str_starts_with(strtolower($this->text), 'giftcard-') || str_starts_with(strtolower($this->text), 'gift-')) {
                return $this->subGiftCard();
            }

            // check is $this->text start with fastcharge
            // if yes return $this->subAdminFastCharge()

            if (str_starts_with(strtolower($this->text), 'charge') !== false) {
                return $this->subAdminFastCharge();
            }

            // check is $this->text start with webapp
            // if yes return $this->webapp()

            $mainMenuCntrl = new MainMenuItemController();
            $checkIsMainMeniItem = $mainMenuCntrl->getMenuNameByAliasName($this->text);
            if ($checkIsMainMeniItem == false) {
                return $this->stickyMenu();
            }
            switch ($checkIsMainMeniItem) {
                case 'منوی اصلی':
                    return $this->subMainMenu();
                    break;
                case 'خرید اشتراک':
                    return $this->buySubscription();
                    break;
                case 'اطلاعات حساب':
                    return $this->accountDetails();
                    break;
                case 'سابقه خرید':
                    return $this->buyHistory();
                    break;
                case 'پشتیبانی':
                    return $this->supports();
                    break;
                case 'آموزش استفاده و سوالات متداول':
                    return $this->faqs();
                    break;
                case 'دانلود برنامه':
                    return $this->appDownload();
                    break;
                case 'گیفت کارت':
                    return $this->giftCard();
                    break;
                case 'اکانت آزمایشی':
                    return $this->testAccount();
                    break;
                case 'webapp':
                    return $this->subWebapp();
                    break;
                case 'کسب درآمد':
                    return $this->referral();
                    break;
                case 'خرید گیفت کارت':
                    return $this->buyGiftCard();
                    break;

                default:
                    return $this->stickyMenu();
                    break;
            }

            return $this->stickyMenu();
        } catch (\Throwable $th) {
            $this->userCommandArr = ['start'];
            \Log::info("Throwable $th");

            return $this->userCommandArr;
        }
    }
    public function changeMenuLevel()
    {
        if ($this->currentMenuLevel != 0) {
            $this->currentMenuLevel -= 1;
        }

        $this->userText = '';
        $menuCntrl = new MenuLevelController();

        $menuCntrl->newUserLevel($this->chat_id, $this->currentMenuLevel);
        if ($this->userCommandArr == null) {
            $this->userCommandArr = ['start'];
        }

        switch ($this->userCommandArr[0]) {
            case 'main':
                return $this->subMainMenu();
                break;
            case 'buySubscription':
                return $this->subBuySubscription();
                break;
            case 'buySubscriptionByLocation':
                return $this->subBuySubscriptionByLocation();
                break;
            case 'addAccountBalance':
                return $this->addAccountBalance();
                break;
            case 'subAccountBalance':
                return $this->subAccountBalance();
                break;
            case 'subBuyHistory':
                return $this->subBuyHistory();
                break;
            case 'subSupport':
                return $this->subSupport();
                break;
            case 'subFaq':
                return $this->subFaq();
                break;
            case 'subAppDownload':
                return $this->subAppDownload();
                break;
            case 'getAppDownload':
                return $this->getAppDownload();
                break;
            case 'giftcard':
                return $this->subGiftCard();
                break;
            case 'recharge':
                return $this->subRecharge();
                break;
            case 'subReferral':
                return $this->subReferral();
                break;
            case 'subFaq':
                return $this->subFaq();
                break;
            case 'help':
                return $this->help();
                break;
            default:
                return $this->stickyMenu();
                break;
        }

        return $this->stickyMenu();
    }
    public function subMainMenu()
    {
        $this->addNewBotLog('menu', 'وارد منوی اصلی ربات شد.', 'show');

        $menu = new MainMenuItemController();

        $selectedSubMenu = $menu->getMenuNameByID($this->userCommandArr[1]);
        \Log::info("selectedSubMenu:  $selectedSubMenu->name");

        switch ($selectedSubMenu->name) {
            case 'خرید اشتراک':
                return $this->buySubscription();
                break;
            case 'سابقه خرید':
                return $this->buyHistory();
                break;
            case 'پشتیبانی':
                $this->supports();
                break;
            case 'آموزش استفاده و سوالات متداول':
                return $this->faqs();
                break;

            case 'اطلاعات حساب':
                return $this->accountDetails();
                break;
            // case "اکانت تستی":
            // $this->buySubscription();
            //     break;

            default:
                return $this->stickyMenu();
                break;
        }
        return;
    }
    public function help()
    {
        switch ($this->userCommandArr[1]) {
            case 'faqs':
                return $this->faqs();
                break;
            case 'appDownload':
                return $this->appDownload();
                break;
            // case "اکانت تستی":
            // $this->buySubscription();
            //     break;

            default:
                $this->stickyMenu();
                break;
        }
        return;
    }
    public function setNewLevel($level)
    {
        $menlvCtrl = new MenuLevelController();
        $menlvCtrl->newUserLevel($this->chat_id, $level);
    }
    public function buySubscription()
    {
        // $this->deleteMessage();
        $this->addNewBotLog('subscription', 'وارد بخش خرید اشتراک شد.', 'show');
        // check has flag for show configs by panels category in advanced setting or not
        $advancedSettingCntrl = new AdvanceSettingLookupController();
        $hasShowConfigByPanelCategory = $advancedSettingCntrl->getValueByNameWithBooleanValue('bot_show_configs_by_panels_category');
        if ($hasShowConfigByPanelCategory == true || $hasShowConfigByPanelCategory == 1) {
            // get panels locations
            $panelCntrl = new PannelController();
            $panels = $panelCntrl->get_all_panells_by_location_capacity_mode();
            // $panels = $panelCntrl->get_all_pannels_locations();
            $text = 'مکان سرور را انتخاب کنید.';
            $opr = [];
            $index = 0;
            foreach ($panels as $key => $value) {
                array_push($opr, [['text' => $value, 'callback_data' => 'buySubscriptionByLocation-' . $value]]);
                $index++;
            }
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            // $result = app('telegram_bot')->editMessageReplyMarkup( $this->chat_id,$this->message_id,$opr,);
            $this->setNewLevel($this->buySubscriptionLevel);
            return response()->json($result, 200);
        }
        //
        $text = 'بسته خود را انتخاب کنید.';
        $prCatCntrl = new ProductCategoryController();

        $prCat = $prCatCntrl->getAllActiveProdctCategoryOrderByPrice();
        $selection = $this->buildPackageSelectionLegacyMessage($prCat, $text);
        $result = app('telegram_bot')->commandMessage($selection['opr'], $this->chat_id, $selection['text']);
        // $result = app('telegram_bot')->editMessageReplyMarkup( $this->chat_id,$this->message_id,$opr,);
        $this->setNewLevel($this->buySubscriptionLevel);
        return response()->json($result, 200);
    }
    public function subBuySubscriptionByLocation()
    {
        $location = $this->userCommandArr[1];
        // get panel id by location
        $panelCntrl = new PannelController();
        $panelId = $panelCntrl->get_pannel_id_by_location($location);
        $text = 'بسته خود را انتخاب کنید.';
        $prCatCntrl = new ProductCategoryController();

        $prCat = $prCatCntrl->get_all_active_prodct_category_by_pannel_id_order_by_price($panelId);
        $selection = $this->buildPackageSelectionLegacyMessage($prCat, $text);
        $result = app('telegram_bot')->commandMessage($selection['opr'], $this->chat_id, $selection['text']);
        // $result = app('telegram_bot')->editMessageReplyMarkup( $this->chat_id,$this->message_id,$opr,);
        $this->setNewLevel($this->buySubscriptionLevel);
        return response()->json($result, 200);
    }
    public function subBuySubscription()
    {
        $prCat = new ProductCategoryController();

        $id = $this->userCommandArr[1];
        \Log::info("userCommandArr: $id");

        $selectedPrCat = $prCat->getProdctCategoryNameByID($this->userCommandArr[1]);
        $this->addNewBotLog('subscription', "بسته $selectedPrCat->category_name را انتخاب کرد.", 'buy subscription');

        // check user account balance
        $productPrice = $selectedPrCat->price;
        $productPriceInDollar = $selectedPrCat->price_in_dollar;
        \Log::info("selectedPrCat->price: $productPrice");
        $opr = [];
        $accBlCtrl = new AccountBallanceController();
        $prCntrl = new ProductController();
        $hasBallance = false;
        $hasBallance = $accBlCtrl->checkUserHasBalance($this->chat_id, $productPrice, $productPriceInDollar);

        // check ref wallet
        $referalCntrl = new ReferralWalletController();
        $referralAmount = $referalCntrl->get_amount_of_ref_wallet_by_account_id($this->chat_id);

        $hasRefballance = false;
        if ($referralAmount >= $productPrice) {
            $hasRefballance = true;
        }

        if ($hasRefballance == true || $hasBallance == true) {
            // $userAccouintBallance = $accBlCtrl->getUserAccuntBalance($this->chat_id);

            // check pannel type
            $pnlCntrl = new PannelController();
            $pannel = $pnlCntrl->getPannelById($selectedPrCat->pannel_id);
            // get selected item specefic data
            $day = $selectedPrCat->expire_day;
            $volume = $selectedPrCat->volume;
            $productID = $prCntrl->getLastInsertedProductId();
            $productID += 1;

            if ($pannel->type == 'hiddify') {
                $generalCntrl = new GeneralController();
                $resualt = $generalCntrl->new_hiddify_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $this->chat_id, $productID);

            } elseif ($pannel->isMarzbanCompatible()) {
                $generalCntrl = new GeneralController();
                $resualt = $generalCntrl->new_marzban_config_telegram_text(
                    $selectedPrCat,
                    $pannel,
                    $volume,
                    $day,
                    $this->chat_id,
                    $productID
                );
            } else {
                $userData = $prCntrl->getProductConfigAndChangeStatus($selectedPrCat->id, $this->chat_id);
                // $pannelLink = $userData["panel_link"];

                $text = '';
                $text .= "خرید شما با موفقیت انجام شد\r\n";
                if ($userData->panel_link != null) {
                    $pannel = $userData->panel_link;
                    $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:$pannel\r\n";
                }
                if ($userData->subscription_link != null) {
                    $userSub = $userData->subscription_link;
                    $text .= "لینک سابسکریپشن: $userSub \r\n";
                    $image = $pnlCntrl->generateQrMOC($userSub);
                    $text .= "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";

                    $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                    $text = '';
                }
                if ($selectedPrCat->shouldSendConfigToUser() && $userData->configs != null) {
                    $configLinks = ProductCategory::extractConfigLinks($userData->configs);
                    if (! empty($configLinks)) {
                        foreach ($configLinks as $link) {
                            $image = $pnlCntrl->generateQrMOC($link);

                            $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $link);
                        }
                        $text = '';
                    } elseif (is_string($userData->configs) && $userData->configs !== '') {
                        $text .= "کانفیگ: \r\n";
                        $text .= "$userData->configs \r\n";
                    }
                }
                $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
                $this->addNewBotLog('subscription', 'خرید یسته با موفقیت انجام شد.', 'successfull buy subscription');

                // $text .= "لینک سابسکریپشن: $userSubscriptionLInk \r\n";
                $text = "جهت نیاز به راهنمایی بر روی یکی از این گزینه ها کلیک کنید. \r\n";
                $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            }

            // minus balance
            if ($hasBallance == true) {
                $accBlCtrl->decUserAccuntBalance($this->chat_id, $productPrice, $productPriceInDollar);
                $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
            } else {
                $referalCntrl->dec_user_ref_wallet_ballance($this->chat_id, $productPrice);
                $this->addNewBotLog('ballance', "مبلغ  $productPrice را از کیف پول همکاری شما بابت شارژ بسته کم شد.", 'minus ballance');
            }

            // send how to use
            $opr = [];
            array_push($opr, [
                [
                    'text' => 'آموزش استفاده',
                    'callback_data' => 'help-faqs',
                ],
            ]);
            array_push($opr, [
                [
                    'text' => 'برنامه های مورد نیاز',
                    'callback_data' => 'help-appDownload',
                ],
            ]);
            $text = 'یک گزینه را انتخاب کنید.';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

            return response()->json($result, 200);
        } else {
            $userAccouintBallance = $accBlCtrl->getUserAccuntBalance($this->chat_id);
            $userAccouintBallanceInDollar = $accBlCtrl->getUserAccuntBalanceInDollar($this->chat_id);

            // get item price
            $estimatedPrice = $productPrice - $userAccouintBallance;
            // calculate estimated price in dollar
            $estimatedPriceInDollar = $productPriceInDollar - $userAccouintBallanceInDollar;

            // create link
            $text = "موجودی شما کم تر از قیمت بسته انتخابی می باشد. لطفا حساب خود را شارژ بفرمایید. \r\n";
            $text .= "موجودی حساب شما: $userAccouintBallance تومان  \r\n";
            $text .= "موجودی مورد نیاز: $productPrice تومان  \r\n";
            $text .= "میزان مبلغ مورد نیاز برای شارژ حساب: $estimatedPrice تومان  \r\n";

            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            $botGeneralCntrl = new BotGeneralController();
            $result = $botGeneralCntrl->increase_account_ballance_menu_on_low_balance($this->chat_id, $estimatedPrice, $estimatedPriceInDollar);

            $this->addNewBotLog('subscription', 'موجودی کیف پول کاربر برای حرید بسته کافی نبود.', 'low account ballance');

            return response()->json($result, 200);
        }
    }
    public function addAccountBalance()
    {
        $this->addNewBotLog('ballance', 'گزینه های شارژ حساب به کاربر نمایش داده شد.', 'show');

        $text = 'نوع پرداخت را انتخاب کنید.';
        $pymCntrl = new PaymentTypeController();

        $opr = [];

        $hasZarinPal = $pymCntrl->getZarinpalStatus();

        if ($hasZarinPal == true) {
            array_push($opr, [['text' => 'پرداخت آنلاین', 'callback_data' => 'subAccountBalance-zarinpal']]);
        }
        // add nowpayment if was active
        if ($this->checkDollarPay() == true || $this->checkDollarPay() == 1) {
            $cryptoCntrl = new CryptoPaymentController();
            $nowpayment = $cryptoCntrl->getNowPaymentsStatus();
            if ($nowpayment == true) {
                array_push($opr, [['text' => 'پرداخت آنلاین با ارز دیجیتال', 'callback_data' => 'subAccountBalance-nowpayment']]);
            }
        }

        // add offline payment
        $offlinePayment = $pymCntrl->getAllActiveOfflinePaymentTypes();
        $index = 0;

        foreach ($offlinePayment as $key => $value) {
            \Log::info("offlinePayment:$value->name");
            array_push($opr, [['text' => "$value->name", 'callback_data' => "subAccountBalance-$value->name "]]);
        }

        $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
        // $result = app('telegram_bot')->editMessageReplyMarkup( $this->chat_id,$this->message_id,$opr,);
        $this->setNewLevel($this->buySubscriptionLevel);
        return response()->json($result, 200);
    }
    public function subAccountBalance()
    {
        $pymCntrl = new PaymentTypeController();

        if ($this->userCommandArr[1] == 'zarinpal') {
            // check if $this->userCommandArr[] lenght

            if (count($this->userCommandArr) >= 3 && is_numeric($this->userCommandArr[2])) {
                $amount = $this->userCommandArr[2];

                $request = new Request();

                $request->account_id = $this->chat_id;
                $request->amount = $amount;
                $billCntrl = new BillController();

                $bill = $billCntrl->createNewBill($request);

                /////
                $trCntrl = new TransactionController();
                $trRequest = new Request();
                $trRequest->invoiceID = $bill->bill_id;
                $trRequest->account_id = $this->chat_id;
                $trRequest->amount = $amount;
                $paymentLink = $trCntrl->add_order($trRequest);

                // $generalCntrl = new GeneralController();
                // $zarinPal = $generalCntrl->get_zarinpal_payment_link_from_html($paymentLink);

                //

                // $openLink = $pymCntrl->getZarinpalLink();
                $text = "⚠️پس از پرداخت 5 دقیقه صبر کنید تا حسابتان شارژ شود، در صورت شارژ نشدن حساب به پشتیبانی پیام دهید.

- بهتر است از مرورگر داخلی تلگرام استفاده نکنید و از مرورگر خارج تلگرام مثل کروم استفاده کنید.

- در هنگام پرداخت vpn خاموش باشد.

- 10 دقیقه زمان برای پرداخت دارید پس خواهشا در این تایم پرداختتان رو تکمیل کنید در غیر اینصورت باید منتظر لینک جدید بمانید.\r\n";

                $opr = [];
                array_push($opr, [
                    [
                        'text' => "پرداخت آنلاین $amount تومان",
                        'url' => "$paymentLink",
                    ],
                ]);
                // array_push($opr, [
                //     [
                //         'text' => 'پرداخت آنلاین',
                //         'url' => "$openLink/$this->chat_id/$bill->bill_id/$bill->amount",
                //     ],
                // ]);
                $this->addNewBotLog('ballance', "صورتحساب به مبلغ $amount برای پرداهت از طریق درگاه زرین پال برای کاربر ارسال شد.", 'show');

                $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $this->chat_id, '');
            } else {
                $text = 'میزان افزایش اعتبار را انتخاب کنید.';
                $opr = [];
                array_push($opr, [['text' => '10 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-10000 '], ['text' => '15 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-15000 ']]);
                array_push($opr, [['text' => '30 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-30000 '], ['text' => '50 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-50000 ']]);
                array_push($opr, [['text' => '90 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-90000 '], ['text' => '100 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-100000 ']]);
                array_push($opr, [['text' => '150 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-150000 '], ['text' => '180 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-180000 ']]);
                array_push($opr, [['text' => '300 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-300000 '], ['text' => '500 هزار تومان', 'callback_data' => 'subAccountBalance-zarinpal-500000 ']]);

                $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
                // $this->setNewLevel($this->addZarinPalBalanceLevel);
                $this->addNewBotLog('ballance', 'مبالغ مورد نیاز برای پرداخت از طریق درگاه زرین پال برای کاربر ارسال شد.', 'show');

                return response()->json($result, 200);
            }
        } elseif ($this->userCommandArr[1] == 'nowpayment') {
            if (count($this->userCommandArr) >= 3 && is_numeric($this->userCommandArr[2])) {
                $amount = $this->userCommandArr[2];

                $request = new Request();

                $request->account_id = $this->chat_id;
                $request->amount = $amount;
                $billCntrl = new BillController();

                $bill = $billCntrl->createNewBillInDollar($request);

                $openLink = $pymCntrl->getNowPaymentsLink();
                ///
                $trCryptoCntrl = new TransactionCryptoController();
                $trRequest = new Request();
                $trRequest->invoiceID = $bill->bill_id;
                $trRequest->account_id = $this->chat_id;
                $trRequest->amount = $amount;
                $paymentLink = $trCryptoCntrl->add_order_crypto_by_nowpayment($trRequest);

                $generalCntrl = new GeneralController();
                $nowpaymentLink = $generalCntrl->get_nowpayment_payment_link_from_html($paymentLink);

                // ///
                $text = "پرداخت مبلغ $amount دلار از طریق درگاه آنلاین \r\n";
                $opr = [];
                array_push($opr, [
                    [
                        'text' => 'پرداخت آنلاین',
                        'url' => "$nowpaymentLink",
                    ],
                ]);
                // array_push($opr, [
                //     [
                //         'text' => 'پرداخت آنلاین',
                //         'url' => "$openLink/$this->chat_id/$bill->bill_id/$amount",
                //     ],
                // ]);
                $this->addNewBotLog('ballance', "صورتحساب به مبلغ $amount برای پرداهت از طریق درگاه زرین پال برای کاربر ارسال شد.", 'show');

                $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $this->chat_id, '');
            } else {
                $text = 'میزان افزایش اعتبار را انتخاب کنید.';
                $opr = [];
                array_push($opr, [['text' => '5$', 'callback_data' => 'subAccountBalance-nowpayment-5 '], ['text' => '7$', 'callback_data' => 'subAccountBalance-nowpayment-7 ']]);
                array_push($opr, [['text' => '10$', 'callback_data' => 'subAccountBalance-nowpayment-10 '], ['text' => '12$', 'callback_data' => 'subAccountBalance-nowpayment-12 ']]);
                array_push($opr, [['text' => '15$', 'callback_data' => 'subAccountBalance-nowpayment-15 '], ['text' => '20$', 'callback_data' => 'subAccountBalance-nowpayment-20 ']]);
                array_push($opr, [['text' => '50$', 'callback_data' => 'subAccountBalance-nowpayment-50 '], ['text' => '150$', 'callback_data' => 'subAccountBalance-nowpayment-150 ']]);
                array_push($opr, [['text' => '200$', 'callback_data' => 'subAccountBalance-nowpayment-200 '], ['text' => '300$', 'callback_data' => 'subAccountBalance-nowpayment-300 ']]);

                $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
                // $this->setNewLevel($this->addZarinPalBalanceLevel);
                $this->addNewBotLog('ballance', 'مبالغ مورد نیاز برای پرداخت از طریق درگاه nowpayments برای کاربر ارسال شد.', 'show');

                return response()->json($result, 200);
            }
        } else {
            $pymCntrl = new PaymentTypeController();
            $pymentMenuCntrl = new PaymentMenuItemController();
            app('telegram_bot')->sendMessage($pymentMenuCntrl->getResponseOfSelectedOfflineMenu(), $this->chat_id, null, 'MarkDown');

            $name = $this->userCommandArr[1];
            $selectedPayment = $pymCntrl->get_payment_type_by_name($this->userCommandArr[1]);
            $merchent_id = $selectedPayment->merchant_id;
            $retrun_text = "<code>{$merchent_id}</code>";
            $result = app('telegram_bot')->sendMessage($retrun_text, $this->chat_id, null, 'HTML');
            // log resualt as a array
            \Log::info("result: " . json_encode($result));
            $this->addNewBotLog('ballance', 'مشخصات پرداخت آفلاین انتخابی به کاربر نمایش داده شد.', 'show');

            return response()->json($result, 200);
        }
    }
    public function checkIsChannelsMember($chat_id)
    {
        $this->addNewBotLog('lock', 'بررسی عضو بودن کاربر در کانالهای قفل ربات', 'check');

        $channelLockCtrl = new ChannelLockController();
        $channels = $channelLockCtrl->getAllActiveChannelLock();
        $response = true;
        foreach ($channels as $channel) {
            $channel_name = $channel->channel_id;
            // check $chanel start with @ char
            if (!preg_match('/^@/', $channel_name)) {
                $channel_name = "@$channel_name";
            }

            $res = app('telegram_bot')->checkMember($channel_name, $chat_id);
            if ($res == false || $res == null) {
                return $response = false;
            } else {
                return $response = true;
            }
        }
        return $response;
    }
    public function channelLockMenu()
    {
        $this->addNewBotLog('lock', 'درخواست از کاربر برای عضویت در کانالهای قفل ربات.', 'show');

        $channelLockCtrl = new ChannelLockController();
        $channels = $channelLockCtrl->getAllActiveChannelLock();
        $opr = [];

        foreach ($channels as $channel => $value) {
            array_push($opr, [
                [
                    'text' => "$value->channel_id",
                    'url' => "https://t.me/$value->channel_id",
                ],
            ]);
        }
        // add /start command
        // array_push($opr, [
        //     [
        //         'text' => "عضو شدم",
        //         'callback_data' => 'start',
        //     ]
        //     ]);

        $channelLockMenuCtrl = new ChannelLockMenuItemController();

        $text = $channelLockMenuCtrl->getChannelLockMenuText();

        $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $this->chat_id, '');
        return response()->json($result, 200);
    }
    public function buyHistory()
    {
        $prCtrl = new ProductController();
        $histories = $prCtrl->getUserProductsHistoryByAccountID($this->chat_id);
        $opr = [];
        if ($histories != null) {
            foreach ($histories as $key => $history) {
                if ($history['product_category'] != null) {
                    $catName = $history->product_category->category_name;
                    $catName .= ' | ' . $history->remark;
                    // remove charecter '-' from $catName
                    $catName = str_replace('-', ' ', $catName);

                    array_push($opr, [
                        [
                            'text' => "$catName",
                            'callback_data' => 'subBuyHistory-' . $history->id,
                        ],
                    ]);
                }
            }
            $text = 'تاریخچه خرید شما:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            $this->addNewBotLog('history', 'نمایش گزینه های تاریخچه خرید کاربر.', 'show');

            return response()->json($result, 200);
        }
    }
    public function subBuyHistory()
    {
        // check is userCommandArr[1] an integer or not
        try {
            $selectedHistoryID = $this->userCommandArr[1];
            $text = "ایتم انتخابی: $selectedHistoryID";
            $prCtrl = new ProductController();
            $prCatCntrl = new ProductCategoryController();
            $pnlCntrl = new PannelController();

            $selectedProduct = $prCtrl->getProductConfigById($selectedHistoryID, $this->chat_id);
            $selectedProductCategory = $prCatCntrl->getProdctCategoryNameByID($selectedProduct->product_categories_id);
            $pannel = $pnlCntrl->getPannelById($selectedProductCategory->pannel_id);
            $this->addNewBotLog('history', 'نمایش اطلاعات تاریخچه خرید انتخابی به کاربر', 'show');

            // check pannel type
            if ($pannel->type == 'hiddify') {
                $generalCntrl = new GeneralController();
                $resualt = $generalCntrl->return_exist_hiddify_config_telegram_text($selectedProduct, $selectedProductCategory, $pannel, $this->chat_id);

            } else {
                if ($selectedProduct->panel_link != null) {
                    $panel_link = $selectedProduct->panel_link;
                    $text = '';
                    $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده: $selectedProduct->panel_link \r\n";
                    $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
                }

                if ($selectedProduct->subscription_link != null) {
                    $userSubscriptionLInk = $selectedProduct->subscription_link;
                    $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
                    $text = '';
                    $text .= "لینک subscription: $selectedProduct->subscription_link \r\n";
                    $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                }

                if ($selectedProductCategory->shouldSendConfigToUser()) {
                    $configLinks = ProductCategory::extractConfigLinks($selectedProduct->configs);
                    if (! empty($configLinks)) {
                        foreach ($configLinks as $link) {
                            $text = $link;
                            $image = $pnlCntrl->generateQrMOC($text);

                            $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                        }
                    } elseif (is_string($selectedProduct->configs) && $selectedProduct->configs !== '') {
                        $text = "$selectedProduct->configs \r\n";
                        $image = $pnlCntrl->generateQrMOC($selectedProduct->configs);

                        $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
                    }
                }
            }
            // sent data by pannel type

            // check is enough volume or  time or not
            // send how to use
            $opr = [];
            // $selectedHistoryID = $this->userCommandArr[1];

            array_push($opr, [
                [
                    'text' => 'تمدید بسته',
                    'callback_data' => "recharge-{$selectedHistoryID}",
                ],
            ]);

            array_push($opr, [
                [
                    'text' => 'آموزش استفاده',
                    'callback_data' => 'help-faqs',
                ],
            ]);
            array_push($opr, [
                [
                    'text' => 'برنامه های مورد نیاز',
                    'callback_data' => 'help-appDownload',
                ],
            ]);

            // set back buttun
            $mainCntrl = new MainMenuItemController();
            $menuItem = $mainCntrl->getMenuIdByName('سابقه خرید');

            array_push($opr, [
                [
                    'text' => 'بازگشت به سابقه خرید',
                    'callback_data' => "main-{$menuItem->id}",
                ],
            ]);

            $text = 'یک گزینه را انتخاب کنید.';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

            return response()->json($result, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
            return null;
        }
    }
    public function subRecharge()
    {
        // check is userCommandArr[1] an integer or not
        try {
            // $this->deleteMessage();
            $selectedHistoryID = $this->userCommandArr[1];
            $text = '';
            $data = Product::where('id', $selectedHistoryID)->with('product_category_and_panel')->first();
            $accountID = $this->chat_id;

            $selectedPrCat = ProductCategory::find($data->product_categories_id);
            // check selectedPrCat is اکانت آزمایشی or not
            if ($selectedPrCat->category_name == 'اکانت آزمایشی' || $selectedPrCat->is_active == false) {
                $text .= "این بسته قابلیت شارژ ندارد \r\n";
                $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
                return $resualt;
            }

            // check account ballance
            $productPrice = $selectedPrCat->price;
            $productPriceInDollar = $selectedPrCat->price_in_dollar;
            $accBlCtrl = new AccountBallanceController();
            $hasBallance = false;
            $hasBallance = $accBlCtrl->checkUserHasBalance($this->chat_id, $productPrice, $productPriceInDollar);

            // check ref wallet
            $referalCntrl = new ReferralWalletController();
            $referralAmount = $referalCntrl->get_amount_of_ref_wallet_by_account_id($this->chat_id);

            $hasRefballance = false;
            if ($referralAmount >= $productPrice) {
                $hasRefballance = true;
            }

            if ($hasRefballance == true || $hasBallance == true) {
                $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

                // check pannel type
                $hiddifcCntrl = new HiddifyPannelController();

                $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
                $day = $selectedPrCat->expire_day;
                $volume = $selectedPrCat->volume;

                $req = new Request();
                $req->pannelID = $pannel->id;
                $req->name = $data->remark;
                $req->uuid = $uuid;
                $req->vol = $volume;
                $req->day = $day;
                // get today date with new variable
                $today = Verta::now();
                $req->comment = "شارژ مجدد در {$today}";

                $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelApi($req);
                if ($hiddifcCntrl->hiddifyMutationSucceeded($updateRemark)) {
                    if ($hasBallance == true) {
                        $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                        $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                    } else {
                        $referalCntrl->dec_user_ref_wallet_ballance($accountID, $productPrice);
                        $this->addNewBotLog('ballance', "مبلغ  $productPrice را از کیف پول همکاری شما بابت شارژ بسته کم شد.", 'minus ballance');
                    }
                    $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');

                    $text .= "✅شارژ با موفقیت انجام شد✅ \r\n";
                    $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

                    return $resualt;
                    // dd($subsequentResponse);
                } else {
                    $text .= "خطایی رخ داد، دوباره امتحان کنید یا با پشتیبانی تماس بگیرید\r\n";
                    $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

                    return $resualt;
                }

                $opr = [];

                array_push($opr, [
                    [
                        'text' => 'آموزش استفاده',
                        'callback_data' => 'help-faqs',
                    ],
                ]);
                array_push($opr, [
                    [
                        'text' => 'برنامه های مورد نیاز',
                        'callback_data' => 'help-appDownload',
                    ],
                ]);

                // set back buttun
                $mainCntrl = new MainMenuItemController();
                $menuItem = $mainCntrl->getMenuIdByName('سابقه خرید');

                array_push($opr, [
                    [
                        'text' => 'بازگشت به سابقه خرید',
                        'callback_data' => "main-{$menuItem->id}",
                    ],
                ]);
                $text = 'یک گزینه را انتخاب کنید.';
                $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

                return response()->json($resualt, 200);
            } else {
                $userAccouintBallance = $accBlCtrl->getUserAccuntBalance($this->chat_id);
                $userAccouintBallanceInDollar = $accBlCtrl->getUserAccuntBalanceInDollar($this->chat_id);
                // get item price
                $estimatedPrice = $productPrice - $userAccouintBallance;
                // calculate estimated price in dollar
                $estimatedPriceInDollar = $productPriceInDollar - $userAccouintBallanceInDollar;

                // create link
                $text = "موجودی شما کم تر از قیمت بسته انتخابی می باشد. لطفا حساب خود را شارژ بفرمایید. \r\n";
                $text .= "موجودی حساب شما: $userAccouintBallance تومان  \r\n";
                $text .= "موجودی مورد نیاز: $productPrice تومان  \r\n";
                $text .= "میزان مبلغ مورد نیاز برای شارژ حساب: $estimatedPrice تومان  \r\n";

                // $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
                $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

                $botGeneralCntrl = new BotGeneralController();
                $result = $botGeneralCntrl->increase_account_ballance_menu_on_low_balance($this->chat_id, $estimatedPrice, $estimatedPriceInDollar);

                $this->addNewBotLog('subscription', 'موجودی کیف پول کاربر برای حرید بسته کافی نبود.', 'low account ballance');

                return response()->json($result, 200);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }
    public function subWebapp()
    {
        $this->addNewBotLog('webapp', 'ارسال لینک ورود سریع به پنل به کاربر', 'show');
        $authCntrl = new AuthController();
        $req = new Request();
        $req->account_id = $this->chat_id;
        $result = $authCntrl->generate_auto_login_link($req);
        return response()->json($result, 200);
    }
    public function supports()
    {
        $this->addNewBotLog('support', 'نمایش گزینه های پشتیبانی به کاربر.', 'show');

        $supportCtrl = new SupportController();
        $supports = $supportCtrl->getSupporstList();
        $opr = [];
        if ($supports != null) {
            foreach ($supports as $key => $support) {
                if ($support['question'] != null) {
                    $question = $support->question;
                    // remove charecter '-' from $catName
                    $catName = str_replace('-', ' ', $question);

                    array_push($opr, [
                        [
                            'text' => "$question",
                            'callback_data' => 'subSupport-' . $support->id,
                        ],
                    ]);
                }
            }
            // array_push($opr, [
            //     [
            //         'text' => 'بازگشت به منوی اصلی',
            //         'callback_data' => '0',
            //     ],
            // ]);
            $text = 'یک گزینه را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }

    public function subSupport()
    {
        $this->addNewBotLog('support', 'نمایش جزییات گزینه انتخابی پشتیبانی به کاربر.', 'show');

        $selectedSupportID = $this->userCommandArr[1];
        \Log::info("selectedSupportID:$selectedSupportID");
        $text = '';

        $supportCtrl = new SupportController();
        $supports = $supportCtrl->getSupportById($selectedSupportID);
        $opr = [];
        if ($supports != null) {
            $text = $supports->question . "\r\n";

            $text = $supports->answer;

            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            // set back buttun
            $mainCntrl = new MainMenuItemController();
            $menuItem = $mainCntrl->getMenuIdByName('پشتیبانی');

            array_push($opr, [
                [
                    'text' => "بازگشت به {$menuItem->alias_name}",
                    'callback_data' => "main-{$menuItem->id}",
                ],
            ]);
            // array_push($opr, [
            //     [
            //         'text' => 'بازگشت به منوی اصلی',
            //         'callback_data' => '0',
            //     ],
            // ]);
            $text = 'یک گزینه را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }
    public function faqs($chatId)
    {
        $this->addNewBotLog('faq', 'نمایش گزینه های سوالات متدوال به کاربر.', 'show');

        $faqCtrl = new FaqController();
        $faqs = $faqCtrl->getFaqList();
        $opr = [];
        if ($faqs != null) {
            foreach ($faqs as $key => $faq) {
                if ($faq['question'] != null) {
                    $question = $faq->question;
                    // remove charecter '-' from $catName
                    $catName = str_replace('-', ' ', $question);

                    array_push($opr, [
                        [
                            'text' => "$question",
                            'callback_data' => 'subFaq-' . $faq->id,
                        ],
                    ]);
                }
            }

            $text = 'یک گزینه را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }
    public function subFaq()
    {
        $this->addNewBotLog('faq', 'نمایش جزییات گزینه انتخابی سوالات متداول به کاربر.', 'show');

        $selectedFaqID = $this->userCommandArr[1];
        // \Log::info("selectedFaqID:$selectedFaqID");
        $text = '';

        $supportCtrl = new FaqController();
        $supports = $supportCtrl->getFacById($selectedFaqID);
        $opr = [];
        if ($supports != null) {
            $text = $supports->question . "\r\n";

            $text = $supports->answer;

            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            // set back buttun
            $mainCntrl = new MainMenuItemController();
            $menuItem = $mainCntrl->getMenuIdByName('آموزش استفاده و سوالات متداول');

            array_push($opr, [
                [
                    'text' => "بازگشت به {$menuItem->alias_name}",
                    'callback_data' => "main-{$menuItem->id}",
                ],
            ]);
            // array_push($opr, [
            //     [
            //         'text' => 'بازگشت به منوی اصلی',
            //         'callback_data' => '0',
            //     ],
            // ]);
            $text = 'یک گزینه را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            return response()->json($result, 200);
        }
    }
    public function accountDetails()
    {
        $this->addNewBotLog('ballance', 'نمایش اطلاعات حساب کاربر.', 'show');

        $accCntrl = new AccountBallanceController();

        $ballance = $accCntrl->getUserAccuntBalance($this->chat_id);
        $ballanceInDollar = $accCntrl->getUserAccuntBalanceInDollar($this->chat_id);
        $referalCntrl = new ReferralWalletController();
        $referralAmount = $referalCntrl->get_amount_of_ref_wallet_by_account_id($this->chat_id);
        $loyaltyService = new \App\Services\LoyaltyPointsService();
        $loyaltyPoints = $loyaltyService->getBalanceByAccountId($this->chat_id);
        $text = "♦️ اطلاعات حساب شما: \n\r";

        $text .= "نام کاربری: $this->username \n\r";
        $text .= "نام: $this->first_name \n\r";
        $text .= "نام خانوادگی: $this->last_name \n\r";
        $text .= "آیدی عددی: $this->chat_id \n\r";
        $text .= 'موجودی کیف پول شما: ';
        // show $ballance with thousands seperator
        $text .= number_format($ballance, 0, '.', ',');
        $text .= " تومان \n\r";
        $text .= 'موجودی دلاری کیف پول شما: ';
        // show $ballance with thousands seperator
        $text .= number_format($ballanceInDollar, 0, '.', ',');
        $text .= "$ \n\r";
        $text .= 'موجودی کیف همکاری شما: ';
        // show $ballance with thousands seperator
        $text .= number_format($referralAmount, 0, '.', ',');
        $text .= " تومان \n\r";
        if ($loyaltyService->isActive()) {
            $text .= 'امتیاز باشگاه مشتریان: ';
            $text .= number_format($loyaltyPoints, 0, '.', ',');
            $text .= " امتیاز \n\r";
        }

        $text .= ' ➖➖➖ ';
        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

        $opr = [];

        array_push($opr, [
            [
                'text' => 'شارژ کیف پول 💰',
                'callback_data' => 'addAccountBalance',
            ],
        ]);

        $text = 'یک گزینه را انتخاب کنید.:';
        $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
        return response()->json($result, 200);
    }
    public function appDownload()
    {
        $appCtrl = new ApplicationController();
        $oses = $appCtrl->getApplicationOSes();
        $opr = [];
        if ($oses != null) {
            foreach ($oses as $key => $os) {
                $catName = $os->os;
                // remove charecter '-' from $catName
                $catName = str_replace('-', ' ', $catName);

                array_push($opr, [
                    [
                        'text' => "$catName",
                        'callback_data' => 'subAppDownload-' . $os->os,
                    ],
                ]);
            }
            $text = 'سیستم عامل را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            $this->addNewBotLog('history', 'نمایش گزینه های دانلود برنامه بر اساس سیستم عامل.', 'show');

            return response()->json($result, 200);
        }
    }
    public function subAppDownload()
    {
        $selectedOsID = $this->userCommandArr[1];
        $appCtrl = new ApplicationController();
        $apps = $appCtrl->getAllActiveAplicationListByOS($selectedOsID);

        $opr = [];
        if ($apps != null) {
            foreach ($apps as $key => $app) {
                $name = $app->name;
                // remove charecter '-' from $catName
                $name = str_replace('-', ' ', $name);

                array_push($opr, [
                    [
                        'text' => "$name",
                        'callback_data' => 'getAppDownload-' . $app->id,
                    ],
                ]);
            }
            $text = 'یک برنامه را انتخاب کنید:';
            $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);
            $this->addNewBotLog('app', 'نمایش گزینه های دانلود برنامه بر اساس سیستم عامل.', 'show');

            return response()->json($result, 200);
        }
    }
    public function getAppDownload()
    {
        // $this->deleteMessage();

        $selectedOsID = $this->userCommandArr[1];
        \Log::info("selectedOsID:$selectedOsID");
        $appCtrl = new ApplicationController();
        $app = $appCtrl->getActiveAplicationByID($selectedOsID);
        $text = '';

        if (isset($app)) {
            // $text .= "نام برنامه: $app->name \n\r";
            // $text .= "$app->description \n\r";
            if (isset($app['name'])) {
                $text .= "نام برنامه: $app->name \n\r";
            }
            if (isset($app['description'])) {
                $text .= "$app->description \n\r";
            }
            if (isset($app['download_link'])) {
                $text .= "لینک دانلود: $app->download_link \n\r";
            }
            if (isset($app['file_src'])) {
                $text .= "لینک فایل: $app->file_src \n\r";
            }
            if (isset($app['how_to_use'])) {
                $text .= "چطور استفاده کنی؟: $app->how_to_use \n\r";
            }
            if (isset($app['youtube_link'])) {
                $text .= "لینک یوتیوب: $app->youtube_link \n\r";
            }

            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            return response()->json($resualt, 200);
        }
        $text = 'برنامه مورد نظر یافت نشد.';
        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
        return response()->json($resualt, 200);

        return response()->json($result, 200);
    }
    public function giftCard()
    {
        $giftMenuCntrl = new GiftCardMenuItemController();
        $mainText = $giftMenuCntrl->getGiftCardMainMenuTitle();
        $text = '';
        $text .= "{$mainText->alias_name} \n\r";
        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
    }
    public function subGiftCard()
    {
        $insertedGift = $this->text;
        \Log::info("imported gift code :$insertedGift");

        $giftMenuCntrl = new GiftCardMenuItemController();

        // check validation of inserted gift code
        $giftCntrl = new GiftCardController();
        $gift = $giftCntrl->getGiftCardByCode($insertedGift);

        if ($gift == null) {
            $text = 'کد وارد شده معتبر نمی باشد.';
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            return response()->json($resualt, 200);
        }

        // check how many time user used it and eligable to use it again

        $usedGiftCntrl = new UsedGiftCardController();
        $userUsedItemCount = $usedGiftCntrl->getCountOfUsePerUser($gift->id, $this->chat_id);

        if ($userUsedItemCount >= $gift->count_of_use_per_user) {
            $expire_text = $giftMenuCntrl->getGiftCardExpiredMenuTitle();
            $text = "{$expire_text}\n\r";
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            return response()->json($resualt, 200);
        }

        $reualt = $usedGiftCntrl->addGiftCardToUserAccount($gift->id, $this->chat_id, $insertedGift);

        if ($reualt) {
            $text = '';

            $text .= "{$giftMenuCntrl->getGiftCardAcceptedMenuTitle()} \n\r";
            $text .= "مبلغ $gift->discount تومان به حساب شما افزوده شد. \n\r";
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            return response()->json($resualt, 200);
        }
        $text = '';
        $expire_text = $giftMenuCntrl->getGiftCardExpiredMenuTitle();
        $text = "{$expire_text}\n\r";

        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
        return response()->json($resualt, 200);
    }
    public function testAccount()
    {
        $testAccountCntrl = new TestAccountController();
        $testAccount = $testAccountCntrl->getTestAccountDetails();
        $usedTestAccountCntrl = new UsedTestAccountController();
        $customTextCtrl = new CustomTextController();

        $text = '';
        if ($usedTestAccountCntrl->checkUserHasTestAccount($this->chat_id, $testAccount->id)) {
            $text .= "اکانت آزمایشی از قبل برای شما فعال شده است، می توانید از سابقه خرید به اطلاعات آن دسترسی داشته باشید.  \n\r";
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            return response()->json($resualt, 200);
        }

        // get test product id

        $prCat = new ProductCategoryController();
        $selectedPrCat = $prCat->getProdctCategoryByCategoryName(TestAccountController::CATEGORY_NAME);
        if ($selectedPrCat == null && $testAccount != null) {
            $selectedPrCat = $testAccountCntrl->ensureTestProductCategory($testAccount);
        }
        if ($selectedPrCat == null) {
            $text = $customTextCtrl->getText('error.server_error');
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

            return response()->json($resualt, 200);
        }

        $text .= "اکانت آزمایشی شما با موفقیت فعال شد. \n\r";

        $text .= "تاریخ انقضای اکانت آزمایشی : $testAccount->expire_day روز \n\r";
        $text .= "میزان امتیاز اکانت آزمایشی : $testAccount->volume \n\r";

        $text .= "شما می توانید از این اکانت آزمایشی استفاده کنید. \n\r";

        $prCntrl = new ProductController();

        $pnlCntrl = new PannelController();
        $pannel = $pnlCntrl->getPannelById($testAccount->pannel_id);
        // Prefer test-account config days/volume when available.
        $day = $testAccount->expire_day ?? $selectedPrCat->expire_day;
        $volume = $testAccount->volume ?? $selectedPrCat->volume;
        $created = false;

        if ($pannel->type == 'hiddify') {
            $testAccountLabel = BotUser::resolveConfigAccountLabel($this->chat_id, 'اکانت_آزمایشی');
            $req = new Request();
            $req->accountId = $testAccountLabel;
            $req->chat_id = $this->chat_id;
            $req->product_id = 'اکانت_آزمایشی';
            $req->pannelID = $selectedPrCat->pannel_id;
            $req->vol = $volume;
            $req->day = $day;
            \Log::info("vol $volume day $day");
            $hiddifcCntrl = new HiddifyPannelController();
            $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req); // api v2

            // $newUUID = $hiddifcCntrl->addUserToHiddifyPanelOldApi($req);
            $userLink = $pannel->user_link;
            if (substr($userLink, -1) == '/') {
                $userLink = substr($userLink, 0, -1);
            }

            $userSubscriptionLInk = "$userLink/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $userPannelLink = "$userLink/{$newUUID}/#{$req->accountId}";

            $image = $pnlCntrl->generateQrMOC($userSubscriptionLInk);
            if ($selectedPrCat->show_pannel_link == 1) {
                $text .= "لینک پنل شما برای مشاهده اطلاعات بسته خریداری شده:{$userPannelLink} \r\n";
            }
            $text .= "لینک سابسکریپشن: $userSubscriptionLInk \r\n";
            $text .= "همچینین شما می توانید QRCode ارسال شده را اسکن نمایید. در صورت نیاز به راهنمایی بر روی آموزش استفاده از لینک سابسکریپشن کلیک کنید.\r\n";

            $resualt = app('telegram_bot')->imageMessageByLink($image, $this->chat_id, $text);
            // save as dectivate product, So we can use it in future when user want to recharge it;
            $request = new Request();
            $request->account_id = $this->chat_id;
            $request->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $request->product_categories_id = $selectedPrCat->id;
            $request->panel_link = "/{$newUUID}/#{$req->accountId}";
            $request->configs = '';
            $request->remark = $testAccountLabel;

            $prCntrl->addAutomatedProductDetails($request);
            $created = $newUUID !== false && $newUUID !== null;
        } elseif ($pannel->type == 'sanaei') {
            $generalCntrl = new GeneralController();
            $created = $generalCntrl->new_sanaei_config_telegram_text(
                $selectedPrCat,
                $pannel,
                $volume,
                $day,
                $this->chat_id,
                $selectedPrCat->id
            ) !== false;
        } elseif ($pannel->isMarzbanCompatible()) {
            $generalCntrl = new GeneralController();
            $mbCtrl = MarzbanPannelController::resolve($pannel);
            $created = $generalCntrl->new_marzban_config_telegram_text(
                $selectedPrCat,
                $pannel,
                $volume,
                $day,
                $this->chat_id,
                $selectedPrCat->id,
                $mbCtrl->buildTestAccountUsername($this->chat_id),
                $pannel->customTextKey('action.test_account.marzban')
            ) !== false;
        }

        if (! $created) {
            $text = $customTextCtrl->getText('error.server_error');
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            return response()->json($resualt, 200);
        }

        $usedTestAccountCntrl->markTestAccountUsed($this->chat_id, $testAccount->id);

        $this->addNewBotLog('account', 'اکانت تست فعال شد', 'test-account');

        $opr = [];
        array_push($opr, [
            [
                'text' => 'آموزش استفاده',
                'callback_data' => 'help-subscription',
            ],
        ]);
        array_push($opr, [
            [
                'text' => 'برنامه های مورد نیاز',
                'callback_data' => 'help-applications',
            ],
        ]);
        $text = 'یک گزینه را انتخاب کنید.';
        $result = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

        return response()->json($result, 200);

        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
        return response()->json($resualt, 200);
    }
    public function referral()
    {
        $referralSettingCntrl = new ReferralSettingController();
        $referralMenu = $referralSettingCntrl->get_referral_setting();

        $referralDesc = $referralMenu->description;
        $text = '';
        $text = "$referralDesc \r\n";
        $opr = [];
        array_push($opr, [
            [
                'text' => 'ایجاد لینک دعوت',
                'callback_data' => 'subReferral',
            ],
        ]);
        $resualt = app('telegram_bot')->commandMessage($opr, $this->chat_id, $text);

        return response()->json($resualt, 200);
    }
    public function subReferral()
    {
        $referralSettingCntrl = new ReferralSettingController();
        $visitCard = $referralSettingCntrl->get_referral_setting_visit_card_text();

        // getbotname drom setting cntrl
        $settingCntrl = new SettingController();
        $botName = $settingCntrl->get_bot_name();

        $inviteUrl = "https://t.me/{$botName}?start={$this->chat_id}";
        $text = '';

        $visitText = explode('\r\n', $visitCard);

        foreach ($visitText as $key => $value) {
            $newtext = trim($value);
            $text .= "\n\r{$newtext}";
        }
        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

        $text = "$inviteUrl";

        $resualt = app('telegram_bot')->sendMessage("<code>$text</code>", $this->chat_id, null, 'HTML');


        return response()->json($resualt, 200);
    }
    public function buyGiftCard()
    {
        $channelLockCtrl = new ChannelLockController();
        $channels = $channelLockCtrl->getAllActiveChannelLock();
        $opr = [];

        foreach ($channels as $channel => $value) {
            array_push($opr, [
                [
                    'text' => 'خرید گیفت کارت',
                    'url' => 'https://t.me/AppleGiftxbot',
                ],
            ]);
        }

        $channelLockMenuCtrl = new ChannelLockMenuItemController();

        $text = 'برای خرید گیفت کارد وارد این لینک بشوید.';

        $result = app('telegram_bot')->inlineKeyboardButton($text, $opr, $this->chat_id, '');
        return response()->json($result, 200);
    }
    public function subAdminFastCharge()
    {
        $insertedCharge = $this->text;
        // explode inserted charge by -
        $chargeArr = explode('-', $insertedCharge);
        // check is admin or not
        $user = User::where('account_id', $this->chat_id)->first();
        $user_role = $user->role;
        if ($user_role != 'admin') {
            $text = 'درخواست مجاز نمی باشد.';
            $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');
            return response()->json($resualt, 200);
        }

        $reqAccountId = $chargeArr[1];
        $reqAmount = $chargeArr[2];

        // add account ballance
        $accounBalanceCntrl = new AccountBallanceController();
        $accounBalanceCntrl->incUserAccuntBalance($reqAccountId, $reqAmount);
        // sent message to admin of bot
        $text = 'با موفقیت انجام شد.';
        $resualt = app('telegram_bot')->sendMessage($text, $this->chat_id, null, 'MarkDown');

        // send message to user
        $text = '';

        $text .= "مبلغ $reqAmount تومان به حساب شما افزوده شد. \n\r";
        $resualt = app('telegram_bot')->sendMessage($text, $reqAccountId, null, 'MarkDown');
        return response()->json($resualt, 200);
    }
    public function addNewBotLog($type, $message, $event)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $this->chat_id, $this->username, $event);
        return true;
    }
    public function sendMessageToAdmin($chat_id, $image_url, $text, $messageType)
    {
        $settingCtrl = new SettingController();

        $admin_id = $settingCtrl->getAdminId();
        if ($messageType == 'image') {
            $result = app('telegram_bot')->imageMessage($image_url, $admin_id, $text);

            return response()->json($result, 200);
        } else {
            $result = app('telegram_bot')->sendMessage($text, $admin_id, '');
        }
    }
    /// check  dollarPay is valid or not
    public function checkDollarPay()
    {
        $paymnetSettingCntrl = new PaymentSettingController();
        $dollarTransaction = $paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');

        if ($dollarTransaction == 1 || $dollarTransaction == true) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param  iterable<int, object>  $categories
     * @return array{text: string, opr: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    private function buildPackageSelectionLegacyMessage(iterable $categories, string $baseText): array
    {
        $paymnetSettingCntrl = new PaymentSettingController();
        $dollarTransaction = $paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');
        $layoutService = new PackageButtonLayoutService();
        $selection = $layoutService->buildPackageSelection(
            collect($categories),
            $dollarTransaction == true || $dollarTransaction == 1,
            $baseText
        );

        return [
            'text' => $selection['message'],
            'opr' => $layoutService->toLegacyInlineKeyboard($selection['buttons']),
        ];
    }

    // preper text
    public function prepareText($text)
    {
        $text = str_replace("\r\n", "\n\r", $text);
        $text = str_replace("\r", "\n\r", $text);
        $text = str_replace("\n", "\n\r", $text);
        $text = str_replace('{username}', $this->username, $text);
        return $text;
    }
}
