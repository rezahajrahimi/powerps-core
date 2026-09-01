<?php

namespace App\Http\Controllers;

use App\Models\AgentProduct;
use App\Models\BotUser;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Pannel;
use App\Models\User;
use App\Models\AccountBallance;
use App\Models\AgentPermisson;
use App\Services\ConfigNameService;
use App\Services\InventoryPurchaseService;
use App\Services\PromoCodeService;
use App\Services\LoyaltyPointsService;
use App\Services\MobileVerificationService;
use App\Services\SubscriptionPaymentService;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Hekmatinasser\Verta\Verta;

class AgentProductController extends Controller
{
    private function applyPanelIdentityToRequest(
        Request $req,
        int|string $accountId,
        int|string|null $suffix = null,
    ): void {
        $req->chat_id = $accountId;
        $req->product_id = $suffix;
        $req->accountId = ConfigNameService::resolveAccountLabel($accountId, $suffix);
    }

    private function recordWebPromoUsage($appliedPromo, string $accountId, float $discountToman): void
    {
        if ($appliedPromo === null) {
            return;
        }

        (new PromoCodeService())->recordUsage($appliedPromo, $accountId, $discountToman);
    }

    /**
     * @return array{
     *     ok: bool,
     *     message?: string,
     *     remark?: string,
     *     reserved_product_id?: int,
     *     account_label?: string
     * }
     */
    private function prepareWebPurchaseIdentity(
        int|string $accountID,
        int $categoryId,
        ?string $userRemark,
        ?string $displayNamePrefix = null,
    ): array {
        $prCntrl = new ProductController();
        $reservedProductId = $prCntrl->reserveProductId($accountID, $categoryId);
        if ($reservedProductId === null) {
            return ['ok' => false, 'message' => 'خطا در رزرو بسته'];
        }

        $accountLabel = BotUser::resolveConfigAccountLabel($accountID, $reservedProductId);
        $trimmedRemark = trim((string) ($userRemark ?? ''));

        if ($trimmedRemark === '') {
            $remark = $accountLabel;
        } elseif ($displayNamePrefix !== null && trim($displayNamePrefix) !== '') {
            $remark = trim($displayNamePrefix) . ' - ' . $trimmedRemark;
        } else {
            $remark = $trimmedRemark;
        }

        return [
            'ok' => true,
            'remark' => $remark,
            'reserved_product_id' => $reservedProductId,
            'account_label' => $accountLabel,
        ];
    }

    /**
     * @return array{ok: bool, status?: int, body?: mixed}
     */
    private function processWebPurchase(
        int|string $accountID,
        ProductCategory $selectedPrCat,
        float $productPrice,
        float $productPriceInDollar,
        ?string $userRemark,
        ?string $displayNamePrefix,
        $appliedPromo,
        float $promoDiscountToman,
        bool $logProductPurchase = true,
    ): array {
        $pnlCntrl = new PannelController();
        $pannel = $pnlCntrl->getPannelById($selectedPrCat->pannel_id);
        if ($pannel == null) {
            return ['ok' => false, 'status' => 500, 'body' => 'پنل یافت نشد'];
        }

        $day = $selectedPrCat->expire_day;
        $volume = $selectedPrCat->volume;
        $prCntrl = new ProductController();
        $accBlCtrl = new AccountBallanceController();

        if ($pannel->isInventoryPanel()) {
            if ($prCntrl->countActiveInventory($selectedPrCat->id) < 1) {
                return ['ok' => false, 'status' => 500, 'body' => 'موجودی این بسته تمام شده است'];
            }

            $soldInventoryProductId = (new InventoryPurchaseService())->deliverInventoryProduct($selectedPrCat, $accountID);
            if ($soldInventoryProductId === false) {
                return ['ok' => false, 'status' => 500, 'body' => 'خطا در تحویل کانفیگ از موجودی'];
            }

            $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
            $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت خرید بسته کم شد.", 'minus ballance');

            $trimmedRemark = trim((string) ($userRemark ?? ''));
            if ($trimmedRemark !== '') {
                $inventoryRemark = ($displayNamePrefix !== null && trim((string) $displayNamePrefix) !== '')
                    ? trim((string) $displayNamePrefix) . ' - ' . $trimmedRemark
                    : $trimmedRemark;
                Product::where('id', $soldInventoryProductId)->update(['remark' => $inventoryRemark]);
            }

            if ($logProductPurchase) {
                $this->addNewBotLog('product', "بسته {$selectedPrCat->category_name} خریداری شد.", 'buy product');
            }
            $this->recordWebPromoUsage($appliedPromo, (string) $accountID, $promoDiscountToman);

            $soldProduct = Product::find($soldInventoryProductId);
            $deliveryLink = '';
            if ($soldProduct !== null) {
                if ($selectedPrCat->show_subscription_link && ! empty($soldProduct->subscription_link)) {
                    $deliveryLink = (string) $soldProduct->subscription_link;
                } else {
                    $configLinks = ProductCategory::extractConfigLinks($soldProduct->configs);
                    $deliveryLink = $configLinks[0] ?? (string) ($soldProduct->subscription_link ?? '');
                }
            }

            return ['ok' => true, 'body' => $deliveryLink];
        }

        $identity = $this->prepareWebPurchaseIdentity(
            $accountID,
            (int) $selectedPrCat->id,
            $userRemark,
            $displayNamePrefix
        );
        if (! ($identity['ok'] ?? false)) {
            return ['ok' => false, 'status' => 500, 'body' => $identity['message'] ?? 'خطا در رزرو بسته'];
        }

        $remark = $identity['remark'];
        $reservedProductId = $identity['reserved_product_id'];

        if ($pannel->type == 'hiddify') {
            $req = new Request();
            $this->applyPanelIdentityToRequest($req, $accountID, (string) $reservedProductId);
            $req->pannelID = $selectedPrCat->pannel_id;
            $req->vol = $volume;
            $req->day = $day;
            $hiddifcCntrl = new HiddifyPannelController();

            $newUUID = $hiddifcCntrl->addUserToHiddifyPanel($req);
            if ($newUUID == false) {
                $prCntrl->deletePendingProduct($reservedProductId);

                return ['ok' => false, 'status' => 500, 'body' => 'Error in creating user in panel'];
            }

            $userPannelLink = $hiddifcCntrl->get_hiddify_subscription_link($pannel->user_link, "/{$newUUID}/#{$req->accountId}");

            $reqProductDetails = new Request();
            $reqProductDetails->account_id = $accountID;
            $reqProductDetails->subscription_link = "/{$newUUID}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $reqProductDetails->product_categories_id = $selectedPrCat->id;
            $reqProductDetails->panel_link = "/{$newUUID}/#{$req->accountId}";
            $reqProductDetails->configs = '';
            $reqProductDetails->remark = $remark;
            $reqProductDetails->product_id = $reservedProductId;

            $prCntrl->addAutomatedProductDetails($reqProductDetails);
            $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
            $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت خرید بسته کم شد.", 'minus ballance');
            if ($logProductPurchase) {
                $this->addNewBotLog('product', "$remark خریداری شد.", 'buy product');
            }
            $this->recordWebPromoUsage($appliedPromo, (string) $accountID, $promoDiscountToman);

            return ['ok' => true, 'body' => $userPannelLink];
        }

        if ($pannel->type == 'sanaei') {
            $snCtrl = new SanaeiPannelController();
            $category = ProductCategory::query()->find($selectedPrCat->id) ?? $selectedPrCat;
            $inboundIds = $category->resolveInboundIds();
            $req = new Request();
            $this->applyPanelIdentityToRequest($req, $accountID, (string) $reservedProductId);
            $req->merge([
                'pannelID' => $category->pannel_id,
                'vol' => $volume,
                'day' => $day,
                'inbound_ids' => $inboundIds,
                'inbound_id' => $inboundIds[0] ?? $category->inbound_id,
                'ip_limit' => $category->ip_limit,
            ]);

            $result = $snCtrl->addUserToSanaeiPanel($req, $inboundIds);
            if ($result === false) {
                $prCntrl->deletePendingProduct($reservedProductId);

                return ['ok' => false, 'status' => 500, 'body' => 'Error in creating user in panel'];
            }
            if (is_array($result)) {
                $uuid = $result['uuid'];
                $subId = $result['subId'];
            } else {
                $uuid = $result;
                $subId = $uuid;
            }

            $links = $snCtrl->getUserLinks($pannel, $uuid, $req->accountId, $selectedPrCat->inbound_id);

            if ($selectedPrCat->show_subscription_link) {
                $userPannelLink = $snCtrl->buildSubscriptionLink($pannel, $subId);
            } else {
                $userPannelLink = $links[0] ?? '';
            }

            $reqProductDetails = new Request();
            $reqProductDetails->account_id = $accountID;
            $reqProductDetails->subscription_link = $userPannelLink;
            $reqProductDetails->product_categories_id = $selectedPrCat->id;
            $reqProductDetails->panel_link = '';
            $reqProductDetails->configs = json_encode(['uuid' => $uuid, 'links' => $links ?? []]);
            $reqProductDetails->remark = $remark;
            $reqProductDetails->product_id = $reservedProductId;

            $prCntrl->addAutomatedProductDetails($reqProductDetails);
            $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
            $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت خرید بسته کم شد.", 'minus ballance');
            if ($logProductPurchase) {
                $this->addNewBotLog('product', "$remark خریداری شد.", 'buy product');
            }
            $this->recordWebPromoUsage($appliedPromo, (string) $accountID, $promoDiscountToman);

            return ['ok' => true, 'body' => $userPannelLink];
        }

        if ($pannel->isMarzbanCompatible()) {
            $mbCtrl = MarzbanPannelController::resolve($pannel);
            $category = ProductCategory::query()->find($selectedPrCat->id) ?? $selectedPrCat;
            $marzbanInbounds = $category->resolveMarzbanInbounds();
            $pasarguardGroupIds = $category->resolvePasarguardGroupIds();
            $marzbanUsername = $mbCtrl->buildBotUsername($accountID, $reservedProductId);
            $userData = $mbCtrl->createUser(
                $pannel,
                $marzbanUsername,
                (int) $day,
                $volume,
                $marzbanInbounds !== [] ? $marzbanInbounds : null,
                $pasarguardGroupIds !== [] ? $pasarguardGroupIds : null
            );
            if ($userData === false) {
                $prCntrl->deletePendingProduct($reservedProductId);

                return ['ok' => false, 'status' => 500, 'body' => 'Error in creating user in panel'];
            }

            $links = $userData['links'] ?? [];
            $userSub = $userData['subscription_link'] ?? '';
            if ($selectedPrCat->show_subscription_link) {
                $deliveryLink = $userSub !== '' ? $userSub : (string) ($userData['subscription_url'] ?? '');
            } else {
                $deliveryLink = $links[0] ?? $userSub;
            }

            $reqProductDetails = new Request();
            $reqProductDetails->account_id = $accountID;
            $reqProductDetails->subscription_link = $userData['subscription_url'] ?? '';
            $reqProductDetails->product_categories_id = $selectedPrCat->id;
            $reqProductDetails->panel_link = $userSub;
            $reqProductDetails->configs = json_encode([
                'username' => $userData['username'] ?? $marzbanUsername,
                'links' => $links,
            ]);
            $reqProductDetails->remark = $remark;
            $reqProductDetails->product_id = $reservedProductId;

            $prCntrl->addAutomatedProductDetails($reqProductDetails);
            $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
            $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت خرید بسته کم شد.", 'minus ballance');
            if ($logProductPurchase) {
                $this->addNewBotLog('product', "$remark خریداری شد.", 'buy product');
            }
            $this->recordWebPromoUsage($appliedPromo, (string) $accountID, $promoDiscountToman);

            return ['ok' => true, 'body' => $deliveryLink];
        }

        $prCntrl->deletePendingProduct($reservedProductId);

        return ['ok' => false, 'status' => 500, 'body' => 'نوع پنل پشتیبانی نمی‌شود'];
    }

