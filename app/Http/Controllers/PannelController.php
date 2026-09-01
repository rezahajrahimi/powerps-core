<?php

namespace App\Http\Controllers;
use App\Models\Pannel;
use App\Models\Proxy;
use App\Models\Inbound;
use App\Models\Product;
use App\Services\LicenseFeatureService;


use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class PannelController extends Controller
{
    public function addNewPannel(Request $request)
    {
        try {
            $licenseService = new LicenseFeatureService();
            if (! $licenseService->canAddPanel(Pannel::count())) {
                return $licenseService->panelLimitReachedResponse();
            }

            $pannel = new Pannel();
            $pannel->type = $request->type;
            $pannel->username = $request->username ?? 'admin';
            $pannel->password = $request->password ?? '123456';
            $pannel->token = !empty($request->token) ? $request->token : null;
            $pannel->location = $request->location ?? null;
            $pannel->url_port = $request->url_port ?? null;
            $pannel->sub_port = $request->sub_port ?? null;
            $pannel->admin_url = $request->admin_url ?? null;
            $pannel->user_link = $request->user_link ?? null;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->save();
            return response()->json(['success' => true, 'id' => $pannel->id], 201);
        } catch (\Throwable $th) {
            \Log::error('addNewPannel failed: ' . $th->getMessage());
            return response()->json(['success' => false, 'message' => 'خطا در ذخیره پنل.'], 500);
        }
    }
    public function addNewPannelMarzban(Request $request)
    {
        try {
            $licenseService = new LicenseFeatureService();
            if (! $licenseService->canAddPanel(Pannel::count())) {
                return $licenseService->panelLimitReachedResponse();
            }

            $pannel = new Pannel();
            $pannel->type = $request->type;
            $pannel->username = $request->username ?? 'admin';
            $pannel->password = $request->password ?? '123456';
            $pannel->token = !empty($request->token) ? $request->token : null;
            $pannel->location = $request->location ?? null;
            $pannel->url_port = $request->url_port ?? null;
            $pannel->admin_url = $request->admin_url ?? null;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->save();
            if ($pannel->isMarzbanCompatible()) {
                if (! empty($request->dynamic_inbounds)) {
                    $items = $request->dynamic_inbounds;
                    if (is_string($items)) {
                        $items = json_decode($items, true);
                    }
                    if (is_array($items)) {
                        $this->syncMarzbanProxiesFromPanel($pannel, $items);
                    }
                }
            }
            return response()->json($pannel->id, 201);
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json(false, 500);
        }
    }
    public function editMarzbanPannel(Request $request)
    {
        try {
            $pannel = Pannel::find($request->id);
            $pannel->type = Pannel::isMarzbanCompatibleType($request->type ?? $pannel->type)
                ? ($request->type ?? $pannel->type)
                : Pannel::TYPE_MARZBAN;
            $pannel->username = $request->username ?? 'admin';
            $pannel->password = $request->password ?? '123456';
            $pannel->token = !empty($request->token) ? $request->token : null;
            $pannel->location = $request->location ?? null;
            $pannel->url_port = $request->url_port ?? null;
            $pannel->admin_url = $request->admin_url ?? null;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->update();

            if (! empty($request->dynamic_inbounds)) {
                $items = $request->dynamic_inbounds;
                if (is_string($items)) {
                    $items = json_decode($items, true);
                }
                if (is_array($items)) {
                    $this->syncMarzbanProxiesFromPanel($pannel, $items);

                    return response()->json($pannel->id, 201);
                }
            }

            return response()->json($pannel->id, 201);
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json(false, 500);
        }
    }
    public function updatePannel(Request $request)
    {
        try {
            $pannel = Pannel::find($request->id);
            if ($pannel) {
                $pannel->type = $request->type;
                $pannel->username = $request->username ?? 'admin';
                $pannel->password = $request->password ?? '123456';
                $pannel->token = !empty($request->token) ? $request->token : null;
                $pannel->location = $request->location ?? null;
                $pannel->url_port = $request->url_port ?? null;
                $pannel->sub_port = $request->sub_port ?? null;
                $pannel->admin_url = $request->admin_url ?? null;
                $pannel->user_link = $request->user_link ?? null;
                $pannel->capacity = $request->capacity ?? 1333333;
                if ($pannel->type === 'sanaei') {
                    if ($request->has('api_version')) {
                        $v = strtolower(trim((string) $request->api_version));
                        $pannel->api_version = in_array($v, ['v2', '2', 'v1', '1'], true) ? 'v2' : 'v3';
                    }
                    $pannel->cookie_session = null;
                }
                if ($pannel->update()) {
                    return response()->json(['success' => true, 'id' => $pannel->id], 200);
                }
                return response()->json(['success' => false, 'message' => 'خطا در ویرایش پنل.'], 500);
            }
            return response()->json(['success' => false, 'message' => 'پنل یافت نشد.'], 404);
        } catch (\Throwable $th) {
            \Log::error('updatePannel failed: ' . $th->getMessage());

            return response()->json(['success' => false, 'message' => 'خطا در ویرایش پنل.'], 500);
        }
    }
    public function getPannels()
    {
        try {
            return Pannel::all();
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function getPannelById($id)
    {
        try {
            return Pannel::find($id);
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function get_pannel_id_by_location($location)
    {
        try {
            return Pannel::where('location', $location)->first()->id;
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function getPannelByIdWithProxiesInbounds($id)
    {
        try {
            $pannel = Pannel::findOrFail($id);
            $proxyInbounds = Pannel::findOrFail($id)->proxies()->with('inbounds')->get();
            return response()->json([$pannel, $proxyInbounds], 200);
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }

    public function deletePannel($id)
    {
        try {
            $panel = Pannel::find($id);
            if ($panel) {
                if ($panel->delete()) {
                    return true;
                } else {
                    return response()->json(false, 500);
                }
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json(false, 500);
        }
    }
    public function get_all_pannels_locations()
    {
        try {
            return Pannel::all()->pluck('location')->unique();
        } catch (\Throwable $th) {
            \Log::info("get_all_pannels_locations:  $th");
            return response()->json(false, 500);
        }
    }

    public function get_all_panells_by_location_capacity_mode()
    {

        // get all stored products conts with the pannel_id seperation

        try {


            $pannels = Pannel::with('product_category_and_count_products')->get();
            foreach ($pannels as $key => $value) {
                // بررسی وجود مقدار و خالی نبودن آرایه
                $rrr = 0;
                if (!empty($value->product_category_and_count_products) && isset($value->product_category_and_count_products[0]->products_count)) {
                    $rrr = $value->product_category_and_count_products[0]->products_count;
                }
                if ($rrr >= $value->capacity) {
                    $pannels->forget($key);
                }
            }
            return $pannels->pluck('location')->unique();
        } catch (\Throwable $th) {
            \Log::info("get_all_panells_by_location_capacity_mode:  $th");

            return collect();
        }

    }
    public function get_all_panells_Id_by_location_capacity_mode()
    {

        // get all stored products conts with the pannel_id seperation

        try {


            $pannels = Pannel::with('product_category_and_count_products')->get();
            foreach ($pannels as $key => $value) {
                $rrr = 0;
                if (! empty($value->product_category_and_count_products) && isset($value->product_category_and_count_products[0]->products_count)) {
                    $rrr = $value->product_category_and_count_products[0]->products_count;
                }
                if ($rrr >= $value->capacity) {
                    $pannels->forget($key);
                }
            }
            return $pannels->pluck('id');
        } catch (\Throwable $th) {
            \Log::info("get_all_panells_Id_by_location_capacity_mode:  $th");

            return collect();
        }

    }


    public function getHiddifyPannelLinkByPannelID($pannelID)
    {
        $panel = Pannel::find($pannelID);
        $mainUrl = $panel->admin_url;

        $mainUrl = str_replace('/admin/', '', $mainUrl);
        $mainUrl = str_replace('/admin', '', $mainUrl);
        // get substring from end of str until /

        $adminUUID = substr($mainUrl, -36);
        $hidifyUrl = str_replace("/$adminUUID", '', $mainUrl);

        return $hidifyUrl;
    }

    public function generateQrMOC($str)
    {
        $fileName = 'qr_' . time() . '_' . rand(1000, 9999) . '.png';

        $qrImage = QrCode::format('png')
            ->size(250)
            ->backgroundColor(255, 255, 255)
            ->color(0, 0, 255)
            ->margin(1)
            ->generate($str);

        // تبدیل خروجی QR به تصویر GD
        $qr = imagecreatefromstring($qrImage);

        // ایجاد تصویر جدید با اندازه بزرگتر برای پس‌زمینه
        $finalImage = imagecreatetruecolor(300, 300);

        // ایجاد رنگ پس‌زمینه (در اینجا آبی روشن)
        $bgColor = imagecolorallocate($finalImage, 200, 230, 255);

        // پر کردن پس‌زمینه
        imagefill($finalImage, 0, 0, $bgColor);

        // کپی کردن QR در مرکز تصویر نهایی
        imagecopy($finalImage, $qr, 25, 25, 0, 0, 250, 250);

        $directory = public_path('images/qrcodes');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory . '/' . $fileName;
        if (File::exists($path)) {
            File::delete($path);
        }

        // ذخیره تصویر نهایی
        $result = imagepng($finalImage, $path);

        // آزاد کردن حافظه
        imagedestroy($qr);
        imagedestroy($finalImage);

        if (!$result) {
            return false;
        }

        return $path;
    }
    public function createMarzbanUser($accountId, $day, $vol, $pannelID)
    {
        $mb = MarzbanPannelController::resolve($pannelID);
        $result = $mb->createUser($pannelID, $accountId, (int) $day, $vol);
        if ($result === false) {
            return null;
        }

        return [
            'links' => $result['links'],
            'subscription_link' => $result['subscription_link'],
        ];
    }

    public function modifyMarzbanUser($accountId, $day, $vol, $pannelID)
    {
        $mb = MarzbanPannelController::resolve($pannelID);
        if (! $mb->modifyUser($pannelID, $accountId, (int) $day, $vol)) {
            return null;
        }

        $user = $mb->getUser($pannelID, $accountId);

        return $user['links'] ?? null;
    }

    private function syncMarzbanProxiesFromPanel(Pannel $pannel, array $items): void
    {
        $byProtocol = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $protocol = strtolower((string) ($item['protocol'] ?? ''));
            $tag = trim((string) ($item['tag'] ?? ''));
            if ($protocol === '' || $tag === '') {
                continue;
            }
            $byProtocol[$protocol][] = [
                'tag' => $tag,
                'enabled' => ! empty($item['enabled']),
            ];
        }

        foreach ($byProtocol as $protocol => $inboundItems) {
            $proxy = Proxy::where('pannel_id', $pannel->id)
                ->where('type', $protocol)
                ->first();
            if (! $proxy) {
                $proxy = new Proxy();
                $proxy->pannel_id = $pannel->id;
                $proxy->type = $protocol;
                $proxy->is_active = false;
                $proxy->save();
            }

            $anyEnabled = false;
            foreach ($inboundItems as $inboundItem) {
                $inbound = Inbound::where('proxy_id', $proxy->id)
                    ->where('name', $inboundItem['tag'])
                    ->first();
                if (! $inbound) {
                    $inbound = new Inbound();
                    $inbound->proxy_id = $proxy->id;
                    $inbound->name = $inboundItem['tag'];
                    $inbound->data = $inboundItem['tag'];
                }
                $inbound->is_active = $inboundItem['enabled'];
                $inbound->save();
                if ($inboundItem['enabled']) {
                    $anyEnabled = true;
                }
            }

            $proxy->is_active = $anyEnabled;
            $proxy->update();
        }
    }
}
