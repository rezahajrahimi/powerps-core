<?php

namespace App\Http\Controllers;

use App\Models\BotUser;
use Illuminate\Http\Request;
use App\Services\ConfigNameService;
use App\Services\MobileVerificationService;
use App\Services\PromoCodeService;

class WebAppUserController extends Controller
{
    public function getFaqs()
    {
        try {
            $faqCtrl = new FaqController();

            return response()->json($faqCtrl->getFaqList() ?? [], 200);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@getFaqs: ' . $th->getMessage());

            return response()->json([], 500);
        }
    }

    public function getSupports()
    {
        try {
            $supportCtrl = new SupportController();

            return response()->json($supportCtrl->getSupporstList() ?? [], 200);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@getSupports: ' . $th->getMessage());

            return response()->json([], 500);
        }
    }

    public function getApplicationOses()
    {
        try {
            $appCtrl = new ApplicationController();

            return response()->json($appCtrl->getApplicationOSes() ?? [], 200);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@getApplicationOses: ' . $th->getMessage());

            return response()->json([], 500);
        }
    }

    public function getApplicationsByOs(string $os)
    {
        try {
            $appCtrl = new ApplicationController();

            return response()->json($appCtrl->getAllActiveAplicationListByOS($os) ?? [], 200);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@getApplicationsByOs: ' . $th->getMessage());

            return response()->json([], 500);
        }
    }

    public function getReferralInfo()
    {
        try {
            $user = auth('sanctum')->user();
            if ($user == null) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $referralSettingCntrl = new ReferralSettingController();
            $setting = $referralSettingCntrl->get_referral_setting();
            if ($setting == null || !$setting->is_active) {
                return response()->json([
                    'is_active' => false,
                    'message' => 'سیستم دعوت فعال نیست.',
                ], 200);
            }

            $settingCntrl = new SettingController();
            $botName = $settingCntrl->get_bot_name();
            $accountId = $user->account_id;
            $inviteUrl = "https://t.me/{$botName}?start={$accountId}";
            $percent = $referralSettingCntrl->get_referral_setting_referral_percent() ?? 0;
            $percentStr = \App\Models\ReferralSetting::formatPercentValue($percent);

            $customTextCtrl = new CustomTextController();
            $description = $customTextCtrl->getText('action.referral.text', [
                'link' => $inviteUrl,
                'percent' => $percentStr,
            ]);
            if (is_array($description)) {
                $description = implode("\n", $description);
            }

            return response()->json([
                'is_active' => true,
                'invite_url' => $inviteUrl,
                'percent' => (float) $percent,
                'description' => $setting->description,
                'visit_card_text' => $setting->visit_card_text,
                'formatted_text' => $description,
            ], 200);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@getReferralInfo: ' . $th->getMessage());

            return response()->json(['message' => 'خطای سرور'], 500);
        }
    }