    public function obtainBatchOfExistProductsToUser(Request $request)
    {
        $pannelID = $request['pannelID'];
        $accountID = $request['accountID'];
        $user = User::where('account_id', $accountID)->first();
        if ($user == null) {
            return response()->json(false, 201);
        }

        $pannel = Pannel::find($pannelID);
        if ($pannel == null) {
            return response()->json(false, 201);
        }

        $selectedExistConfig = json_decode($request['configs'], true);
        if (! is_array($selectedExistConfig)) {
            return response()->json(false, 201);
        }

        $prCatCntrl = new ProductCategoryController();
        $prCntrl = new ProductController();

        foreach ($selectedExistConfig as $rawValue) {
            $value = is_string($rawValue) ? json_decode($rawValue, true) : $rawValue;
            if (! is_array($value)) {
                continue;
            }

            $uuid = (string) ($value['uuid'] ?? '');
            if ($uuid === '') {
                continue;
            }

            $name = (string) ($value['name'] ?? $uuid);
            $packageDays = (int) ($value['packageDays'] ?? $value['package_days'] ?? 0);
            $usageLimitGB = (float) ($value['usageLimitGB'] ?? $value['usage_limit_GB'] ?? 0);

            $req = new Request();
            $req->product_categories_id = $prCatCntrl->getProductCatIdBYExpireDayPannelIDVolume(
                $packageDays,
                $pannelID,
                $usageLimitGB
            );
            $req->pannelID = $pannelID;
            $req->remark = $name;
            $req->account_id = $accountID;

            if ($pannel->type === 'sanaei') {
                $snCtrl = new SanaeiPannelController();
                $links = $snCtrl->getUserLinks($pannel, $uuid, $name);
                $subLink = $links[0] ?? '';
                if ($subLink === '') {
                    $found = $snCtrl->findClientByUUID($pannel, $uuid);
                    $subId = $found['client']['subId'] ?? $uuid;
                    $subLink = $snCtrl->buildSubscriptionLink($pannel, $subId);
                }
                $req->subscription_link = $subLink;
                $req->panel_link = '';
                $req->configs = json_encode(['uuid' => $uuid, 'links' => $links]);
            } elseif ($pannel->isMarzbanCompatible()) {
                $mb = MarzbanPannelController::resolve($pannel);
                $username = $uuid;
                $panelUser = $mb->getUser($pannel, $username);
                $userSub = $mb->getSubscriptionLink($pannel, $username) ?? '';
                $req->subscription_link = $panelUser['subscription_url'] ?? '';
                $req->panel_link = $userSub;
                $req->configs = json_encode([
                    'username' => $username,
                    'links' => $panelUser['links'] ?? [],
                ]);
            } else {
                $req->configs = '';
                $req->subscription_link = "/{$uuid}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
                $req->panel_link = "/{$uuid}/#{$name}";
            }

            $prCntrl->addOrUpdateProductDetailsBySubscriptionLink($req);
        }

        return response()->json(true, 200);
    }
    public function createBatchOfUserAgentProduct(Request $request)
    {
        try {
            // get count of users with agent role
            $agentsCount = User::where('role', 'agent')->count();
            $authCntrl = new AuthController();
            // check powerps license
            $getPowerPsLicenseType = $authCntrl->getPowerPsLicenseType();
            $hasAccountLimitation = false;
            if ($getPowerPsLicenseType == "false" || $getPowerPsLicenseType == "trial" || $getPowerPsLicenseType == "boronze") {
                $hasAccountLimitation = true;
            }

            if ($agentsCount > 10 && $getPowerPsLicenseType == "silver") {
                $hasAccountLimitation = true;
            }
            if ($hasAccountLimitation == true) {
                // check this operation is update or create, so if user alreade have a agent role it's a update and we have to continue else it's a create

                $checkIsExist = User::where('role', 'agent')
                    ->where('account_id', $request['UserID'])
                    ->first();
                if (null == $checkIsExist) {
                    return response()->json('به محدودیت افزودن دستیار فروش رسیده اید، برای افزودن دستیار جدید با پشتیبانی تماس بگیرید و اکانت خود را ارتقا بدهید.', 201);
                }
            }
            $data = json_decode($request, true);

            $reqUserID = $request['UserID'];
            $userCntrl = new UserController();

            $userID = $userCntrl->getUserIdByTelegramID($reqUserID);
            if ($userID == null) {
                return response()->json(false, 201);
            }

            $selectedProductList = json_decode($request['selectedProductList'], true);
            if (! is_array($selectedProductList)) {
                return response()->json(false, 201);
            }

            // create an array
            $newSelectedProductList = [];

            foreach ($selectedProductList as $value) {
                $value = $this->normalizeSelectedProductItem($value);
                if ($value === null) {
                    continue;
                }

                $productCategoryId = (int) ($value['productCategoriesId'] ?? $value['id']);
                if ($productCategoryId <= 0) {
                    continue;
                }

                $req = new Request();
                $req->product_categories_id = $productCategoryId;
                $req->price = $value['newPrice'] ?? $value['price'] ?? 0;
                $req->price_in_dollar = $value['newPriceInDollar'] ?? $value['priceInDollar'] ?? 0;
                $req->user_id = $userID;
                $req->is_active = true;

                $newSelectedProductList[] = $productCategoryId;

                $this->createANewAgentProduct($req);
            }

            // log the array

            // get all agent products wich id is not in $newSelectedProductList array
            $allAgentProducts = AgentProduct::where('user_id', $userID)->get();

            foreach ($allAgentProducts as $value) {
                if (!in_array($value->product_categories_id, $newSelectedProductList)) {
                    $this->deleteAgentProductByPrCatIDAndUserID($userID, $value->product_categories_id);
                }
            }

            $agentPremissionCntrl = new AgentPermissonController();
            $reqPermission = new Request();
            $reqPermission->merge([
                'user_id' => $userID,
                'minus_ballance' => $request['minusBallance'],
                'minus_ballance_limit' => $request['minusBallanceLimit'] ?? null,
                'create_products' => $request['createProducts'],
                'delete_products' => $request['deleteProducts'],
                'traffic_limitation_tb' => $request['trafficLimitationTB'] ? $request['trafficLimitationTB'] : 10,
                'product_limitation' => $request['productLimitation'] ? $request['productLimitation'] : 1000,
            ]);
            $agentPremissionCntrl->updateAgentPremisson($reqPermission);
            $userCntrl->changeUserRoleToAgent($userID);
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("$th");

            return response()->json($th, 201);
        }
    }
    public function removeAgent(Request $request)
    {
        try {
            $data = json_decode($request, true);

            $reqUserID = $request['UserID'];
            // change agent role to user
            $userCntrl = new UserController();
            $userID = $userCntrl->getUserIdByTelegramID($reqUserID);
            if ($userID == null) {
                return response()->json(false, 201);
            }
            $userCntrl->changeAgentRoleToUser($userID);

            // remove agent permission

            $agentPremissionCntrl = new AgentPermissonController();
            $agentPremissionCntrl->deleteAgentPremisson($userID);

            // remove agent product
            $res = $this->deleteAllAgentProductsByUserIDAndAssignToBotAdmin($userID);

            //

            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("$th");

            return response()->json($th, 201);
        }
    }
    public function deleteBatchOfUserAgentProduct(Request $request)
    {
        try {
            $data = json_decode($request, true);

            $reqUserID = $request['UserID'];
            $userCntrl = new UserController();

            $userID = $userCntrl->getUserIdByTelegramID($reqUserID);
            if ($userID == null) {
                return response()->json(false, 201);
            }
            $selectedProductList = json_decode($request['selectedProductList'], true);
            if (! is_array($selectedProductList)) {
                return response()->json(false, 201);
            }

            foreach ($selectedProductList as $value) {
                $value = $this->normalizeSelectedProductItem($value);
                if ($value === null) {
                    continue;
                }

                $productCategoryId = $value['productCategoriesId'] ?? $value['id'] ?? null;
                if ($productCategoryId != null) {
                    $this->deleteAgentProductByPrCatIDAndUserID($userID, $productCategoryId);
                } else {
                    $this->deleteAgentProductByIDAndUserID($userID, $value['id']);
                }
            }
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("$th");

            return response()->json($th, 201);
        }
    }
    private function normalizeSelectedProductItem($value): ?array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return null;
        }

        return $value;
    }

    public function createANewAgentProduct(Request $request)
    {
        try {
            $hasProductCategory = ProductCategory::where('id', $request->product_categories_id)->first();

            if ($hasProductCategory == null) {
                return;
            }

            $existing = AgentProduct::where('user_id', $request->user_id)
                ->where('product_categories_id', $request->product_categories_id)
                ->first();

            if ($existing != null) {
                $request->merge(['id' => $existing->id]);

                return $this->updateAgentProduct($request);
            }

            $agentProduct = new AgentProduct();

            $agentProduct->product_categories_id = $request->product_categories_id;
            $agentProduct->user_id = $request->user_id;
            $agentProduct->is_active = $request->is_active == true || $request->is_active == 1 ? true : false;
            $agentProduct->price = $request->price ?? 0.0;
            $agentProduct->price_in_dollar = $request->price_in_dollar ?? 0.0;
            $agentProduct->save();

            return response()->json($agentProduct, 200);
        } catch (\Throwable $th) {
            \Log::info("createANewAgentProduct throw $th");
            return response()->json(false, 500);
        }
    }
    public function updateAgentProduct(Request $request)
    {
        try {
            $agentProduct = AgentProduct::find($request->id);
            if ($agentProduct == null) {
                return response()->json(false, 404);
            }

            $agentProduct->price = $request->price ?? 0;
            $agentProduct->price_in_dollar = $request->price_in_dollar ?? 0.0;

            $agentProduct->update();

            return response()->json($agentProduct, 200);
        } catch (\Throwable $th) {
            \Log::info("updateAgentProduct throw $th");
            return response()->json(false, 500);
        }
    }
    public function deleteAgentProduct($id)
    {
        try {
            $agentProduct = AgentProduct::find($id);
            $agentProduct->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function deleteAgentProductByPrCatIDAndUserID($userID, $productCatId)
    {
        try {
            $agentProduct = AgentProduct::where('user_id', $userID)->where('product_categories_id', $productCatId)->first();
            if (!$agentProduct) {
                return;
            }
            $agentProduct->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function deleteAllAgentProductsByUserID($userID)
    {
        try {
            $agentProduct = AgentProduct::where('user_id', $userID)->get();
            if (!$agentProduct) {
                return;
            }
            $agentProduct->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function deleteAllAgentProductsByUserIDAndAssignToBotAdmin($userID)
    {
        try {
            $agentUser = User::find($userID);
            if (!$agentUser) {
                return false;
            }

            $adminUser = auth('sanctum')->user();
            if (!$adminUser) {
                return false;
            }

            // Transfer sold products (VPN accounts) to admin
            Product::where('account_id', $agentUser->account_id)->update(['account_id' => $adminUser->account_id]);

            // Delete agent's custom price list
            AgentProduct::where('user_id', $userID)->delete();

            return true;
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return false;
        }
    }
    public function getAgentProductsByUserID($userID)
    {
        try {
            return AgentProduct::where('user_id', $userID)->with('product_categories')->get();
        } catch (\Throwable $th) {
            return response()->json(null, 500);
        }
    }

    public function getAgentUserByAccountId($accountId): ?User
    {
        return User::where('account_id', $accountId)->where('role', 'agent')->first();
    }

    public function getActiveProductCategoriesForAgent(int $userId, ?int $panelId = null)
    {
        return AgentProduct::where('user_id', $userId)
            ->where('is_active', true)
            ->with('product_categories')
            ->get()
            ->filter(function ($agentProduct) use ($panelId) {
                $category = $agentProduct->product_categories;
                if ($category === null || ! $category->is_active) {
                    return false;
                }
                if ($category->category_name === 'اکانت آزمایشی') {
                    return false;
                }
                if ($panelId !== null && (int) $category->pannel_id !== (int) $panelId) {
                    return false;
                }

                return true;
            })
            ->map(function ($agentProduct) {
                $category = $agentProduct->product_categories->replicate();
                $category->id = $agentProduct->product_categories->id;
                $category->price = $agentProduct->price;
                $category->price_in_dollar = $agentProduct->price_in_dollar;

                return $category;
            })
            ->sortBy('price')
            ->values();
    }

    public function resolveProductPricingForAccount($accountId, $productCategoryId): ?array
    {
        $user = User::where('account_id', $accountId)->first();
        if ($user === null) {
            return null;
        }

        $category = ProductCategory::where('id', $productCategoryId)
            ->where('is_active', true)
            ->first();
        if ($category === null) {
            return null;
        }

        if ($user->role !== 'agent') {
            if (! $category->isAllowedForUserGroup($user->user_group_id)) {
                return null;
            }

            return [
                'category' => $category,
                'price' => $category->price,
                'price_in_dollar' => $category->price_in_dollar,
                'is_agent' => false,
            ];
        }

        $agentProduct = AgentProduct::where('user_id', $user->id)
            ->where('product_categories_id', $productCategoryId)
            ->where('is_active', true)
            ->first();
        if ($agentProduct === null) {
            return null;
        }

        return [
            'category' => $category,
            'price' => $agentProduct->price,
            'price_in_dollar' => $agentProduct->price_in_dollar,
            'is_agent' => true,
        ];
    }

    /**
     * @return array{ok: bool, message?: string, status?: int, category?: ProductCategory, price?: float, price_in_dollar?: float, applied_promo?: \App\Models\PromoCode|null, promo_discount_toman?: float}
     */
    public function resolveWebPurchasePricing(string $accountId, int $categoryId, ?string $promoCode = null): array
    {
        $pricing = $this->resolveProductPricingForAccount($accountId, $categoryId);
        if ($pricing === null) {
            return [
                'ok' => false,
                'message' => 'این بسته برای شما در دسترس نیست.',
                'status' => 404,
            ];
        }

        $priceToman = (float) $pricing['price'];
        $priceDollar = (float) $pricing['price_in_dollar'];
        $appliedPromo = null;
        $promoDiscountToman = 0.0;

        if ($promoCode !== null && trim($promoCode) !== '') {
            $promoService = new PromoCodeService();
            $promoResult = $promoService->validate(
                $promoCode,
                $accountId,
                $categoryId,
                $priceToman,
                $priceDollar
            );
            if (! ($promoResult['valid'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => $promoResult['message'] ?? 'کد تخفیف نامعتبر است.',
                    'status' => 422,
                ];
            }
            $priceToman = (float) ($promoResult['final_price_toman'] ?? $priceToman);
            $priceDollar = (float) ($promoResult['final_price_dollar'] ?? $priceDollar);
            $promoDiscountToman = (float) ($promoResult['discount_toman'] ?? 0);
            $appliedPromo = $promoResult['promo'] ?? null;
        }

        return [
            'ok' => true,
            'category' => $pricing['category'],
            'price' => $priceToman,
            'price_in_dollar' => $priceDollar,
            'applied_promo' => $appliedPromo,
            'promo_discount_toman' => $promoDiscountToman,
        ];
    }

    public function checkAgentPurchaseLimits(
        $accountId,
        float $additionalVolumeGb = 0,
        int $additionalProducts = 1
    ): ?string {
        $user = $this->getAgentUserByAccountId($accountId);
        if ($user === null) {
            return null;
        }

        $usage = $this->getAgentLimitUsage($user->id);
        if ($usage === null) {
            return null;
        }

        if ($additionalProducts > 0) {
            $newCount = $usage['used_product_count'] + $additionalProducts;
            if ($newCount > $usage['product_limit']) {
                return 'به محدودیت تعداد محصول رسیده‌اید.';
            }
        }

        if ($additionalVolumeGb > 0) {
            $additionalTrafficTb = round($additionalVolumeGb / 1000, 2);
            $newTraffic = round($usage['used_traffic_tb'] + $additionalTrafficTb, 2);
            if ($newTraffic > $usage['traffic_limit_tb']) {
                return 'به محدودیت ترافیک رسیده‌اید.';
            }
        }

        return null;
    }

    public function calculateRawAgentUsage($accountId): array
    {
        $productCount = Product::where('account_id', $accountId)->count();
        $trafficGb = Product::where('account_id', $accountId)
            ->leftJoin('product_categories', 'products.product_categories_id', '=', 'product_categories.id')
            ->sum('product_categories.volume');

        return [
            'product_count' => (int) $productCount,
            'traffic_tb' => round(((float) $trafficGb) / 1000, 2),
        ];
    }

    public function getAgentLimitUsage(int $userId): ?array
    {
        $user = User::find($userId);
        if ($user === null || $user->role !== 'agent') {
            return null;
        }

        $agentPermisson = AgentPermisson::where('user_id', $userId)->first();
        if ($agentPermisson === null) {
            return null;
        }

        $raw = $this->calculateRawAgentUsage($user->account_id);
        $baselineCount = (int) ($agentPermisson->product_count_baseline ?? 0);
        $baselineTraffic = (float) ($agentPermisson->traffic_tb_baseline ?? 0);
        $productLimit = (int) $agentPermisson->product_limitation;
        $trafficLimit = (float) $agentPermisson->traffic_limitation_tb;

        $usedCount = max(0, $raw['product_count'] - $baselineCount);
        $usedTraffic = max(0, round($raw['traffic_tb'] - $baselineTraffic, 2));

        $usage = [
            'product_limit' => $productLimit,
            'traffic_limit_tb' => $trafficLimit,
            'used_product_count' => $usedCount,
            'used_traffic_tb' => $usedTraffic,
            'remaining_product_count' => max(0, $productLimit - $usedCount),
            'remaining_traffic_tb' => max(0, round($trafficLimit - $usedTraffic, 2)),
            'total_product_count' => $raw['product_count'],
            'total_traffic_tb' => $raw['traffic_tb'],
            'product_count_baseline' => $baselineCount,
            'traffic_tb_baseline' => $baselineTraffic,
            'product_usage_percent' => $productLimit > 0
                ? min(100, round(($usedCount / $productLimit) * 100, 1))
                : 0,
            'traffic_usage_percent' => $trafficLimit > 0
                ? min(100, round(($usedTraffic / $trafficLimit) * 100, 1))
                : 0,
        ];

        if ($agentPermisson->minus_ballance === 1 || $agentPermisson->minus_ballance === true) {
            $balance = AccountBallance::where('account_id', $user->account_id)->first();
            $currentBalance = $balance ? (float) $balance->ballance : 0.0;
            $debtLimit = $agentPermisson->minus_ballance_limit !== null
                ? (float) $agentPermisson->minus_ballance_limit
                : null;
            $currentDebt = $currentBalance < 0 ? abs($currentBalance) : 0.0;

            $usage['minus_ballance_limit'] = $debtLimit;
            $usage['current_balance'] = $currentBalance;
            $usage['current_debt'] = $currentDebt;

            if ($debtLimit !== null && $debtLimit > 0) {
                $usage['remaining_debt_limit'] = max(0, round($debtLimit - $currentDebt, 2));
                $usage['debt_usage_percent'] = min(
                    100,
                    round(($currentDebt / $debtLimit) * 100, 1)
                );
            } else {
                $usage['remaining_debt_limit'] = null;
                $usage['debt_usage_percent'] = 0;
            }
        }

        return $usage;
    }

    public function resetAgentLimitUsage(int $userId): ?array
    {
        $user = User::find($userId);
        if ($user === null || $user->role !== 'agent') {
            return null;
        }

        $agentPermisson = AgentPermisson::where('user_id', $userId)->first();
        if ($agentPermisson === null) {
            return null;
        }

        $raw = $this->calculateRawAgentUsage($user->account_id);
        $agentPermisson->product_count_baseline = $raw['product_count'];
        $agentPermisson->traffic_tb_baseline = $raw['traffic_tb'];
        $agentPermisson->save();

        return $this->getAgentLimitUsage($userId);
    }

    public function getAgentProductsByID($ID)
    {
        try {
            return AgentProduct::where('id', $ID)->first();
        } catch (\Throwable $th) {
            return response()->json(null, 500);
        }
    }
    public function reChargeProductByAdminWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $selectedPrCat = ProductCategory::find($data->product_categories_id);

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

            if ($pannel && $pannel->type == 'sanaei') {
                $configs = json_decode($data->configs, true) ?? [];
                $uuid = $configs['uuid'] ?? null;
                if ($uuid == null) {
                    return response()->json(false, 400);
                }
                $sn = new SanaeiPannelController();
                $res = $sn->updateLimits($pannel->id, $uuid, $selectedPrCat->expire_day, $selectedPrCat->volume);
                if ($res) {
                    $this->addNewBotLog('product', "$data->remark توسط مدیر شارژ شد", 'charge product');
                    return response()->json(true, 200);
                }
                return response()->json(false, 500);
            }

            if ($pannel && $pannel->isMarzbanCompatible()) {
                $mb = MarzbanPannelController::resolve($pannel);
                $res = $mb->updateLimits($pannel->id, $data->resolveMarzbanPanelUsername(), $selectedPrCat->expire_day, $selectedPrCat->volume);
                if ($res) {
                    $this->addNewBotLog('product', "$data->remark توسط مدیر شارژ شد", 'charge product');

                    return response()->json(true, 200);
                }

                return response()->json(false, 500);
            }

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
                $this->addNewBotLog('product', "$data->remark توسط مدیر شارژ شد", 'charge product');

                return response()->json(true, 200);
            }

            return response()->json(false, 500);
        } else {
            return response()->json(false, 500);
        }
    }
    public function changeProductByAdminWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $oldPrCat = ProductCategory::find($data->product_categories_id);
        $newPrCat = ProductCategory::find($request->newPrCatID);

        if ($data != null) {
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            if ($pannel && $pannel->type == 'sanaei') {
                $configs = json_decode($data->configs, true) ?? [];
                $uuid = $configs['uuid'] ?? null;
                if ($uuid == null) {
                    return response()->json(false, 400);
                }
                $sn = new SanaeiPannelController();
                $res = $sn->updateLimits($pannel->id, $uuid, $newPrCat->expire_day, $newPrCat->volume);
                if ($res) {
                    if ($request->changeBallance == 1 || $request->changeBallance == true) {
                        $accBalCntrl = new AccountBallanceController();
                        $diffInToman = $newPrCat->price - $oldPrCat->price;
                        $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                        if ($diffInToman != 0) {
                            $accBalCntrl->decUserAccuntBalance($data->account_id, $diffInToman, $dissInDollar);
                        }
                    }
                    $data->product_categories_id = $newPrCat->id;
                    $data->update();
                    $this->addNewBotLog('product', "$data->remark توسط مدیر تغییر یافت.", 'change product');
                    return response()->json(true, 200);
                }
                return response()->json(false, 500);
            }

            if ($pannel && $pannel->isMarzbanCompatible()) {
                $mb = MarzbanPannelController::resolve($pannel);
                $res = $mb->updateLimits($pannel->id, $data->resolveMarzbanPanelUsername(), $newPrCat->expire_day, $newPrCat->volume);
                if ($res) {
                    if ($request->changeBallance == 1 || $request->changeBallance == true) {
                        $accBalCntrl = new AccountBallanceController();
                        $diffInToman = $newPrCat->price - $oldPrCat->price;
                        $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                        if ($diffInToman != 0) {
                            $accBalCntrl->decUserAccuntBalance($data->account_id, $diffInToman, $dissInDollar);
                        }
                    }
                    $data->product_categories_id = $newPrCat->id;
                    $data->update();
                    $this->addNewBotLog('product', "$data->remark توسط مدیر تغییر یافت.", 'change product');

                    return response()->json(true, 200);
                }

                return response()->json(false, 500);
            }

            $hiddifcCntrl = new HiddifyPannelController();
            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
            $day = $newPrCat->expire_day;
            $volume = $newPrCat->volume;

            $req = new Request();
            $req->pannelID = $newPrCat->pannel_id;
            $req->name = $data->remark;
            $req->uuid = $uuid;
            $req->vol = $volume;
            $req->day = $day;
            // get today date with new variable
            $today = Verta::now();
            if ($request->recharge == true || $request->recharge == 1) {
                $req->comment = "تغییر دسته بندی همراه با ریست زمان و حجم {$today}";

                $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelApi($req);
                // $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelOldApi($req);
                if ($hiddifcCntrl->hiddifyMutationSucceeded($updateRemark)) {
                    $this->addNewBotLog('product', "$data->remark توسط مدیر تغییر یافت.", 'charge product');
                } else {
                    return response()->json(false, 500);
                }
                if ($request->changeBallance == 1 || $request->changeBallance == true) {
                    $accBalCntrl = new AccountBallanceController();
                    // get difference between old and new price
                    $diffInToman = $newPrCat->price - $oldPrCat->price;
                    $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                    if ($diffInToman < 0) {
                        $accBalCntrl->decUserAccuntBalance($data->account_id, $diffInToman, $dissInDollar);
                    }
                    $data->product_categories_id = $newPrCat->id;
                    $data->update();

                    return response()->json(true, 200);
                }
                $data->product_categories_id = $newPrCat->id;
                $data->update();

                return response()->json(true, 200);
            } else {
                $req->comment = "تغییر دسته بندی  {$today}";

                $updateRemark = $hiddifcCntrl->upgradeUserOfHiddifyPanelApi($req);
                // $updateRemark = $hiddifcCntrl->upgradeUserOfHiddifyPanelOldApi($req);
                if ($updateRemark['status'] == 200) {
                    if ($updateRemark['msg'] !== 'ok') {
                        return response()->json(false, 401);
                    }
                    $this->addNewBotLog('product', "$data->remark توسط مدیر تغییر یافت.", 'charge product');
                }
                if ($request->changeBallance == 1 || $request->changeBallance == true) {
                    $accBalCntrl = new AccountBallanceController();

                    // get difference between old and new price
                    $diffInToman = $newPrCat->price - $oldPrCat->price;
                    $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                    if ($diffInToman > 0) {
                        $accBalCntrl->decUserAccuntBalance($data->account_id, $diffInToman, $dissInDollar);
                    }
                    $data->product_categories_id = $newPrCat->id;
                    $data->update();

                    return response()->json(true, 200);
                }
            }
            $data->product_categories_id = $newPrCat->id;
            $data->update();

            return response()->json(false, 500);
        }
        return response()->json(false, 500);
    }
    public function changeActivationOfHiddifyUserByAdmin(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();

        if ($data != null) {
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $today = Verta::now();
            if ($pannel && $pannel->type == 'sanaei') {
                $configs = json_decode($data->configs, true) ?? [];
                $uuid = $configs['uuid'] ?? null;
                if ($uuid == null) {
                    return response()->json(false, 400);
                }
                $sn = new SanaeiPannelController();
                $enable = ($request->enable == true || $request->enable == 1 || $request->enable == 'true');
                $res = $sn->changeUserActivation($pannel->id, $uuid, $enable);
                if ($res) {
                    $data->deactive_by_admin = !$enable;
                    $this->addNewBotLog('product', "$data->remark توسط مدیر " . ($enable ? 'فعال' : 'غیر فعال') . " شد.", 'change activation');
                    $data->update();
                    return response()->json(true, 200);
                }
                return response()->json(false, 401);
            }

            if ($pannel && $pannel->isMarzbanCompatible()) {
                $mb = MarzbanPannelController::resolve($pannel);
                $enable = ($request->enable == true || $request->enable == 1 || $request->enable == 'true');
                $res = $mb->changeUserActivation($pannel->id, $data->resolveMarzbanPanelUsername(), $enable);
                if ($res) {
                    $data->deactive_by_admin = ! $enable;
                    $this->addNewBotLog('product', "$data->remark توسط مدیر " . ($enable ? 'فعال' : 'غیر فعال') . " شد.", 'change activation');
                    $data->update();

                    return response()->json(true, 200);
                }

                return response()->json(false, 401);
            }

            $hiddifcCntrl = new HiddifyPannelController();
            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);

            $req = new Request();
            $req->pannelID = $data->product_category_and_panel->pannel_id;
            $req->uuid = $uuid;

            if ($request->enable == true || $request->enable == 1 || $request->enable == 'true') {
                $req->comment = "فعال شدن بسته توسط مدیر در {$today}";
                $req->enable = true;
                $data->deactive_by_admin = false;
            } else {
                $req->comment = "غیر فعال شدن بسته توسط مدیر در {$today}";
                $req->enable = false;
                $data->deactive_by_admin = true;
            }
            $updateRemark = $hiddifcCntrl->changeUserActivationOfHiddifyPanelApi($req);
            if ($updateRemark->getStatusCode() == 200) {
                $this->addNewBotLog('product', "$data->remark توسط مدیر غیر فعال شد.", 'charge product');
                $data->update();
                return response()->json(true, 200);
            }
            return response()->json(false, 401);
        }
        return response()->json($request->id, 404);
    }
    public function getBoughtProductsPannelLinkFromServerByIdAdminMode($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $userId = auth('sanctum')->user()->account_id;

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

            if ($pannel && $pannel->type == 'sanaei') {
                return $pannel->admin_url;
            }

            if ($pannel && $pannel->isMarzbanCompatible()) {
                if (! empty($data->panel_link)) {
                    return $data->panel_link;
                }
                $mb = MarzbanPannelController::resolve($pannel);

                return $mb->getSubscriptionLink($pannel, $data->resolveMarzbanPanelUsername()) ?? $pannel->url_port;
            }

            $hiddifcCntrl = new HiddifyPannelController();

            return $hiddifcCntrl->get_hiddify_subscription_link($pannel->user_link, $data->panel_link);

        } else {
            return null;
        }
    }
    public function softDeleteProductByAgentWithPrIDAdminMOde($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;

        if ($data != null) {
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            if ($pannel && $pannel->isInventoryPanel()) {
                $data->delete();
                $this->addNewBotLog('product', "بسته $data->remark توسط مدیر حذف شد", 'remove product');

                return response()->json(true, 200);
            }
            if ($pannel && $pannel->type == 'sanaei') {
                $configs = json_decode($data->configs, true) ?? [];
                $uuid = $configs['uuid'] ?? null;
                if ($uuid == null) {
                    return response()->json(null, 400);
                }
                $sn = new SanaeiPannelController();
                $res = $sn->deleteUser($pannel->id, $uuid);
                if ($res) {
                    $data->delete();
                    $this->addNewBotLog('product', "بسته $data->remark توسط مدیر حذف شد", 'remove product');
                    return response()->json(true, 200);
                }
                return response()->json(null, 500);
            }
            if ($pannel && $pannel->isMarzbanCompatible()) {
                $mb = MarzbanPannelController::resolve($pannel);
                $res = $mb->deleteUser($pannel->id, $data->resolveMarzbanPanelUsername());
                if ($res) {
                    $data->delete();
                    $this->addNewBotLog('product', "بسته $data->remark توسط مدیر حذف شد", 'remove product');

                    return response()->json(true, 200);
                }

                return response()->json(null, 500);
            }
            if ($pannel && $pannel->type === Pannel::TYPE_HIDDIFY) {
                $hiddifcCntrl = new HiddifyPannelController();
                $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
                $updateRemark = $hiddifcCntrl->deleteUserOfHiddifyPanel($pannel->id, $uuid);
                if ($updateRemark->getStatusCode() == 200) {
                    $data->delete();
                    $this->addNewBotLog('product', "بسته $data->remark توسط مدیر حذف شد", 'remove product');
                    return response()->json(true, 200);
                }
                return response()->json(null, 500);
            }

            // Unknown / unreachable panel types: still allow admin DB cleanup
            $data->delete();
            $this->addNewBotLog('product', "بسته $data->remark توسط مدیر حذف شد", 'remove product');

            return response()->json(true, 200);
        }
        return response()->json(false, 401);
    }
    /// Agent function
    public function getProductsOfLoggedAgent()
    {
        $userId = auth('sanctum')->user()->id;
        return $this->getAgentProductsByUserID($userId);
    }

    public function getLoggedAgentLimitUsage()
    {
        $usage = $this->getAgentLimitUsage(auth('sanctum')->user()->id);
        if ($usage === null) {
            return response()->json(null, 404);
        }

        return response()->json($usage, 200);
    }

    public function resetAgentLimitUsageByAdmin($userId)
    {
        $usage = $this->resetAgentLimitUsage((int) $userId);
        if ($usage === null) {
            return response()->json(false, 404);
        }

        return response()->json($usage, 200);
    }
    public function buyProductByAgentWithPrID(Request $request)
    {
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        $agentname = auth('sanctum')->user()->name;
        $promoCode = $request->input('promo_code');

        $pricingResolved = $this->resolveWebPurchasePricing(
            (string) $accountID,
            (int) $request->id,
            is_string($promoCode) ? $promoCode : null
        );
        if (! ($pricingResolved['ok'] ?? false)) {
            return response()->json(
                $pricingResolved['message'] ?? 'خطا',
                $pricingResolved['status'] ?? 422
            );
        }

        $selectedPrCat = $pricingResolved['category'];
        $productPrice = $pricingResolved['price'];
        $productPriceInDollar = $pricingResolved['price_in_dollar'];
        $appliedPromo = $pricingResolved['applied_promo'] ?? null;
        $promoDiscountToman = (float) ($pricingResolved['promo_discount_toman'] ?? 0);

        if ($selectedPrCat == null) {
            return response()->json(false, 500);
        }

        if ($selectedPrCat->is_active == false) {
            return response()->json(false, 500);
        }

        $limitMessage = $this->checkAgentPurchaseLimits($accountID, (float) $selectedPrCat->volume, 1);
        if ($limitMessage !== null) {
            return response()->json($limitMessage, 401);
        }

        $accBlCtrl = new AccountBallanceController();
        $loyaltyService = new LoyaltyPointsService();
        $useLoyaltyPoints = $request->boolean('use_loyalty_points', true);
        $loyaltyCheckout = $loyaltyService->resolveCheckout(
            $accountID,
            (float) $productPrice,
            (float) $productPriceInDollar,
            fn ($id, $price, $priceDollar) => $accBlCtrl->checkUserHasBalance($id, $price, $priceDollar),
            fn () => false,
            $useLoyaltyPoints,
        );
        $chargePrice = (float) $loyaltyCheckout['charge_price_toman'];

        if (! $loyaltyCheckout['can_proceed']) {
            return response()->json('low ballance', 401);
        }

        if (! $accBlCtrl->checkUserHasBalance($accountID, $chargePrice, $productPriceInDollar)
            && ! ($loyaltyCheckout['points_only'] ?? false)) {
            return response()->json('low ballance', 401);
        }

        $purchaseResult = $this->processWebPurchase(
            $accountID,
            $selectedPrCat,
            $chargePrice,
            (float) $productPriceInDollar,
            $request->remark,
            $agentname,
            $appliedPromo,
            $promoDiscountToman,
            true
        );

        if (! ($purchaseResult['ok'] ?? false)) {
            return response()->json(
                $purchaseResult['body'] ?? 'خطا در خرید کانفیگ',
                $purchaseResult['status'] ?? 500
            );
        }

        if ((int) $loyaltyCheckout['points_to_redeem'] > 0) {
            $loyaltyService->redeemPoints(
                $accountID,
                (int) $loyaltyCheckout['points_to_redeem'],
                'purchase',
                'product_category',
                $selectedPrCat->id,
                'استفاده از امتیاز در خرید وب'
            );
        }

        $loyaltyService->awardPurchasePoints($accountID, (float) $productPrice, $selectedPrCat->id);

        return $purchaseResult['body'];
    }
    public function buyProductByUserWithPrID(Request $request)
    {
        $accountID = auth('sanctum')->user()->account_id;
        $mobileBlock = (new MobileVerificationService())->purchaseBlockResponse($accountID);
        if ($mobileBlock['blocked'] ?? false) {
            return response()->json([
                'success' => false,
                'code' => $mobileBlock['code'] ?? 'mobile_verification_required',
                'message' => $mobileBlock['message'] ?? 'تایید موبایل لازم است.',
                'bot_username' => $mobileBlock['bot_username'] ?? null,
            ], 403);
        }

        $userName = auth('sanctum')->user()->name;
        $promoCode = $request->input('promo_code');

        $pricingResolved = $this->resolveWebPurchasePricing(
            (string) $accountID,
            (int) $request->id,
            is_string($promoCode) ? $promoCode : null
        );
        if (! ($pricingResolved['ok'] ?? false)) {
            return response()->json(
                $pricingResolved['message'] ?? 'خطا',
                $pricingResolved['status'] ?? 422
            );
        }

        $selectedPrCat = $pricingResolved['category'];
        $productPrice = $pricingResolved['price'];
        $productPriceInDollar = $pricingResolved['price_in_dollar'];
        $appliedPromo = $pricingResolved['applied_promo'] ?? null;
        $promoDiscountToman = (float) ($pricingResolved['promo_discount_toman'] ?? 0);

        if ($selectedPrCat == null) {
            return response()->json(false, 500);
        }

        if ($selectedPrCat->is_active == false) {
            return response()->json(false, 500);
        }

        $accBlCtrl = new AccountBallanceController();
        $loyaltyService = new LoyaltyPointsService();
        $useLoyaltyPoints = $request->boolean('use_loyalty_points', true);
        $loyaltyCheckout = $loyaltyService->resolveCheckout(
            $accountID,
            (float) $productPrice,
            (float) $productPriceInDollar,
            fn ($id, $price, $priceDollar) => $accBlCtrl->checkUserHasBalance($id, $price, $priceDollar),
            fn () => false,
            $useLoyaltyPoints,
        );
        $chargePrice = (float) $loyaltyCheckout['charge_price_toman'];

        if (! $loyaltyCheckout['can_proceed']) {
            return response()->json('low ballance', 401);
        }

        if (! $accBlCtrl->checkUserHasBalance($accountID, $chargePrice, $productPriceInDollar)
            && ! ($loyaltyCheckout['points_only'] ?? false)) {
            return response()->json('low ballance', 401);
        }

        $purchaseResult = $this->processWebPurchase(
            $accountID,
            $selectedPrCat,
            $chargePrice,
            (float) $productPriceInDollar,
            $request->remark,
            $userName,
            $appliedPromo,
            $promoDiscountToman,
            true
        );

        if (! ($purchaseResult['ok'] ?? false)) {
            return response()->json(
                $purchaseResult['body'] ?? 'خطا در خرید کانفیگ',
                $purchaseResult['status'] ?? 500
            );
        }

        if ((int) $loyaltyCheckout['points_to_redeem'] > 0) {
            $loyaltyService->redeemPoints(
                $accountID,
                (int) $loyaltyCheckout['points_to_redeem'],
                'purchase',
                'product_category',
                $selectedPrCat->id,
                'استفاده از امتیاز در خرید وب'
            );
        }

        $loyaltyService->awardPurchasePoints($accountID, (float) $productPrice, $selectedPrCat->id);

        return $purchaseResult['body'];
    }
    public function reChargeProductByUserWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        $selectedPrCat = ProductCategory::find($data->product_categories_id);
        // check agent has terrafic limition or not

        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }
        if ($selectedPrCat->is_active == false) {
            return response()->json(false, 500);
        }

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                ->where('user_id', $userID)
                ->first();
            // return $agentProduct;
            $productPrice = $selectedPrCat->price;
            $productPriceInDollar = $selectedPrCat->price_in_dollar;
            $accBlCtrl = new AccountBallanceController();
            if ($accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar)) {
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
                    $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                    $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                    $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');

                    return response()->json(true, 200);
                    // dd($subsequentResponse);
                }
            }

            if ($accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar)) {
                if ($pannel && $pannel->type == 'sanaei') {
                    $configs = json_decode($data->configs, true) ?? [];
                    $uuid = $configs['uuid'] ?? null;
                    if ($uuid == null) {
                        return response()->json(false, 400);
                    }
                    $sn = new SanaeiPannelController();
                    $res = $sn->updateLimits($pannel->id, $uuid, $selectedPrCat->expire_day, $selectedPrCat->volume);
                    if ($res->getStatusCode() == 200) {
                        $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                        $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                        $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');
                        return response()->json(true, 200);
                    }
                    return response()->json(false, 401);
                }

                if ($pannel && $pannel->isMarzbanCompatible()) {
                    $mb = MarzbanPannelController::resolve($pannel);
                    $res = $mb->updateLimits($pannel->id, $data->resolveMarzbanPanelUsername(), $selectedPrCat->expire_day, $selectedPrCat->volume);
                    if ($res) {
                        $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                        $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                        $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');

                        return response()->json(true, 200);
                    }

                    return response()->json(false, 401);
                }
            }
            return response()->json(false, 401);
        }
        return response()->json(false, 500);
    }
    public function buyProductByAdmin(Request $request)
    {
        $selectedPrCat = ProductCategory::find($request->id);
        if ($selectedPrCat == null || ! $selectedPrCat->is_active) {
            return response()->json('بسته یافت نشد یا غیرفعال است', 500);
        }

        $accountID = $request->account_id;
        $userID = $request->user_id;
        $agentname = $request->username;
        $deductFromWallet = filter_var($request->input('deduct_from_wallet', true), FILTER_VALIDATE_BOOLEAN);

        $productPrice = $selectedPrCat->price;
        $productPriceInDollar = $selectedPrCat->price_in_dollar;

        $agentProduct = AgentProduct::where('product_categories_id', $selectedPrCat->id)
            ->where('user_id', $userID)
            ->first();
        if ($agentProduct != null) {
            $productPrice = $agentProduct->price;
            $productPriceInDollar = $agentProduct->price_in_dollar;
        }

        $accBlCtrl = new AccountBallanceController();
        $referralCntrl = new ReferralWalletController();
        $paymentService = new SubscriptionPaymentService($accBlCtrl, new PaymentSettingController(), $referralCntrl, new LogController());
        $chargeResult = null;

        if ($deductFromWallet) {
            $hasBallance = $accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar);
            $hasRefballance = $referralCntrl->check_user_has_ref_wallet_ballance($accountID, $productPrice);
            if ((! $hasRefballance && ! $hasBallance) || ($hasBallance == 0 && $hasRefballance == 0)) {
                return response()->json('موجودی کیف پول کاربر کافی نیست', 401);
            }
        }

        $pnlCntrl = new PannelController();
        $pannel = $pnlCntrl->getPannelById($selectedPrCat->pannel_id);
        if ($pannel == null) {
            return response()->json('پنل یافت نشد', 500);
        }

        $day = $selectedPrCat->expire_day;
        $volume = $selectedPrCat->volume;
        $prCntrl = new ProductController();
        $generalCntrl = new GeneralController();
        $botUser = BotUser::where('account_id', $accountID)->first();
        $username = $botUser?->username ?? (string) $accountID;
        $reservedProductId = null;
        $soldInventoryProductId = null;

        try {
            if ($pannel->isInventoryPanel()) {
                if ($prCntrl->countActiveInventory($selectedPrCat->id) < 1) {
                    return response()->json('موجودی این بسته تمام شده است', 500);
                }

                if ($deductFromWallet) {
                    $hasRefballance = $referralCntrl->check_user_has_ref_wallet_ballance($accountID, $productPrice);
                    $chargeResult = $paymentService->charge(
                        $accountID,
                        (float) $productPrice,
                        (float) $productPriceInDollar,
                        (bool) $hasRefballance,
                        $username,
                        'admin_purchase'
                    );
                    if (! $paymentService->wasCharged($chargeResult)) {
                        return response()->json('موجودی کیف پول کاربر کافی نیست', 401);
                    }
                    $this->addNewBotLog('ballance', "مبلغ $productPrice از حساب کاربر $accountID بابت خرید بسته توسط ادمین کم شد.", 'minus ballance');
                }

                $inventoryService = new InventoryPurchaseService();
                $soldInventoryProductId = $inventoryService->deliverInventoryProduct($selectedPrCat, $accountID);
                if ($soldInventoryProductId === false) {
                    if ($chargeResult !== null) {
                        $paymentService->refund($accountID, $chargeResult, $username, 'admin_purchase');
                    }

                    return response()->json('خطا در تحویل کانفیگ از موجودی', 500);
                }

                $trimmedRemark = trim((string) ($request->remark ?? ''));
                if ($trimmedRemark !== '') {
                    $inventoryRemark = ($agentname !== null && trim((string) $agentname) !== '')
                        ? trim((string) $agentname) . ' - ' . $trimmedRemark
                        : $trimmedRemark;
                    Product::where('id', $soldInventoryProductId)->update(['remark' => $inventoryRemark]);
                }

                $generalCntrl->send_using_subscription_manual_message($accountID, null, null, true);
                $this->addNewBotLog('product', "بسته {$selectedPrCat->category_name} برای کاربر $accountID توسط ادمین خریداری شد.", 'buy product');

                return response()->json(['success' => true, 'product_id' => $soldInventoryProductId], 200);
            }

            $identity = $this->prepareWebPurchaseIdentity(
                $accountID,
                (int) $selectedPrCat->id,
                $request->remark,
                $agentname
            );
            if (! ($identity['ok'] ?? false)) {
                return response()->json($identity['message'] ?? 'خطا در رزرو بسته', 500);
            }

            $remark = $identity['remark'];
            $reservedProductId = $identity['reserved_product_id'];

            if ($deductFromWallet) {
                $hasRefballance = $referralCntrl->check_user_has_ref_wallet_ballance($accountID, $productPrice);
                $chargeResult = $paymentService->charge(
                    $accountID,
                    (float) $productPrice,
                    (float) $productPriceInDollar,
                    (bool) $hasRefballance,
                    $username,
                    'admin_purchase'
                );
                if (! $paymentService->wasCharged($chargeResult)) {
                    $prCntrl->deletePendingProduct($reservedProductId);

                    return response()->json('موجودی کیف پول کاربر کافی نیست', 401);
                }
                $this->addNewBotLog('ballance', "مبلغ $productPrice از حساب کاربر $accountID بابت خرید بسته توسط ادمین کم شد.", 'minus ballance');
            }

            $result = null;
            if ($pannel->type == 'hiddify') {
                $result = $generalCntrl->new_hiddify_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $accountID, $reservedProductId);
            } elseif ($pannel->isMarzbanCompatible()) {
                $result = $generalCntrl->new_marzban_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $accountID, $reservedProductId);
            } elseif ($pannel->type == 'sanaei') {
                $result = $generalCntrl->new_sanaei_config_telegram_text($selectedPrCat, $pannel, $volume, $day, $accountID, $reservedProductId);
            } else {
                if ($chargeResult !== null) {
                    $paymentService->refund($accountID, $chargeResult, $username, 'admin_purchase');
                }
                $prCntrl->deletePendingProduct($reservedProductId);

                return response()->json('نوع پنل پشتیبانی نمی‌شود', 500);
            }

            if ($result === false || $result === null) {
                if ($chargeResult !== null) {
                    $paymentService->refund($accountID, $chargeResult, $username, 'admin_purchase');
                }
                $prCntrl->deletePendingProduct($reservedProductId);

                return response()->json('خطا در ایجاد کانفیگ در پنل', 500);
            }

            Product::where('id', $reservedProductId)->update(['remark' => $remark]);
            $generalCntrl->send_using_subscription_manual_message($accountID, null, null, false);
            $this->addNewBotLog('product', "$remark برای کاربر $accountID توسط ادمین خریداری شد.", 'buy product');

            return response()->json(['success' => true, 'product_id' => $reservedProductId], 200);
        } catch (\Throwable $th) {
            \Log::error('buyProductByAdmin failed: ' . $th->getMessage(), [
                'account_id' => $accountID,
                'category_id' => $selectedPrCat->id,
                'trace' => $th->getTraceAsString(),
            ]);

            if ($chargeResult !== null && $paymentService->wasCharged($chargeResult)) {
                $paymentService->refund($accountID, $chargeResult, $username, 'admin_purchase');
            }
            if ($reservedProductId !== null) {
                $prCntrl->deletePendingProduct($reservedProductId);
            }
            if ($soldInventoryProductId !== null) {
                (new InventoryPurchaseService())->rollbackDelivery($soldInventoryProductId);
            }

            return response()->json('خطا در خرید کانفیگ', 500);
        }
    }
    public function getAgentSelledProducts($count = 10)
    {
        $userId = auth('sanctum')->user()->account_id;
        $product = Product::where('account_id', $userId)->with('product_category_and_panel')->take($count)->orderBy('id', 'desc')->get();

        return $product;
    }
    public function getAgentSelledProductsByPagination()
    {
        try {
            $userId = auth('sanctum')->user()->account_id;
            $product = Product::where('account_id', $userId)
                ->with('product_category_and_panel')
                ->orderBy('id', 'desc')
                ->paginate(10, ['*'], 'page');

            return $product;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }

    public function getAgentSelledProductsByAdmin($userId, Request $request)
    {
        try {
            $user = User::where('id', $userId)->where('role', 'agent')->first();
            if ($user == null) {
                return response()->json(null, 404);
            }

            $page = (int) $request->get('page', 1);
            $product = Product::where('account_id', $user->account_id)
                ->with('product_category_and_panel')
                ->orderBy('id', 'desc')
                ->paginate(10, ['*'], 'page', $page);

            return $product;
        } catch (\Throwable $th) {
            \Log::info("getAgentSelledProductsByAdmin: $th");

            return response()->json('Server Error', 500);
        }
    }
    public function resolveBoughtProductStatusFromServer(int|string $id): ?array
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        if ($data == null) {
            return null;
        }

        try {
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            if ($pannel == null) {
                return null;
            }

            if ($pannel->type == 'sanaei') {
                $configs = json_decode($data->configs, true) ?? [];
                $uuid = $configs['uuid'] ?? null;
                $email = $configs['email'] ?? null;
                if ($uuid == null) {
                    return null;
                }
                $sn = new SanaeiPannelController();
                $status = $sn->getClientStatus($pannel, $uuid);
                if (! $status && $email) {
                    $found = $sn->findClientByEmail($pannel, $email);
                    if ($found) {
                        $status = $sn->getClientStatus($pannel, $found['client']['id'] ?? $uuid);
                    }
                }
                if ($status) {
                    $status['panel_type'] = 'sanaei';

                    return $status;
                }

                return null;
            }

            if ($pannel->isMarzbanCompatible()) {
                $mb = MarzbanPannelController::resolve($pannel);
                $status = $mb->getClientStatus($pannel, $data->resolveMarzbanPanelUsername());
                if ($status) {
                    $status['panel_type'] = $pannel->type;

                    return $status;
                }

                return null;
            }

            $hiddifcCntrl = new HiddifyPannelController();
            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
            if ($uuid == null || $uuid === '') {
                return null;
            }

            $body = $hiddifcCntrl->sendGetRequestToHiddifyPannel($pannel->id, "/api/v2/admin/user/$uuid/");
            if (! is_array($body)) {
                return null;
            }

            $body['panel_type'] = 'hiddify';

            return $body;
        } catch (\Throwable $th) {
            \Log::error('resolveBoughtProductStatusFromServer: ' . $th->getMessage(), [
                'product_id' => $id,
            ]);

            return null;
        }
    }

    public function getBoughtProductsStatusFromServerById($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        if ($data == null) {
            return response()->json(null, 404);
        }

        $authUser = auth('sanctum')->user();
        if (
            $authUser
            && ($authUser->role ?? '') !== 'admin'
            && (string) $authUser->account_id !== (string) $data->account_id
        ) {
            return response()->json(false, 401);
        }

        $status = $this->resolveBoughtProductStatusFromServer($id);
        if ($status === null) {
            return response()->json(null, 404);
        }

        return response()->json($status, 200);
    }
    public function getBoughtProductsPannelLinkFromServerById($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $userId = auth('sanctum')->user()->account_id;

        if ($userId != $data->account_id || $data == null) {
            return response()->json(false, 401);
        }
        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

            if ($pannel && $pannel->type == 'sanaei') {
                $configs = json_decode($data->configs, true) ?? [];
                $links = $configs['links'] ?? [];
                return $links[0] ?? '';
            }

            if ($pannel && $pannel->isMarzbanCompatible()) {
                if (! empty($data->panel_link)) {
                    return $data->panel_link;
                }
                $mb = MarzbanPannelController::resolve($pannel);

                return $mb->getSubscriptionLink($pannel, $data->resolveMarzbanPanelUsername()) ?? '';
            }

            $hiddifcCntrl = new HiddifyPannelController();

            return $hiddifcCntrl->get_hiddify_subscription_link($pannel->user_link, $data->panel_link);
        } else {
            return null;
        }
    }
    public function renameHiddifyRemark(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();

        if ($data == null) {
            return response()->json(false, 500);
        }

        $userId = auth('sanctum')->user()->account_id;

        if ($userId != $data->account_id) {
            return response()->json(false, 401);
        }

        $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
        if ($pannel === null || ! $pannel->supportsRemarkRename()) {
            return response()->json(false, 400);
        }

        if ($pannel->type == 'sanaei') {
            $configs = json_decode($data->configs, true) ?? [];
            $uuid = $configs['uuid'] ?? null;
            if ($uuid == null) {
                return response()->json(false, 400);
            }
            $sn = new SanaeiPannelController();
            $client = $sn->findClientByUUID($pannel->id, $uuid);
            if (!$client) {
                return response()->json(false, 404);
            }
            $client['email'] = $request->name;
            $res = $sn->updateClient($pannel->id, $client['id'], $client);
            if ($res) {
                $data->remark = $request->name;
                $data->update();
                return response()->json(true, 200);
            }
            return response()->json(false, 400);
        }

        if ($pannel->type == 'hiddify') {
            $hiddifcCntrl = new HiddifyPannelController();
            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
            $req = new Request();
            $req->pannelID = $pannel->id;
            $req->name = $request->name;
            $req->uuid = $uuid;

            $updateRemark = $hiddifcCntrl->updateUserNameOfHiddifyPanelApi($req);
            if ($updateRemark['status'] == 200) {
                if ($updateRemark['msg'] !== 'ok') {
                    return response()->json(false, 401);
                }
                $data->remark = $request->name;
                $data->update();
                return response()->json(true, 200);
            }
            return response()->json(false, 401);
        }

        return response()->json(false, 400);
    }
    public function reChargeProductByAgentWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        $selectedPrCat = ProductCategory::find($data->product_categories_id);

        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }
        if ($selectedPrCat->is_active == false) {
            return response()->json(false, 500);
        }

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                ->where('user_id', $userID)
                ->first();
            // return $agentProduct;
            $productPrice = $agentProduct->price;
            $productPriceInDollar = $agentProduct->price_in_dollar;
            $accBlCtrl = new AccountBallanceController();
            if ($accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar)) {
                if ($pannel && $pannel->type == 'sanaei') {
                    $configs = json_decode($data->configs, true) ?? [];
                    $uuid = $configs['uuid'] ?? null;
                    if ($uuid == null) {
                        return response()->json(false, 400);
                    }
                    $sn = new SanaeiPannelController();
                    $res = $sn->updateLimits($pannel->id, $uuid, $selectedPrCat->expire_day, $selectedPrCat->volume);
                    if ($res->getStatusCode() == 200) {
                        $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                        $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                        $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');
                        return response()->json(true, 200);
                    }
                    return response()->json(false, 401);
                }
                if ($pannel && $pannel->isMarzbanCompatible()) {
                    $mb = MarzbanPannelController::resolve($pannel);
                    $res = $mb->updateLimits($pannel->id, $data->resolveMarzbanPanelUsername(), $selectedPrCat->expire_day, $selectedPrCat->volume);
                    if ($res) {
                        $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                        $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                        $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');

                        return response()->json(true, 200);
                    }

                    return response()->json(false, 401);
                }
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
                    $accBlCtrl->decUserAccuntBalance($accountID, $productPrice, $productPriceInDollar);
                    $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت شارژ بسته کم شد.", 'minus ballance');
                    $this->addNewBotLog('product', "$data->remark شارژ شد.", 'charge product');

                    return response()->json(true, 200);
                } else {
                    return response()->json(false, 401);
                }
            }
            return response()->json(false, 401);
        }
        return response()->json(false, 500);
    }
    public function changeProductByAgentWithPrID(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();

        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        $oldPrCat = ProductCategory::find($data->product_categories_id);
        $newPrCat = ProductCategory::find($request->newPrCatID);

        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }
        if ($oldPrCat === null || $newPrCat === null || $oldPrCat->is_active == false) {
            return response()->json(false, 500);
        }

        $volumeDelta = max(0, (float) $newPrCat->volume - (float) $oldPrCat->volume);
        $limitMessage = $this->checkAgentPurchaseLimits($accountID, $volumeDelta, 0);
        if ($limitMessage !== null) {
            return response()->json($limitMessage, 401);
        }

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                ->where('user_id', $userID)
                ->first();

            // $newAgentProduct = AgentProduct::find($request->newPrCatID);
            $newAgentProduct = AgentProduct::where('product_categories_id', $request->newPrCatID)
                ->where('user_id', $userID)
                ->first();

            // return $agentProduct;
            $oldProductPrice = $agentProduct->price;
            $oldProductPriceInDollar = $agentProduct->price_in_dollar;
            $productPrice = $newAgentProduct->price;
            $productPriceInDollar = $newAgentProduct->price_in_dollar;
            $accBlCtrl = new AccountBallanceController();
            if ($accBlCtrl->checkUserHasBalance($accountID, $productPrice, $productPriceInDollar)) {
                $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
                if ($pannel && $pannel->type == 'sanaei') {
                    $configs = json_decode($data->configs, true) ?? [];
                    $uuid = $configs['uuid'] ?? null;
                    if ($uuid == null) {
                        return response()->json('Bad Request', 400);
                    }
                    $sn = new SanaeiPannelController();
                    if ($request->recharge == true || $request->recharge == 1) {
                        $res = $sn->updateLimits($newPrCat->pannel_id, $uuid, $newPrCat->expire_day, $newPrCat->volume);
                        if (!$res) {
                            return response()->json(false, 401);
                        }
                        $this->addNewBotLog('product', "$data->remark توسط کاربر تغییر یافت.", 'charge product');
                        $diffInToman = $newPrCat->price - $oldPrCat->price;
                        $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                        if ($diffInToman > 0) {
                            $accBlCtrl->decUserAccuntBalance($accountID, $diffInToman, $dissInDollar);
                        }
                        $data->product_categories_id = $newPrCat->id;
                        $data->update();
                        return response()->json(true, 200);
                    }
                    $res = $sn->updateClientEmail($newPrCat->pannel_id, $uuid, $data->remark);
                    if ($res) {
                        $diffInToman = $newPrCat->price - $oldPrCat->price;
                        $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                        if ($diffInToman < 0) {
                            $accBlCtrl->decUserAccuntBalance($accountID, $diffInToman, $dissInDollar);
                        }
                        $data->product_categories_id = $newPrCat->id;
                        $data->update();
                        return response()->json(true, 200);
                    }
                    return response()->json(false, 401);
                }
                $hiddifcCntrl = new HiddifyPannelController();
                $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
                $day = $newPrCat->expire_day;
                $volume = $newPrCat->volume;

                $req = new Request();
                $req->pannelID = $newPrCat->pannel_id;
                $req->name = $data->remark;
                $req->uuid = $uuid;
                $req->vol = $volume;
                $req->day = $day;
                // get today date with new variable
                $today = Verta::now();
                if ($request->recharge == true || $request->recharge == 1) {
                    $req->comment = "تغییر دسته بندی همراه با ریست زمان و حجم {$today}";

                    $updateRemark = $hiddifcCntrl->rechargeUserOfHiddifyPanelApi($req);
                    if ($hiddifcCntrl->hiddifyMutationSucceeded($updateRemark)) {
                        $this->addNewBotLog('product', "$data->remark توسط کاربر تغییر یافت.", 'charge product');
                    } else {
                        return response()->json(false, 401);

                    }
                    // get difference between old and new price
                    $diffInToman = $newPrCat->price - $oldPrCat->price;
                    $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                    if ($diffInToman > 0) {
                        $accBlCtrl->decUserAccuntBalance($accountID, $diffInToman, $dissInDollar);
                    }
                    $data->product_categories_id = $newPrCat->id;
                    $data->update();
                    return response()->json(true, 200);
                }

                $req->comment = "تغییر دسته بندی  {$today}";

                $updateRemark = $hiddifcCntrl->upgradeUserOfHiddifyPanelApi($req);
                // $updateRemark = $hiddifcCntrl->upgradeUserOfHiddifyPanelOldApi($req);
                if ($updateRemark['status'] == 200) {
                    if ($updateRemark['msg'] !== 'ok') {
                        return response()->json(false, 401);
                    }
                    $this->addNewBotLog('product', "$data->remark توسط کاربر تغییر یافت.", 'charge product');
                }

                // get difference between old and new price
                $diffInToman = $newPrCat->price - $oldPrCat->price;
                $dissInDollar = $newPrCat->price_in_dollar - $oldPrCat->price_in_dollar;
                if ($diffInToman < 0) {
                    $accBlCtrl->decUserAccuntBalance($accountID, $diffInToman, $dissInDollar);
                }
                $data->product_categories_id = $newPrCat->id;
                $data->update();

                return response()->json(true, 200);
            }

            return response()->json('Low Ballance', 401);
        }

        return response()->json(false, 500);
    }
    public function changeActivationOfHiddifyUserByAgent(Request $request)
    {
        $data = Product::where('id', $request->id)
            ->with('product_category_and_panel')
            ->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;
        if ($accountID != $data->account_id) {
            return response()->json('This product is not yours', 401);
        }
        if ($data->deactive_by_admin == true) {
            return response()->json('This product is deactivated by admin', 401);
        }
        if ($data != null) {
            if ($data->product_category_and_panel->pannel->type == 'sanaei') {
                $sanaeiCntrl = new SanaeiPannelController();
                $req = new Request();
                $req->pannelID = $data->product_category_and_panel->pannel_id;
                $req->uuid = $data->uuid;
                $req->enable = ($request->enable == true || $request->enable == 1 || $request->enable == 'true');

                $res = $sanaeiCntrl->changeUserActivationOfSanaeiPanelApi($req);
                if ($res->getStatusCode() == 200) {
                    $status = $req->enable ? 'فعال' : 'غیر فعال';
                    $this->addNewBotLog('product', "$data->remark توسط کاربر {$status} شد.", 'change activation');
                    return response()->json(true, 200);
                }
                return response()->json(false, 401);
            }

            if ($data->product_category_and_panel->pannel->isMarzbanCompatible()) {
                $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
                $mb = MarzbanPannelController::resolve($pannel);
                $enable = ($request->enable == true || $request->enable == 1 || $request->enable == 'true');
                $res = $mb->changeUserActivation($pannel->id, $data->resolveMarzbanPanelUsername(), $enable);
                if ($res) {
                    $status = $enable ? 'فعال' : 'غیر فعال';
                    $this->addNewBotLog('product', "$data->remark توسط کاربر {$status} شد.", 'change activation');

                    return response()->json(true, 200);
                }

                return response()->json(false, 401);
            }

            $hiddifcCntrl = new HiddifyPannelController();
            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);

            $req = new Request();
            $req->pannelID = $data->product_category_and_panel->pannel_id;
            $req->uuid = $uuid;
            $today = Verta::now();

            if ($request->enable == true || $request->enable == 1 || $request->enable == 'true') {
                $req->comment = "فعال شدن بسته توسط کاربر در {$today}";
                $req->enable = true;
            } else {
                $req->comment = "غیر فعال شدن بسته توسط کاربر در {$today}";
                $req->enable = false;
            }
            // get today date with new variable

            $updateRemark = $hiddifcCntrl->changeUserActivationOfHiddifyPanelApi($req);
            if ($updateRemark->getStatusCode() == 200) {
                $this->addNewBotLog('product', "$data->remark توسط کاربر غیر فعال شد.", 'charge product');
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        }
        return response()->json($request->id, 404);
    }
    public function softDeleteProductByAgentWithPrID($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;

        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }

        if ($data != null) {
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);
            $currentUsage = 0;
            $currentStatus = $this->resolveBoughtProductStatusFromServer($id);
            if ($currentStatus != null) {
                $currentUsage = $currentStatus['current_usage_GB'];
            }

            if ($pannel && $pannel->type == 'sanaei') {
                $configs = json_decode($data->configs, true) ?? [];
                $uuid = $configs['uuid'] ?? null;
                if ($uuid == null) {
                    return response()->json(null, 400);
                }
                $sn = new SanaeiPannelController();
                $res = $sn->deleteUser($pannel->id, $uuid);
                if ($res->getStatusCode() == 200) {
                    $data->delete();
                    $this->addNewBotLog('product', "بسته $data->remark حذف شد.", 'remove product');
                    $agentPremissionCntrl = new AgentPermissonController();
                    $agentPr = $agentPremissionCntrl->getUserPremission();
                    if ($agentPr->delete_products == 1 || $agentPr->delete_products == true) {
                        if ($currentUsage < 0.5) {
                            $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                                ->where('user_id', $userID)
                                ->first();
                            $productPrice = $agentProduct->price;
                            $accBlCtrl = new AccountBallanceController();
                            $inc = $accBlCtrl->incUserAccuntBalance($accountID, $productPrice);
                            $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت حذف بسته کم حجم اضافه شد.", 'add ballance');
                            if ($inc == false) {
                                return response()->json(null, 500);
                            }
                        }
                    }
                    return response()->json(true, 200);
                }
                return response()->json(null, 500);
            }
            if ($pannel && $pannel->isMarzbanCompatible()) {
                $mb = MarzbanPannelController::resolve($pannel);
                $res = $mb->deleteUser($pannel->id, $data->resolveMarzbanPanelUsername());
                if ($res) {
                    $data->delete();
                    $this->addNewBotLog('product', "بسته $data->remark حذف شد.", 'remove product');
                    $agentPremissionCntrl = new AgentPermissonController();
                    $agentPr = $agentPremissionCntrl->getUserPremission();
                    if ($agentPr->delete_products == 1 || $agentPr->delete_products == true) {
                        if ($currentUsage < 0.5) {
                            $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                                ->where('user_id', $userID)
                                ->first();
                            $productPrice = $agentProduct->price;
                            $accBlCtrl = new AccountBallanceController();
                            $inc = $accBlCtrl->incUserAccuntBalance($accountID, $productPrice);
                            $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت حذف بسته کم حجم اضافه شد.", 'add ballance');
                            if ($inc == false) {
                                return response()->json(null, 500);
                            }
                        }
                    }

                    return response()->json(true, 200);
                }

                return response()->json(null, 500);
            }

            $hiddifcCntrl = new HiddifyPannelController();
            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);
            $updateRemark = $hiddifcCntrl->deleteUserOfHiddifyPanel($pannel->id, $uuid);
            if ($updateRemark->getStatusCode() == 200) {
                $data->delete();
                $this->addNewBotLog('product', "بسته $data->remark حذف شد.", 'remove product');
                $agentPremissionCntrl = new AgentPermissonController();
                $agentPr = $agentPremissionCntrl->getUserPremission();
                if ($agentPr->delete_products == 1 || $agentPr->delete_products == true) {
                    if ($currentUsage < 0.5) {
                        $agentProduct = AgentProduct::where('product_categories_id', $data->product_category_and_panel->id)
                            ->where('user_id', $userID)
                            ->first();
                        $productPrice = $agentProduct->price;
                        $accBlCtrl = new AccountBallanceController();
                        $inc = $accBlCtrl->incUserAccuntBalance($accountID, $productPrice);
                        $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت حذف بسته کم حجم اضافه شد.", 'add ballance');
                        if ($inc == false) {
                            return response()->json(null, 500);
                        }
                    }
                }
                return response()->json(true, 200);
            }
            return response()->json(null, 500);
        }
        return response()->json(false, 401);
    }
    public function softDeleteProductByUserWithPrID($id)
    {
        $data = Product::where('id', $id)->with('product_category_and_panel')->first();
        $accountID = auth('sanctum')->user()->account_id;
        $userID = auth('sanctum')->user()->id;

        if ($accountID != $data->account_id) {
            return response()->json(false, 401);
        }

        if ($data != null) {
            // get pannel url
            $pannel = Pannel::find($data->product_category_and_panel->pannel_id);

            if ($pannel && $pannel->type == 'sanaei') {
                $configs = json_decode($data->configs, true) ?? [];
                $uuid = $configs['uuid'] ?? null;
                if ($uuid == null) {
                    return response()->json(null, 400);
                }
                $sn = new SanaeiPannelController();
                $res = $sn->deleteUser($pannel->id, $uuid);
                if ($res->getStatusCode() == 200) {
                    $data->delete();
                    $this->addNewBotLog('product', "بسته $data->remark حذف شد.", 'remove product');
                    return response()->json(true, 200);
                }
                return response()->json(null, 500);
            }

            if ($pannel && $pannel->isMarzbanCompatible()) {
                $mb = MarzbanPannelController::resolve($pannel);
                $res = $mb->deleteUser($pannel->id, $data->resolveMarzbanPanelUsername());
                if ($res) {
                    $data->delete();
                    $this->addNewBotLog('product', "بسته $data->remark حذف شد.", 'remove product');

                    return response()->json(true, 200);
                }

                return response()->json(null, 500);
            }

            $hiddifcCntrl = new HiddifyPannelController();

            $uuid = $hiddifcCntrl->extractUUID($data->subscription_link);

            $req = new Request();
            $req->pannelID = $pannel->id;
            $req->name = $data->remark;
            $req->uuid = $uuid;
            $req->vol = 0.0;
            $req->day = 0;
            // get today date with new variable
            $today = Verta::now();
            $req->comment = "حذف شده در {$today}";

            $updateRemark = $hiddifcCntrl->deleteUserOfHiddifyPanel($pannel->id, $uuid);
            if ($updateRemark->getStatusCode() == 200) {
                $data->delete();
                $this->addNewBotLog('product', "بسته $data->remark حذف شد.", 'remove product');

                return response()->json(true, 200);
            } else {
                return response()->json(null, 500);
            }
        }
        return response()->json(false, 401);
    }
    public function addNewBotLog($type, $message, $event)
    {
        $accountID = auth('sanctum')->user()->account_id;
        $name = auth('sanctum')->user()->name;

        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $accountID, $name, $event);
        return true;
    }
}
