<?php

namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\BotUser;
use App\Models\Pannel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// use carbon
use Carbon\Carbon;

class ProductController extends Controller
{
    public function getProductConfigAndChangeStatus($selectedProductCatID, $userID)
    {
        return DB::transaction(function () use ($selectedProductCatID, $userID) {
            $data = Product::query()
                ->where('product_categories_id', $selectedProductCatID)
                ->where('isActive', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($data === null) {
                return null;
            }

            $data->isActive = false;
            $data->account_id = $userID;
            $data->save();

            return $data;
        });
    }

    public function countActiveInventory(int $categoryId): int
    {
        return Product::query()
            ->where('product_categories_id', $categoryId)
            ->where('isActive', true)
            ->count();
    }

    public function releaseInventoryProduct(int $productId): bool
    {
        $product = Product::find($productId);
        if ($product === null) {
            return false;
        }

        $product->isActive = true;
        $product->account_id = null;

        return $product->save();
    }

    public function getProductConfigById($id, $userID)
    {
        $data = Product::where('id', $id)->where('account_id', $userID)->with('product_category')->first();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function getUserProductsHistoryByAccountID($userID)
    {
        $data = Product::where('account_id', $userID)
            ->where('remark', '!=', 'pending')
            ->with('product_category')
            ->orderBy('id', 'desc')
            ->get();

        return $data->isEmpty() ? null : $data;
    }
    /**
     * Preview products that no longer exist on their panel (no deletion).
     */
    public function previewMissingUserProductsOnPanels($userID)
    {
        try {
            $missing = $this->findMissingProductsOnPanels((int) $userID);
            if ($missing === null) {
                return response()->json(false, 404);
            }

            return response()->json(['missing' => $missing], 200);
        } catch (\Throwable $th) {
            \Log::info("previewMissingUserProductsOnPanels: $th");

            return response()->json('Server Error', 500);
        }
    }

    /**
     * Delete selected products that are missing on their panels.
     */
    public function deleteSelectedMissingUserProducts(Request $request)
    {
        try {
            $validated = $request->validate([
                'bot_user_id' => 'required|integer|exists:bot_users,id',
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'integer',
            ]);

            $botUser = BotUser::find($validated['bot_user_id']);
            if (! $botUser) {
                return response()->json(false, 404);
            }

            $requestedIds = array_values(array_unique(array_map('intval', $validated['product_ids'])));
            $products = Product::where('account_id', $botUser->account_id)
                ->whereIn('id', $requestedIds)
                ->with('product_category_and_panel')
                ->get();

            $unreachablePanels = [];
            $toDelete = [];
            foreach ($products as $product) {
                $category = $product->product_category_and_panel;
                if (! $category || ! $category->pannel_id) {
                    continue;
                }

                $pannel = Pannel::find($category->pannel_id);
                if (! $pannel) {
                    continue;
                }

                $check = $this->checkProductMissingOnPanel($product, $pannel, $unreachablePanels);
                if (is_array($check)) {
                    $toDelete[] = $product->id;
                }
            }

            if ($toDelete !== []) {
                Product::where('account_id', $botUser->account_id)
                    ->whereIn('id', $toDelete)
                    ->delete();
            }

            return response()->json([
                'deleted' => count($toDelete),
                'deleted_ids' => $toDelete,
            ], 200);
        } catch (\Throwable $th) {
            \Log::info("deleteSelectedMissingUserProducts: $th");

            return response()->json('Server Error', 500);
        }
    }

    /**
     * Legacy sync: delete all products missing on panels (all supported panel types).
     */
    public function syncUserProductsHistoryByAccountIDwithPanels($userID)
    {
        try {
            $missing = $this->findMissingProductsOnPanels((int) $userID);
            if ($missing === null) {
                return response()->json(false, 404);
            }

            $ids = array_column($missing, 'id');
            if ($ids !== []) {
                $botUser = BotUser::find($userID);
                Product::where('account_id', $botUser->account_id)
                    ->whereIn('id', $ids)
                    ->delete();
            }

            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function findMissingProductsOnPanels(int $userID): ?array
    {
        $botUser = BotUser::find($userID);
        if (! $botUser) {
            return null;
        }

        $products = Product::where('account_id', $botUser->account_id)
            ->with('product_category_and_panel')
            ->get();

        $unreachablePanels = [];
        $missing = [];
        foreach ($products as $product) {
            $category = $product->product_category_and_panel;
            if (! $category || ! $category->pannel_id) {
                continue;
            }

            $pannel = Pannel::find($category->pannel_id);
            if (! $pannel) {
                continue;
            }

            $check = $this->checkProductMissingOnPanel($product, $pannel, $unreachablePanels);
            if ($check === false || $check === null) {
                continue;
            }

            $missing[] = [
                'id' => $product->id,
                'remark' => $product->remark,
                'category_name' => $category->category_name ?? null,
                'panel_type' => $pannel->type,
                'panel_location' => $pannel->location,
                'subscription_link' => $product->subscription_link,
                'reason' => $check['reason'] ?? 'not_found',
            ];
        }

        return $missing;
    }

    /**
     * @param  array<int, true>  $unreachablePanels
     * @return array{reason: string}|false|null false=exists, null=skip, array=missing
     */
    private function checkProductMissingOnPanel(Product $product, Pannel $pannel, array &$unreachablePanels): array|false|null
    {
        if ($pannel->isInventoryPanel()) {
            return null;
        }

        if (isset($unreachablePanels[$pannel->id])) {
            return ['reason' => 'panel_unreachable'];
        }

        try {
            if ($pannel->type === Pannel::TYPE_SANAEI) {
                $configs = json_decode($product->configs, true) ?? [];
                $uuid = $configs['uuid'] ?? null;
                if ($uuid === null || $uuid === '') {
                    return null;
                }

                $sn = new SanaeiPannelController();
                $found = $sn->findClientByUUID($pannel, $uuid);

                return $found ? false : ['reason' => 'not_found'];
            }

            if ($pannel->isMarzbanCompatible()) {
                $username = $product->resolveMarzbanPanelUsername();
                if ($username === '') {
                    return null;
                }

                $mb = MarzbanPannelController::resolve($pannel);
                $user = $mb->getUser($pannel, $username);

                return $user ? false : ['reason' => 'not_found'];
            }

            if ($pannel->type === Pannel::TYPE_HIDDIFY) {
                $hiddifcCntrl = new HiddifyPannelController();
                $uuid = $hiddifcCntrl->extractUUID($product->subscription_link ?? '');
                if ($uuid === null || $uuid === '') {
                    return null;
                }

                $url = $hiddifcCntrl->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
                if ($url === '' || $url === null) {
                    return null;
                }
                $url = "{$url}/api/v2/admin/user/$uuid";

                $subsequentResponse = Http::timeout(15)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Hiddify-API-Key' => $pannel->secret_code,
                ])->get($url);

                $statusCode = $subsequentResponse->status();
                if ($statusCode === 404) {
                    return ['reason' => 'not_found'];
                }
                if ($statusCode === 200) {
                    return false;
                }

                // Auth/server errors on a reachable panel: skip
                return null;
            }

            return null;
        } catch (\Throwable $th) {
            if ($this->isPanelConnectivityFailure($th)) {
                $unreachablePanels[$pannel->id] = true;
                \Log::info('checkProductMissingOnPanel panel unreachable', [
                    'panel_id' => $pannel->id,
                    'product_id' => $product->id,
                    'error' => $th->getMessage(),
                ]);

                return ['reason' => 'panel_unreachable'];
            }

            \Log::info('checkProductMissingOnPanel unexpected error: ' . $th->getMessage(), [
                'panel_id' => $pannel->id,
                'product_id' => $product->id,
            ]);

            return null;
        }
    }

    private function isPanelConnectivityFailure(\Throwable $th): bool
    {
        if ($th instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }

        if ($th instanceof \GuzzleHttp\Exception\ConnectException) {
            return true;
        }

        $message = $th->getMessage();

        return str_contains($message, 'cURL error')
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'Connection timed out')
            || str_contains($message, 'Failed to connect')
            || str_contains($message, 'Could not resolve host')
            || str_contains($message, 'SSL_ERROR')
            || str_contains($message, 'SSL connect error');
    }
    public function getUserProductsHistoryByUserIDWithPagination($userId)
    {
        try {
            $botUser = BotUser::where('id', $userId)->first();
            $accountID = $botUser->account_id;
            $data = Product::where('account_id', $accountID)
                ->with('product_category.pannel')
                ->paginate(10, ['*'], 'page');
            return $data;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getActiveProductsByProductCatID($selectedProductCatID)
    {
        $data = Product::where('product_categories_id', $selectedProductCatID)->where('isActive', true)->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
    public function addNewProductDetails(Request $request)
    {
        $data = new Product();
        $data->product_categories_id = $request->product_categories_id;
        $data->configs = $request->configs;
        $data->subscription_link = $request->subscription_link;
        $data->panel_link = $request->panel_link;
        $data->remark = $request->remark;
        $data->deactive_by_admin = false;
        if ($data->save()) {
            return $this->getActiveProductsByProductCatID($request->product_categories_id);
        } else {
            return false;
        }
    }
    public function addOrUpdateProductDetailsBySubscriptionLink(Request $request)
    {
        $data = Product::where('subscription_link', $request->subscription_link)->first();
        if ($data != null) {
            $data->account_id = $request->account_id;
            $data->update();

            return true;
        }

        $data = new Product();
        $data->product_categories_id = $request->product_categories_id;
        $data->configs = $request->configs;
        $data->subscription_link = $request->subscription_link;
        $data->panel_link = $request->panel_link;
        $data->isActive = false;
        $data->account_id = $request->account_id;
        $data->remark = $request->remark;
        $data->deactive_by_admin = false;
        if ($data->save()) {
            return true;
        } else {
            return false;
        }
    }
    public function reserveProductId(int|string $accountId, int $categoryId): ?int
    {
        $data = new Product();
        $data->product_categories_id = $categoryId;
        $data->account_id = $accountId;
        $data->remark = 'pending';
        $data->isActive = false;
        $data->deactive_by_admin = false;
        $data->configs = '';
        $data->subscription_link = '';
        $data->panel_link = '';

        if (! $data->save()) {
            return null;
        }

        return (int) $data->id;
    }

    public function deletePendingProduct(int $productId): bool
    {
        return Product::query()
            ->where('id', $productId)
            ->where('remark', 'pending')
            ->delete() > 0;
    }

    public function addAutomatedProductDetails(Request $request)
    {
        if (! empty($request->product_id)) {
            $data = Product::find($request->product_id);
            if (! $data) {
                return false;
            }
        } else {
            $data = new Product();
            $data->isActive = false;
            $data->deactive_by_admin = false;
        }

        $data->product_categories_id = $request->product_categories_id;
        $data->configs = $request->configs;
        $data->subscription_link = $request->subscription_link;
        $data->panel_link = $request->panel_link;
        $data->account_id = $request->account_id;
        $data->remark = $request->remark;

        if ($data->save()) {
            return $this->getActiveProductsByProductCatID($request->product_categories_id);
        }

        return false;
    }
    public function getLastInsertedProductId()
    {
        $data = Product::orderBy('id', 'desc')->first();
        if ($data != null) {
            return $data->id;
        } else {
            return 1;
        }
    }
    public function deleteProduct($id)
    {
        try {
            $data = Product::find($id);
            if ($data != null) {
                $catId = $data->product_categories_id;
                $data->delete();

                return $this->getActiveProductsByProductCatID($catId);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function delete_product_by_uuid($uuid)
    {
        try {
            $subscriptionLink = "/{$uuid}/all.txt?name=sublink-unknown&asn=unknown&mode=new";
            $data = Product::where('subscription_link', $subscriptionLink)->first();
            if ($data != null) {
                $data->delete();
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
            return response()->json(false, 500);
        }
    }

    public function delete_sanaei_product_by_uuid($uuid)
    {
        try {
            // Search for Sanaei products by UUID in configs field
            $data = Product::where('configs', 'like', '%"uuid":"' . $uuid . '"%')->first();
            if ($data != null) {
                $data->delete();
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
            return response()->json(false, 500);
        }
    }

    public function delete_marzban_product_by_username($username)
    {
        try {
            $data = Product::where('remark', $username)->first();
            if ($data != null) {
                $data->delete();

                return true;
            }

            return false;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return false;
        }
    }

    public function deleteProductByProductID($id)
    {
        try {
            $data = Product::where('id', $id)->first();
            if ($data != null) {
                $data->delete();
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function getLastBuyersByCatIdAndCount($product_categories_id, $count)
    {
        try {
            $data = Product::where('product_categories_id', $product_categories_id)->where('isActive', false)->with('user')->orderBy('id', 'desc')->take($count)->get();
            if ($data != null) {
                return response()->json($data, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }

    public function getCountOfProductSelledSummeryByCatID($product_categories_id)
    {
        try {
            $last30 = $this->getCountOfSellProductBYCatIdAndMonth($product_categories_id, 1);
            $last90 = $this->getCountOfSellProductBYCatIdAndMonth($product_categories_id, 3);
            $last180 = $this->getCountOfSellProductBYCatIdAndMonth($product_categories_id, 6);
            $last365 = $this->getCountOfSellProductBYCatIdAndMonth($product_categories_id, 12);
            return response()->json(['ماه گذشته' => $last30, 'سه ماه گذشته' => $last90, 'شش ماه گذشته' => $last180, 'یکسال گذشته' => $last365], 200);
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function getCountOfSellProductBYCatIdAndMonth($product_categories_id, $month)
    {
        // get count of Product in last month  by id
        $fromDate = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $tillDate = Carbon::now()->subMonth()->endOfMonth($month)->toDateString();

        $data = Product::where('product_categories_id', $product_categories_id)
            ->whereBetween('updated_at', [$fromDate, $tillDate])
            ->count();
        return $data;
    }
    public function getLastProductSelled($count)
    {
        $data = Product::with(['user', 'product_category'])
            ->where('remark', '!=', 'pending')
            ->orderBy('id', 'desc')
            ->take($count)
            ->get();
        return $data;
    }

}