    public function redeemGiftCard(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if ($user == null) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $code = trim((string) ($request->code ?? ''));
            if ($code === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'کد گیفت کارت را وارد کنید.',
                ], 422);
            }

            $accountId = (string) $user->account_id;
            $customTextCtrl = new CustomTextController();
            $attemptsCacheKey = "gift_card_attempts_{$accountId}";
            $blockedCacheKey = "gift_card_blocked_{$accountId}";

            if (Cache::has($blockedCacheKey)) {
                $blockExpiresIn = now()->diffInMinutes(Cache::get($blockedCacheKey));

                return response()->json([
                    'success' => false,
                    'message' => $customTextCtrl->getText('error.giftCard.too_many_attempts', [
                        'minutes' => $blockExpiresIn,
                    ]),
                ], 429);
            }

            $attempts = Cache::get($attemptsCacheKey, 0) + 1;
            Cache::put($attemptsCacheKey, $attempts, now()->addHour());

            $giftCardCntrl = new GiftCardController();
            $giftCard = $giftCardCntrl->getGiftCardByCode($code);

            if ($giftCard == null) {
                if ($attempts >= 3) {
                    Cache::put($blockedCacheKey, now()->addHour(), now()->addHour());
                    Cache::forget($attemptsCacheKey);

                    return response()->json([
                        'success' => false,
                        'message' => $customTextCtrl->getText('error.giftCard.blocked'),
                    ], 429);
                }

                return response()->json([
                    'success' => false,
                    'message' => $customTextCtrl->getText('error.giftCard.not_found'),
                ], 404);
            }

            Cache::forget($attemptsCacheKey);

            $usedGiftCntrl = new UsedGiftCardController();
            $userUsedItemCount = $usedGiftCntrl->getCountOfUsePerUser($giftCard->id, $accountId);
            if ($userUsedItemCount >= $giftCard->count_of_use_per_user) {
                return response()->json([
                    'success' => false,
                    'message' => $customTextCtrl->getText('error.giftCard.already_used'),
                ], 409);
            }

            $result = $usedGiftCntrl->addGiftCardToUserAccount($giftCard->id, $accountId, $giftCard->code);
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => $customTextCtrl->getText('action.help.giftCard.success'),
                    'discount' => $giftCard->discount,
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => $customTextCtrl->getText('error.giftCard.already_used'),
            ], 409);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@redeemGiftCard: ' . $th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطای سرور',
            ], 500);
        }
    }

    public function claimTestAccount()
    {
        try {
            $user = auth('sanctum')->user();
            if ($user == null) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $accountId = (string) $user->account_id;
            $customTextCtrl = new CustomTextController();

            $testAccountCntrl = new TestAccountController();
            $testAccount = $testAccountCntrl->getTestAccountDetails();
            if ($testAccount == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'اکانت آزمایشی پیکربندی نشده است.',
                ], 404);
            }

            $usedTestAccountCntrl = new UsedTestAccountController();
            if ($usedTestAccountCntrl->checkUserHasTestAccount($accountId, $testAccount->id)) {
                return response()->json([
                    'success' => false,
                    'message' => $customTextCtrl->getText('error.test_account.exist'),
                ], 409);
            }

            $panelCntrl = new PannelController();
            $pannel = $panelCntrl->getPannelById($testAccount->pannel_id);
            if ($pannel == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'پنل اکانت آزمایشی یافت نشد.',
                ], 404);
            }

            $prCatCntrl = new ProductCategoryController();
            $selectedPrCat = $prCatCntrl->getProdctCategoryByCategoryName(TestAccountController::CATEGORY_NAME);
            if ($selectedPrCat == null) {
                $selectedPrCat = $testAccountCntrl->ensureTestProductCategory($testAccount);
            }
            if ($selectedPrCat == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'دسته اکانت آزمایشی یافت نشد.',
                ], 404);
            }

            $day = $testAccount->expire_day;
            $volume = $testAccount->volume;
            $subscriptionLink = null;
            $panelLink = null;
            $config = null;
            $created = false;

            if ($pannel->type == 'hiddify') {
                $hiddifcCntrl = new HiddifyPannelController();
                $accountLabel = BotUser::resolveConfigAccountLabel($accountId, 'اکانت_آزمایشی');
                $req = new Request();
                $req->accountId = $accountLabel;
                $req->chat_id = $accountId;
                $req->product_id = $testAccount->id;
                $req->pannelID = $testAccount->pannel_id;
                $req->vol = $volume;
                $req->day = $day;

                $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req);
                if ($newUUID == false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'خطا در ایجاد اکانت آزمایشی',
                    ], 500);
                }

                $userLink = rtrim((string) $pannel->user_link, '/');
                $subscriptionLink = "{$userLink}/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
                $panelLink = "{$userLink}/{$newUUID}/#{$accountLabel}";

                $request = new Request();
                $request->account_id = $accountId;
                $request->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
                $request->product_categories_id = $selectedPrCat->id;
                $request->panel_link = "/{$newUUID}/#{$accountLabel}";
                $request->configs = '';
                $request->remark = $accountLabel;
                $request->product_id = $selectedPrCat->id;
                $prCntrl = new ProductController();
                $prCntrl->addAutomatedProductDetails($request);
                $created = true;
            } elseif ($pannel->type == 'sanaei') {
                $snCtrl = new SanaeiPannelController();
                $accountLabel = BotUser::resolveConfigAccountLabel($accountId, $selectedPrCat->id);
                $inboundIds = method_exists($selectedPrCat, 'resolveInboundIds')
                    ? $selectedPrCat->resolveInboundIds()
                    : [];
                $req = new Request();
                $req->merge([
                    'accountId' => $accountLabel,
                    'chat_id' => $accountId,
                    'product_id' => $selectedPrCat->id,
                    'pannelID' => $pannel->id,
                    'vol' => $volume,
                    'day' => $day,
                    'inbound_ids' => $inboundIds,
                    'inbound_id' => $inboundIds[0] ?? $selectedPrCat->inbound_id,
                    'ip_limit' => $selectedPrCat->ip_limit,
                ]);

                $result = $snCtrl->addUserToSanaeiPanel($req, $inboundIds);
                if ($result === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'خطا در ایجاد اکانت آزمایشی',
                    ], 500);
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

                $links = $snCtrl->getUserLinks($pannel, $uuid, $accountLabel, $selectedPrCat->inbound_id, $clientEmail ?: null);
                $subscriptionLink = $snCtrl->buildSubscriptionLink($pannel, $subId);
                $panelLink = $subscriptionLink;
                $config = ! empty($links) ? $links[0] : null;

                $request = new Request();
                $request->account_id = $accountId;
                $request->subscription_link = $subscriptionLink;
                $request->product_categories_id = $selectedPrCat->id;
                $request->panel_link = $subscriptionLink;
                $request->configs = json_encode([
                    'uuid' => $uuid,
                    'email' => $clientEmail,
                    'subId' => $subId,
                    'links' => $links ?? [],
                ]);
                $request->remark = $accountLabel;
                $request->product_id = $selectedPrCat->id;
                $prCntrl = new ProductController();
                $prCntrl->addAutomatedProductDetails($request);
                $created = true;
            } elseif ($pannel->isMarzbanCompatible()) {
                $mbCtrl = MarzbanPannelController::resolve($pannel);
                $username = $mbCtrl->buildTestAccountUsername($accountId);
                $userData = $mbCtrl->createUser($pannel, $username, (int) $day, $volume);
                if ($userData === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'خطا در ایجاد اکانت آزمایشی',
                    ], 500);
                }

                $subscriptionLink = $userData['subscription_link'] ?? null;
                $panelLink = $subscriptionLink;
                $links = $userData['links'] ?? [];
                $config = !empty($links) ? $links[0] : null;

                $request = new Request();
                $request->account_id = $accountId;
                $request->subscription_link = $userData['subscription_url'] ?? '';
                $request->product_categories_id = $selectedPrCat->id;
                $request->panel_link = $subscriptionLink;
                $request->configs = json_encode([
                    'username' => $userData['username'] ?? $username,
                    'links' => $links,
                ]);
                $request->remark = $username;
                $request->product_id = $selectedPrCat->id;
                $prCntrl = new ProductController();
                $prCntrl->addAutomatedProductDetails($request);
                $created = true;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'نوع پنل پشتیبانی نمی‌شود.',
                ], 400);
            }

            if (! $created) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در ایجاد اکانت آزمایشی',
                ], 500);
            }

            $usedTestAccountCntrl->markTestAccountUsed($accountId, $testAccount->id);

            $logCtrl = new LogController();
            $logCtrl->addNewLog('test_account', 'دریافت اکانت آزمایشی از وب‌اپ', $accountId, '', 'show');

            return response()->json([
                'success' => true,
                'message' => $customTextCtrl->getText('action.test_account.success'),
                'expire_day' => $day,
                'volume' => $volume,
                'subscription_link' => $subscriptionLink,
                'panel_link' => $panelLink,
                'config' => $config,
            ], 200);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@claimTestAccount: ' . $th->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطای سرور',
            ], 500);
        }
    }

    public function validatePromoCode(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if ($user == null) {
                return response()->json(['valid' => false, 'message' => 'Unauthorized'], 401);
            }

            $request->validate([
                'code' => 'required|string',
                'category_id' => 'required|integer',
            ]);

            $agentProductCtrl = new AgentProductController();
            $pricing = $agentProductCtrl->resolveProductPricingForAccount(
                (string) $user->account_id,
                (int) $request->category_id
            );

            if ($pricing === null) {
                return response()->json([
                    'valid' => false,
                    'message' => 'این بسته برای شما در دسترس نیست.',
                ], 422);
            }

            $service = new PromoCodeService();
            $result = $service->validate(
                $request->code,
                (string) $user->account_id,
                (int) $request->category_id,
                (float) $pricing['price'],
                (float) $pricing['price_in_dollar']
            );

            return response()->json($result, ($result['valid'] ?? false) ? 200 : 422);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@validatePromoCode: ' . $th->getMessage());

            return response()->json([
                'valid' => false,
                'message' => 'خطای سرور',
            ], 500);
        }
    }

    public function getMobileVerificationStatus()
    {
        try {
            $user = auth('sanctum')->user();
            if ($user == null) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $status = (new MobileVerificationService())->statusForAccount($user->account_id);

            return response()->json($status, 200);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@getMobileVerificationStatus: ' . $th->getMessage());

            return response()->json(['message' => 'خطای سرور'], 500);
        }
    }

    public function getPackageNameHint()
    {
        try {
            return response()->json([
                'preview' => ConfigNameService::preview(
                    ConfigNameService::getFormat(),
                    ConfigNameService::getPrefix()
                ),
                'hint' => 'اگر نام بسته را وارد نکنید، مطابق تنظیمات ربات نام‌گذاری می‌شود.',
            ], 200);
        } catch (\Throwable $th) {
            \Log::error('WebAppUserController@getPackageNameHint: ' . $th->getMessage());

            return response()->json([
                'preview' => ConfigNameService::preview(
                    ConfigNameService::DEFAULT_FORMAT,
                    ConfigNameService::DEFAULT_PREFIX
                ),
                'hint' => 'اگر نام بسته را وارد نکنید، مطابق تنظیمات ربات نام‌گذاری می‌شود.',
            ], 200);
        }
    }
}
