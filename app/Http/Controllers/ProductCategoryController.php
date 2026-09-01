<?php

namespace App\Http\Controllers;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Services\LicenseFeatureService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProductCategoryController extends Controller
{
    private function normalizeInboundIds(Request $request): ?array
    {
        if ($request->has('inbound_ids')) {
            $raw = $request->input('inbound_ids');
            if ($raw === null || $raw === '' || $raw === []) {
                return null;
            }

            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $raw = $decoded;
                } else {
                    $parts = preg_split('/[,; ]+/', trim($raw));
                    $raw = array_filter($parts ?? [], fn ($part) => $part !== '');
                }
            }

            if (! is_array($raw)) {
                return null;
            }

            $ids = [];
            foreach ($raw as $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $ids[] = (int) $value;
            }

            $ids = array_values(array_unique($ids));

            return $ids === [] ? null : $ids;
        }

        if ($request->filled('inbound_id')) {
            return [(int) $request->inbound_id];
        }

        return null;
    }

    private function applyInboundFields(ProductCategory $data, Request $request): void
    {
        $inboundIds = $this->normalizeInboundIds($request);
        $data->inbound_id = $inboundIds[0] ?? null;

        if (Schema::hasColumn('product_categories', 'inbound_ids')) {
            $data->inbound_ids = $inboundIds;
        }

        if ($inboundIds !== null && count($inboundIds) > 1) {
            $data->sample_inbound = $this->mergeInboundIdsIntoSampleInbound(
                $data->sample_inbound,
                $inboundIds
            );
        }
    }

    /**
     * @param  int[]  $inboundIds
     */
    private function mergeInboundIdsIntoSampleInbound(mixed $sampleInbound, array $inboundIds): string
    {
        $metaLine = '__INBOUND_IDS__:' . json_encode(array_values($inboundIds));
        $sample = is_string($sampleInbound) ? $sampleInbound : '';

        if ($sample === '') {
            return $metaLine;
        }

        if (str_starts_with($sample, '__INBOUND_IDS__:')) {
            $parts = explode("\n", $sample, 2);

            return $metaLine . (isset($parts[1]) ? "\n" . $parts[1] : '');
        }

        return $metaLine . "\n" . $sample;
    }

    private function normalizeMarzbanInbounds(Request $request): ?array
    {
        if (! $request->has('marzban_inbounds')) {
            return null;
        }

        $raw = $request->input('marzban_inbounds');
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            } else {
                return null;
            }
        }

        if (! is_array($raw)) {
            return null;
        }

        $result = [];
        foreach ($raw as $protocol => $tags) {
            $protocolKey = strtolower((string) $protocol);
            if ($protocolKey === '' || ! is_array($tags)) {
                continue;
            }
            $normalizedTags = [];
            foreach ($tags as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $normalizedTags[] = $tag;
                }
            }
            if ($normalizedTags !== []) {
                $result[$protocolKey] = array_values(array_unique($normalizedTags));
            }
        }

        return $result === [] ? null : $result;
    }

    private function applyMarzbanInboundFields(ProductCategory $data, Request $request): void
    {
        $marzbanInbounds = $this->normalizeMarzbanInbounds($request);
        if ($marzbanInbounds !== null || $request->has('marzban_inbounds')) {
            if (Schema::hasColumn('product_categories', 'marzban_inbounds')) {
                $data->marzban_inbounds = $marzbanInbounds;
            }
        }
    }

    private function normalizePasarguardGroupIds(Request $request): ?array
    {
        if (! $request->has('pasarguard_group_ids')) {
            return null;
        }

        $raw = $request->input('pasarguard_group_ids');
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            } else {
                $parts = preg_split('/[,; ]+/', trim($raw));
                $raw = array_filter($parts ?? [], fn ($part) => $part !== '');
            }
        }

        if (! is_array($raw)) {
            return null;
        }

        $ids = [];
        foreach ($raw as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $ids[] = (int) $value;
        }

        $ids = array_values(array_unique($ids));

        return $ids === [] ? null : $ids;
    }

    private function applyPasarguardGroupFields(ProductCategory $data, Request $request): void
    {
        $groupIds = $this->normalizePasarguardGroupIds($request);
        if ($groupIds !== null || $request->has('pasarguard_group_ids')) {
            if (Schema::hasColumn('product_categories', 'pasarguard_group_ids')) {
                $data->pasarguard_group_ids = $groupIds;
            }
        }
    }

    private function normalizeAllowedUserGroupIds(Request $request): ?array
    {
        if (! $request->has('allowed_user_group_ids')) {
            return null;
        }

        $raw = $request->input('allowed_user_group_ids');
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $raw = $decoded;
            } else {
                $parts = preg_split('/[,; ]+/', trim($raw));
                $raw = array_filter($parts ?? [], fn ($part) => $part !== '');
            }
        }

        if (! is_array($raw)) {
            return null;
        }

        $ids = [];
        foreach ($raw as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $ids[] = (int) $value;
        }

        $ids = array_values(array_unique($ids));

        return $ids === [] ? null : $ids;
    }

    public function getAllProdctCategory()
    {
        return ProductCategory::with('pannel')->orderBy('created_at')->get();
    }
    public function getProdctCategoryNameByID($id)
    {
        return ProductCategory::where('id', $id)->first();
    }
    public function deleteProductCategoryByID($id)
    {
        // delete productCategory by id with cascade if have not any product relation

        $data = ProductCategory::where('id', $id)->first();
        if ($data != null) {
            if ($data->delete() != null) {
                return $this->getAllProdctCategory();
            } else {
                return response()->json(false, 404);
            }
        }
        return response()->json(false, 404);
    }
    public function getProdctCategoryByCategoryName($categoryName)
    {
        return ProductCategory::where('category_name', $categoryName)->first();
    }
    public function getAllProdctCategoryOrderByPrice()
    {
        return ProductCategory::orderBy('price')->get();
    }
    public function getAllActiveProdctCategoryOrderByPrice(?int $userGroupId = null, bool $filterByUserGroup = false)
    {
        $panelCntrl = new PannelController();
        $panels = $panelCntrl->get_all_panells_Id_by_location_capacity_mode();
        $categories = ProductCategory::orderBy('price')->where('is_active', true)
            ->where('category_name', '!=', 'اکانت آزمایشی')
            ->whereIn('pannel_id', $panels)
            ->get();

        if (! $filterByUserGroup) {
            return $categories;
        }

        return $categories
            ->filter(fn (ProductCategory $category) => $category->isAllowedForUserGroup($userGroupId))
            ->values();
    }
    public function get_all_active_prodct_category_by_pannel_id_order_by_price($pannel_id, ?int $userGroupId = null, bool $filterByUserGroup = false)
    {

        $categories = ProductCategory::where('pannel_id', $pannel_id)
            ->where('category_name', '!=', 'اکانت آزمایشی')
            ->orderBy('price')->where('is_active', true)->get();

        if (! $filterByUserGroup) {
            return $categories;
        }

        return $categories
            ->filter(fn (ProductCategory $category) => $category->isAllowedForUserGroup($userGroupId))
            ->values();
    }
    public function getProdctPannelID($name, $pannel_id)
    {
        $data = ProductCategory::where('pannel_id', $pannel_id)->where('category_name', $name)->first();
        if ($data != null) {
            return $data->id;
        } else {
            return -1;
        }
    }
    public function getProductCatIdBYExpireDayPannelIDVolume($expire_day, $pannel_id, $volume)
    {
        $data = ProductCategory::where('expire_day', $expire_day)->where('pannel_id', $pannel_id)->where('volume', $volume)->first();
        if ($data == null) {
            // create a new category with this expire_day and pannel_id and volume
            $data = new ProductCategory();
            $data->pannel_id = $pannel_id;
            $data->expire_day = $expire_day;
            $data->volume = $volume;
            $data->category_name = 'یک دسته بندی جدید ';
            $data->price = 0;
            $data->price_in_dollar = 0;
            $data->is_active = true;
            $data->rechargable = true;
            $data->show_subscription_link = true;
            $data->show_pannel_link = true;
            $data->send_config_to_user = true;

            $data->save();
            $id = $data->id;
        }

        return $data->id;
    }
    public function addNewProductCategory(Request $request)
    {
        try {
            $data = new ProductCategory();
            $data->pannel_id = $request->pannel_id;
            $data->category_name = $request->category_name;
            $data->price = $request->price;
            $data->expire_day = $request->expire_day;
            $data->volume = $request->volume;
            $data->rechargable = $request->rechargable;
            $data->show_subscription_link = $request->show_subscription_link;
            $data->show_pannel_link = $request->show_pannel_link;
            $data->send_config_to_user = $request->boolean('send_config_to_user', true);
            $data->sample_inbound = $request->sample_inbound;
            $this->applyInboundFields($data, $request);
            $this->applyMarzbanInboundFields($data, $request);
            $this->applyPasarguardGroupFields($data, $request);
            $data->ip_limit = $request->ip_limit ?? 0;
            if ($request->price_in_dollar != null && $request->price_in_dollar >= 0.00) {
                $data->price_in_dollar = $request->price_in_dollar;
            } else {
                $data->price_in_dollar = 0.0;
            }
            $data->is_active = true;
            $allowedGroupIds = $this->normalizeAllowedUserGroupIds($request);
            if ($allowedGroupIds !== null || $request->has('allowed_user_group_ids')) {
                $data->allowed_user_group_ids = $allowedGroupIds;
            }
            if ($request->has('upsell_category_id')) {
                $upsellId = $request->upsell_category_id;
                if ((new LicenseFeatureService())->isGold()) {
                    $data->upsell_category_id = ($upsellId === '' || $upsellId === '0' || $upsellId === 0)
                        ? null
                        : (int) $upsellId;
                }
            }
            if ($data->save()) {
                return $this->getAllProdctCategory();
            }

            return false;
        } catch (\Throwable $e) {
            \Log::error('addNewProductCategory error: ' . $e->getMessage());

            return false;
        }
    }
    public function editProductCategory(Request $request)
    {
        try {
            $data = ProductCategory::find($request->id);
            $data->pannel_id = $request->pannel_id;
            $data->category_name = $request->category_name;
            $data->price = $request->price;
            $data->expire_day = $request->expire_day;
            $data->volume = $request->volume;
            $data->rechargable = $request->rechargable;
            $data->show_subscription_link = $request->show_subscription_link;
            $data->show_pannel_link = $request->show_pannel_link;
            $data->send_config_to_user = $request->boolean('send_config_to_user', true);
            $data->sample_inbound = $request->sample_inbound;
            $this->applyInboundFields($data, $request);
            $this->applyMarzbanInboundFields($data, $request);
            $this->applyPasarguardGroupFields($data, $request);
            \Log::info("sample_inbound", [$request->sample_inbound]);
            $data->ip_limit = $request->ip_limit ?? 0;
            $data->is_active = $request->is_active;
            if ($request->price_in_dollar != null && $request->price_in_dollar >= 0.00) {
                $data->price_in_dollar = $request->price_in_dollar;
            } else {
                $data->price_in_dollar = 0.0;
            }

            $allowedGroupIds = $this->normalizeAllowedUserGroupIds($request);
            if ($allowedGroupIds !== null || $request->has('allowed_user_group_ids')) {
                $data->allowed_user_group_ids = $allowedGroupIds;
            }

            if ($request->has('upsell_category_id')) {
                $upsellId = $request->upsell_category_id;
                if ((new LicenseFeatureService())->isGold()) {
                    $data->upsell_category_id = ($upsellId === '' || $upsellId === '0' || $upsellId === 0)
                        ? null
                        : (int) $upsellId;
                }
            }

            if ($data->update()) {
                return response()->json($this->getAllProdctCategory(), 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            \Log::error('editProductCategory error: ' . $th->getMessage());

            return response()->json(false, 500);
        }
    }
    public function editProductCategoryByName(Request $request)
    {
        try {
            $data = ProductCategory::where('category_name', $request->category_name)->first();
            $data->price = $request->price;
            $data->expire_day = $request->expire_day;
            $data->volume = $request->volume;
            $data->rechargable = $request->rechargable;
            $data->show_subscription_link = $request->show_subscription_link;
            $data->show_pannel_link = $request->show_pannel_link;
            $data->send_config_to_user = $request->boolean('send_config_to_user', true);
            $data->is_active = $request->is_active;
            $data->sample_inbound = $request->sample_inbound;

            if ($data->update()) {
                return true;
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function getProdctPrice($name, $servicetypeID)
    {
        $data = ProductCategory::where('pannel_id', $servicetypeID)->where('category_name', $name)->first();
        if ($data != null) {
            return $data->price;
        } else {
            return -1;
        }
    }
    public function reActiveProductCategory($id)
    {
        try {
            $data = ProductCategory::find($id);
            $data->is_active = true;
            if ($data->update()) {
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function deActiveProductCategory($id)
    {
        try {
            $data = ProductCategory::find($id);
            $data->is_active = false;
            if ($data->update()) {
                return response()->json(true, 200);
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function mostSelledProductCategory($count)
    {
        try {
            $data = Product::leftJoin('product_categories', 'products.product_categories_id', '=', 'product_categories.id')->where('product_categories.is_active', true)->groupBy('product_categories.category_name')->select('product_categories.category_name', \DB::raw('count(*) as count'))->orderBy('count', 'desc')->take($count)->get();

            // $data = ProductCategory::where('is_active', true)
            // ->leftJoin('products', 'products.product_categories_id', '=', 'product_categories.id')
            // ->groupBy('product_categories.category_name')
            // ->select('product_categories.category_name', \DB::raw('count(*) as count'))
            // ->orderBy('count', 'desc')
            // ->take($count)->get();
            if ($data != null) {
                return $data;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            \Log::info('error: ' . $th);
            return null;
        }
    }
    public function getAgentProductsNotSelectedByUserID($userID)
    {
        try {
            return ProductCategory::whereDoesntHave('agent_products', function ($query) use ($userID) {
                $query->where('agent_products.user_id', '=', $userID);
            })->get();
            // $selected =  ProductCategory::with('agent_products')
            // ->whereHas('agent_products', function ($query) use($userID) {
            //     $query->where('agent_products.user_id', '=', $userID);
            // })->get();

            // return response()->json([ 'selected'=> $selected,'not_selected'=> $not_selected], 200);
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(null, 500);
        }
    }
}
