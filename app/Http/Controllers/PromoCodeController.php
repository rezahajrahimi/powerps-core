<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Services\LicenseFeatureService;
use App\Services\PromoCodeService;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function __construct(private LicenseFeatureService $license) {}

    public function index()
    {
        if ($this->license->isBronzeOrBelow()) {
            return $this->license->silverRequiredResponse();
        }

        return response()->json(PromoCode::orderByDesc('id')->get());
    }

    public function store(Request $request)
    {
        if ($this->license->isBronzeOrBelow()) {
            return $this->license->silverRequiredResponse();
        }

        if (! $this->license->isGold() && PromoCode::count() >= LicenseFeatureService::SILVER_PROMO_MAX) {
            return response()->json([
                'message' => 'در لایسنس نقره‌ای حداکثر ' . LicenseFeatureService::SILVER_PROMO_MAX . ' کد تخفیف مجاز است.',
            ], 403);
        }

        $allowedTypes = $this->license->isGold()
            ? 'percent,fixed_toman,fixed_dollar'
            : 'percent,fixed_toman';

        $data = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code',
            'type' => 'required|in:' . $allowedTypes,
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'min_order_amount' => 'nullable|numeric|min:0',
            'allowed_category_ids' => 'nullable|array',
            'allowed_user_group_ids' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if (! $this->license->isGold()) {
            unset($data['allowed_category_ids'], $data['allowed_user_group_ids']);
        }

        $data['code'] = strtoupper(trim($data['code']));
        if (($data['type'] ?? '') === 'percent' && (float) $data['value'] > 100) {
            return response()->json([
                'message' => 'درصد تخفیف باید بین ۰ تا ۱۰۰ باشد.',
                'errors' => ['value' => ['درصد تخفیف باید بین ۰ تا ۱۰۰ باشد.']],
            ], 422);
        }
        $promo = PromoCode::create($data);

        return response()->json($promo, 201);
    }

    public function update(Request $request, int $id)
    {
        if ($this->license->isBronzeOrBelow()) {
            return $this->license->silverRequiredResponse();
        }

        $promo = PromoCode::findOrFail($id);
        $allowedTypes = $this->license->isGold()
            ? 'percent,fixed_toman,fixed_dollar'
            : 'percent,fixed_toman';

        $data = $request->validate([
            'code' => 'sometimes|string|max:50|unique:promo_codes,code,' . $id,
            'type' => 'sometimes|in:' . $allowedTypes,
            'value' => 'sometimes|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'min_order_amount' => 'nullable|numeric|min:0',
            'allowed_category_ids' => 'nullable|array',
            'allowed_user_group_ids' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if (! $this->license->isGold()) {
            unset($data['allowed_category_ids'], $data['allowed_user_group_ids']);
        }

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $type = $data['type'] ?? $promo->type;
        if ($type === 'percent' && array_key_exists('value', $data) && (float) $data['value'] > 100) {
            return response()->json([
                'message' => 'درصد تخفیف باید بین ۰ تا ۱۰۰ باشد.',
                'errors' => ['value' => ['درصد تخفیف باید بین ۰ تا ۱۰۰ باشد.']],
            ], 422);
        }

        $promo->update($data);

        return response()->json($promo);
    }

    public function destroy(int $id)
    {
        if ($this->license->isBronzeOrBelow()) {
            return $this->license->silverRequiredResponse();
        }

        PromoCode::findOrFail($id)->delete();

        return response()->json(true);
    }

    public function usages(Request $request, int $id)
    {
        if (! $this->license->isGold()) {
            return $this->license->goldRequiredResponse();
        }

        PromoCode::findOrFail($id);

        $paginated = PromoCodeUsage::paginateForPromo(
            $id,
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 15)
        );

        return response()->json([
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function validateCode(Request $request, PromoCodeService $service)
    {
        $request->validate([
            'code' => 'required|string',
            'account_id' => 'required|string',
            'category_id' => 'required|integer',
            'price_toman' => 'required|numeric',
            'price_dollar' => 'nullable|numeric',
        ]);

        $result = $service->validate(
            $request->code,
            $request->account_id,
            (int) $request->category_id,
            (float) $request->price_toman,
            (float) ($request->price_dollar ?? 0)
        );

        return response()->json($result, $result['valid'] ? 200 : 422);
    }
}
