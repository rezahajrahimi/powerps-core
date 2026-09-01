<?php

namespace App\Http\Controllers;

use App\Models\Pannel;
use App\Services\ConfigNameService;
use App\Services\LicenseFeatureService;
use App\Models\Proxy;
use App\Models\Inbound;

use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class HiddifyPannelController extends Controller
{
    private function buildHiddifyUserName(Request $request): string
    {
        $accountLabel = ConfigNameService::resolvePanelAccountLabel(
            $request->chat_id ?? null,
            $request->product_id ?? null,
            $request->accountId
        );

        return ConfigNameService::buildHiddifyName(
            $accountLabel,
            $request->chat_id ?? null,
            $request->product_id ?? null
        );
    }

    public function generateUUID($data = null)
    {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    public function get_hiddify_subscription_link($url, $link): string
    {
        if (substr($url, -1) == '/') {
            $url = substr($url, 0, -1);
        }
        // check $link start with /
        if (str_starts_with($link, '/')) {
            $link = ltrim($link, '/');
        }
        return "{$url}/{$link}";
    }
    public function getClearHiddifyRequestUrl($mainUrl, $requestAPi)
    {
        // get substring from end of str until /
        $mainUrl = str_replace('/admin/', '', $mainUrl);
        $mainUrl = str_replace('/admin', '', $mainUrl);
        // if (str_starts_with($requestAPi, '/')) {
        //     $requestAPi = ltrim($requestAPi, '/');
        // }
        if (str_ends_with($mainUrl, '/')) {
            $mainUrl = rtrim($mainUrl, '/');
        }
        return "{$mainUrl}";
    }

    private function normalizeHiddifyUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return rtrim($url, '/');
    }

    private function resolveHiddifyBaseUrl(?Pannel $pannel): string
    {
        if (! $pannel) {
            return '';
        }

        $candidates = array_values(array_filter([
            trim((string) ($pannel->admin_url ?? '')),
            trim((string) ($pannel->user_link ?? '')),
        ]));

        $urlPort = trim((string) ($pannel->url_port ?? ''));
        if ($urlPort !== '') {
            $candidates[] = str_contains($urlPort, '://') ? $urlPort : "https://{$urlPort}";
        }

        foreach ($candidates as $candidate) {
            $url = $this->normalizeHiddifyUrl(
                $this->getClearHiddifyRequestUrl($candidate, '')
            );

            if ($url !== '' && parse_url($url, PHP_URL_SCHEME)) {
                return $url;
            }
        }

        return '';
    }

    private function buildHiddifyRequestUrl(?Pannel $pannel, string $requestApi): string
    {
        $baseUrl = $this->resolveHiddifyBaseUrl($pannel);
        if ($baseUrl === '') {
            return '';
        }

        return $baseUrl . '/' . ltrim($requestApi, '/');
    }

    private function logInvalidHiddifyPanelUrl(int|string $pannelID, ?Pannel $pannel, string $requestApi): void
    {
        \Log::error('Hiddify panel URL is missing or invalid', [
            'pannelID' => $pannelID,
            'requestApi' => $requestApi,
            'admin_url' => $pannel?->admin_url,
            'user_link' => $pannel?->user_link,
            'url_port' => $pannel?->url_port,
        ]);
    }

    private function logHiddifyRequestFailure(int|string $pannelID, string $method, string $url, $response = null, ?\Throwable $error = null): void
    {
        $context = [
            'pannelID' => $pannelID,
            'method' => $method,
            'url' => $url,
        ];

        if ($response !== null) {
            $context['status'] = method_exists($response, 'status') ? $response->status() : null;
            $context['body'] = method_exists($response, 'body') ? $response->body() : null;
        }

        if ($error !== null) {
            $context['error'] = $error->getMessage();
        }

        \Log::error('Hiddify panel request failed', $context);
    }

    public function extractUUID($string)
    {
        // get substring between '/' and '/'
        $parts = explode('/', $string);

        return $parts[1];
    }
    public function checkHiddifyPanelUrl(Request $request)
    {
        $pannelUrl = $request->pannelUrl;
        // check is $pannelUrl ended with "/"

        $secretValue = $request->secretValue;
        if (str_ends_with($pannelUrl, '/')) {
            $pannelUrl = rtrim($pannelUrl, '/');
        }

        // $headers = [
        //     'Content-Type' => 'application/json',
        //     'Accept' => 'application/json',
        //     'Hiddify-API-Key' => $secretValue,
        // ];
        $url = "$pannelUrl/api/v2/admin/server_status/";

        $subsequentResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Hiddify-API-Key' => $secretValue,
        ])->get($url);

        if ($subsequentResponse->getStatusCode() == 200) {

            return response()->json(true, 200);
        }
        return response()->json(false, 401);
    }

    public function addHiddifyPannel(Request $request)
    {
        try {
            $licenseService = new LicenseFeatureService();
            if (! $licenseService->canAddPanel(Pannel::count())) {
                return $licenseService->panelLimitReachedResponse();
            }

            // add pannel
            $pannel = new Pannel();
            $pannel->type = 'hiddify';
            $pannel->location = $request->location ?? null;
            $pannel->admin_url = $this->normalizeHiddifyUrl(
                $this->getClearHiddifyRequestUrl((string) $request->admin_url, '')
            );
            $pannel->user_link = $request->user_link
                ? $this->normalizeHiddifyUrl((string) $request->user_link)
                : null;
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->secret_code = $request->secretValue;
            $pannel->url_port = parse_url($request->admin_url, PHP_URL_HOST);
            // check cookie
            $pannel->save();
            return response()->json($pannel->id, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json(false, 500);
        }
    }
    public function updateHiddifyPannel(Request $request)
    {
        try {
            $pannel = Pannel::find($request->id);
            $pannel->location = $request->location ?? null;
            $pannel->admin_url = $this->normalizeHiddifyUrl(
                $this->getClearHiddifyRequestUrl((string) $request->admin_url, '')
            );
            $pannel->capacity = $request->capacity ?? 1333333;
            $pannel->secret_code = $request->secretValue;
            $pannel->user_link = $request->user_link
                ? $this->normalizeHiddifyUrl((string) $request->user_link)
                : null;

            $pannel->url_port = parse_url($request->admin_url, PHP_URL_HOST);
            // check cookie
            if ($pannel->update()) {
                return response()->json(true, 201);
            }

            return response()->json(false, 500);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");

            return response()->json(false, 500);
        }
    }

    public function resolvePanelUsersList($pannelID): array
    {
        try {
            $pannel = Pannel::find($pannelID);
            if (! $pannel) {
                return [];
            }

            if ($pannel->type == 'sanaei') {
                $sn = new SanaeiPannelController();

                return $sn->getAllClients($pannel) ?: [];
            }

            if (Pannel::isMarzbanCompatibleType($pannel->type)) {
                $mb = MarzbanPannelController::resolve($pannel);

                return $mb->getAllUsers($pannel) ?: [];
            }

            if ($pannel->type !== 'hiddify') {
                return [];
            }

            $data = $this->sendGetRequestToHiddifyPannel($pannelID, '/api/v2/admin/user/');
            if ($data instanceof \Illuminate\Http\JsonResponse || ! is_array($data)) {
                return [];
            }

            return $data;
        } catch (\Throwable $th) {
            \Log::warning('resolvePanelUsersList failed', [
                'panel_id' => $pannelID,
                'error' => $th->getMessage(),
            ]);

            return [];
        }
    }

    public function getHiddifyPanelUsersByPannelID($pannelID)
    {
        $pannel = Pannel::find($pannelID);
        if (! $pannel) {
            return response()->json([], 404);
        }

        return response()->json($this->resolvePanelUsersList($pannelID));
    }
    public function getHiddifyPanelUserByPannelID($pannelID, $userUUID)
    {
        $data = $this->sendGetRequestToHiddifyPannel($pannelID, "api/v2/admin/user/$userUUID/");
        return $data;
    }

    public function getHiddifyPanelAllConfigsUserByPannelID($pannelID, $userUUID)
    {
        $data = $this->sendGetRequestToHiddifyPannel($pannelID, '/api/v2/user/all-configs/');
        return $data;
    }

    public function modifyDaysToHiddifyConfigs(Request $request)
    {
        // \Log::info(json_encode(['request' => $request]));
        $pannelID = $request->pannelID;
        $userUUID = $request->uuid;
        $actionType = $request->actionType;
        $days = $request->days;
        $data = $this->getHiddifyPanelUserByPannelID($pannelID, $userUUID);
        // \Log::info(message: json_encode(["response 1" => $data]));
        // if (isset($data['uuid']) && $data['uuid'] == '') {

        //     return response()->json(['status' => 'error', 'message' => 'کاربر یافت نشد.'], 404);
        // }
        // update
        $current_day = $data['package_days'];
        if ($actionType == "add") {
            $new_day = $current_day + $days;
            $request->day = $new_day;
        } else {
            $new_day = $current_day - $days;
            $request->day = $new_day;
        }
        $request->uuid = $userUUID;
        $request->vol = $data['usage_limit_GB'];
        $request->name = $data['name'];

        // send patch request to hiddify panel
        $result = $this->upgradeUserOfHiddifyPanelApi($request);
        \Log::info(message: json_encode(["response 2" => $result]));

        return $result;
    }
    public function addUserToHiddifyPanel(Request $request)
    {
        $pannelID = $request->pannelID;
        $vol = $request->vol;
        $day = $request->day;
        $accountId = $request->accountId;
        $pannel = Pannel::find($pannelID);

        $adminUUID = $pannel->secret_code;
        $comment = $request->comment ?? '';

        $uuid = $this->generateUUID();
        $params = [
            'uuid' => "$uuid",
            'name' => $this->buildHiddifyUserName($request),
            'current_usage_GB' => 0,
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];
        $data = $this->sendPostRequestToHiddifyPannel($pannelID, '/api/v2/admin/user/', $params);

        if ($data === false) {
            \Log::error('Hiddify addUserToHiddifyPanel failed', [
                'pannelID' => $pannelID,
                'accountId' => $accountId,
                'uuid' => $uuid,
            ]);

            return false;
        }

        // UUID is generated locally and sent in the request body.
        return $uuid;
    }
    public function addUserToHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $vol = $request->vol;
        $day = $request->day;
        $accountId = $request->accountId;
        $pannel = Pannel::find($pannelID);

        $adminUUID = $pannel->secret_code;
        $comment = $request->comment ?? '';

        $uuid = $this->generateUUID();
        $params = [
            'uuid' => "$uuid",
            'name' => $this->buildHiddifyUserName($request),
            'current_usage_GB' => 0,
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];
        $data = $this->sendPostRequestToHiddifyPannel($pannelID, "$adminUUID/api/v1/user/", $params);
        if ($data != false) {
            return $uuid;
        }
        return $data;
    }
    public function updateUserOfHiddifyPanel(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);

        $vol = $request->vol;
        $day = $request->day;
        $accountId = $request->accountId;
        $adminUUID = $pannel->secret_code;
        $uuid = $request->uuid;
        $comment = $request->comment ?? '';
        $name = $request->name ?? '';

        $params = [
            'uuid' => "$uuid",
            'name' => $name,
            'current_usage_GB' => 0,
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];
        $data = $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);
        return $data;
    }
    public function updateUserNameOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
        ];
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
        $url = "$adminUUID/api/v1/user/?uuid={$uuid}";

        $data = $this->sendPostRequestToHiddifyPannel($pannelID, $url, $params);
        return $data;
    }
    public function updateUserNameOfHiddifyPanelApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $comment = $request->comment ?? '';

        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
            'comment' => "$comment",
        ];
        $data = $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);

        return $data;
    }

    private function buildHiddifyRechargeParams(Request $request): array
    {
        $pannel = Pannel::find($request->pannelID);
        $adminUUID = (string) ($pannel?->secret_code ?? '');
        $today = date('Y-m-d');

        return [
            'uuid' => (string) $request->uuid,
            'name' => (string) ($request->name ?? ''),
            'current_usage_GB' => 0,
            'usage_limit_GB' => (float) $request->vol,
            'package_days' => (int) $request->day,
            'mode' => 'no_reset',
            'start_date' => $today,
            'last_reset_time' => date('Y-m-d H:i:s'),
            'enable' => true,
            'added_by_uuid' => $adminUUID,
            'comment' => (string) ($request->comment ?? ''),
        ];
    }

    private function verifyHiddifyRechargeState(int|string $pannelID, string $uuid, float $expectedVolumeGb): bool
    {
        $user = $this->sendGetRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/");
        if (! is_array($user)) {
            return false;
        }

        $currentUsage = (float) ($user['current_usage_GB'] ?? -1);
        $usageLimit = (float) ($user['usage_limit_GB'] ?? 0);

        return $currentUsage <= 0.01 && abs($usageLimit - $expectedVolumeGb) < 0.01;
    }

    public function hiddifyMutationSucceeded(mixed $result): bool
    {
        if ($result === false || $result === null) {
            return false;
        }

        if ($result instanceof \Illuminate\Http\Client\Response) {
            return $result->successful();
        }

        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return $result->getStatusCode() >= 200 && $result->getStatusCode() < 300;
        }

        return is_array($result);
    }

    public function rechargeUserOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        if (! $pannel) {
            return false;
        }

        $adminUUID = $pannel->secret_code;
        $uuid = $request->uuid;
        $params = $this->buildHiddifyRechargeParams($request);
        $path = "$adminUUID/api/v1/user/?uuid={$uuid}";

        return $this->sendPostRequestToHiddifyPannel($pannelID, $path, $params);
    }

    public function rechargeUserOfHiddifyPanelApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $uuid = (string) $request->uuid;
        $expectedVolume = (float) $request->vol;
        $params = $this->buildHiddifyRechargeParams($request);

        $response = $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);
        if ($this->hiddifyMutationSucceeded($response) && $this->verifyHiddifyRechargeState($pannelID, $uuid, $expectedVolume)) {
            return $response;
        }

        \Log::warning('Hiddify v2 recharge did not fully reset user, falling back to v1 API', [
            'pannelID' => $pannelID,
            'uuid' => $uuid,
            'expected_volume_GB' => $expectedVolume,
        ]);

        $fallback = $this->rechargeUserOfHiddifyPanelOldApi($request);
        if ($this->hiddifyMutationSucceeded($fallback)) {
            return response()->json(['msg' => 'ok'], 200);
        }

        return false;
    }
    public function upgradeUserOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $vol = $request->vol;
        $day = $request->day;

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $comment = $request->comment ?? '';
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
        $url = "$adminUUID/api/v1/user/?uuid={$uuid}";

        $data = $this->sendPostRequestToHiddifyPannel($pannelID, $url, $params);
        return $data;
    }
    public function upgradeUserOfHiddifyPanelApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $vol = $request->vol;
        $day = $request->day;

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $comment = $request->comment ?? '';
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
        ];

        $data = $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);
        \Log::info(message: json_encode(["response 3" => $data]));

        return $data;
    }
    public function changeUserActivationOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $comment = $request->comment ?? '';

        $enable = $request->enable == true || $request->enable == 1 ? true : false;
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'comment' => "$comment",
            'enable' => $enable,
            'added_by_uuid' => "$adminUUID",
        ];
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
        $url = "$adminUUID/api/v1/user/?uuid={$uuid}";

        $data = $this->sendPostRequestToHiddifyPannel($pannelID, $url, $params);
        return $data;
    }
    public function changeUserActivationOfHiddifyPanelApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $comment = $request->comment ?? '';

        $enable = $request->enable == true || $request->enable == 1 ? true : false;
        // get today date as format like 2024-01-01
        $today = date('Y-m-d');
        $params = [
            'uuid' => "$uuid",
            'comment' => "$comment",
            'enable' => $enable,
            'added_by_uuid' => "$adminUUID",
        ];
        $data = $this->sendPatchRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$uuid/", $params);
        return $data;
    }
    public function deleteUserOfHiddifyPanelOldApi(Request $request)
    {
        $pannelID = $request->pannelID;
        $pannel = Pannel::find($pannelID);
        $vol = $request->vol;
        $day = $request->day;

        $adminUUID = $pannel->secret_code;

        $uuid = $request->uuid;
        $name = $request->name ?? '';
        $comment = $request->comment ?? '';

        $params = [
            'uuid' => "$uuid",
            'name' => "$name",
            'usage_limit_GB' => $vol,
            'package_days' => $day,
            'mode' => 'no_reset',
            'added_by_uuid' => "$adminUUID",
            'comment' => "$comment",
            'enable' => false,
            'start_date' => '2024-01-01',
        ];
        $url = $this->getClearHiddifyRequestUrl($pannel->admin_url, $pannel->secret_code);
        $url = "$adminUUID/api/v1/user/?uuid={$uuid}";

        $data = $this->sendPostRequestToHiddifyPannel($pannelID, $url, $params);
        return $data;
    }
    public function deleteUserOfHiddifyPanel($pannelID, $userUUID)
    {
        $data = $this->sendDeleteRequestToHiddifyPannel($pannelID, "/api/v2/admin/user/$userUUID/");
        return $data;
    }
    public function sendGetRequestToHiddifyPannel($pannelID, $requestAPi)
    {
        $pannel = Pannel::find($pannelID);
        $url = $this->buildHiddifyRequestUrl($pannel, $requestAPi);
        if ($url === '') {
            $this->logInvalidHiddifyPanelUrl($pannelID, $pannel, $requestAPi);

            return false;
        }

        $secretValue = $pannel->secret_code;
        try {
            $subsequentResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Hiddify-API-Key' => $secretValue,
            ])->get($url);
        } catch (\Throwable $th) {
            $this->logHiddifyRequestFailure($pannelID, 'GET', $url, error: $th);

            return false;
        }

        if ($subsequentResponse->successful()) {
            $checkIsHtmlPage = strpos($subsequentResponse->body(), '<html>');
            if ($checkIsHtmlPage !== false) {
                $this->logHiddifyRequestFailure($pannelID, 'GET', $url, $subsequentResponse);

                return false;
            }

            return json_decode($subsequentResponse->body(), true);
        }

        $this->logHiddifyRequestFailure($pannelID, 'GET', $url, $subsequentResponse);

        return false;
    }
    public function sendDeleteRequestToHiddifyPannel($pannelID, $requestAPi)
    {
        $pannel = Pannel::find($pannelID);
        $secretValue = $pannel->secret_code;
        $url = $this->buildHiddifyRequestUrl($pannel, $requestAPi);
        if ($url === '') {
            $this->logInvalidHiddifyPanelUrl($pannelID, $pannel, $requestAPi);

            return false;
        }

        try {
            $subsequentResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Hiddify-API-Key' => $secretValue,
            ])->delete($url);
        } catch (\Throwable $th) {
            $this->logHiddifyRequestFailure($pannelID, 'DELETE', $url, error: $th);

            return false;
        }

        if ($subsequentResponse->successful()) {
            return true;
        }

        $this->logHiddifyRequestFailure($pannelID, 'DELETE', $url, $subsequentResponse);

        return false;
    }
    public function sendPutRequestToHiddifyPannel($pannelID, $requestAPi, $params = [])
    {
        $pannel = Pannel::find($pannelID);
        $secretValue = $pannel->secret_code;
        $url = $this->buildHiddifyRequestUrl($pannel, $requestAPi);
        if ($url === '') {
            $this->logInvalidHiddifyPanelUrl($pannelID, $pannel, $requestAPi);

            return false;
        }

        try {
            $subsequentResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Hiddify-API-Key' => $secretValue,
            ])->put($url, $params);
        } catch (\Throwable $th) {
            $this->logHiddifyRequestFailure($pannelID, 'PUT', $url, error: $th);

            return false;
        }

        if ($subsequentResponse->successful()) {
            $checkIsHtmlPage = strpos($subsequentResponse->body(), '<html>');
            if ($checkIsHtmlPage !== false) {
                $this->logHiddifyRequestFailure($pannelID, 'PUT', $url, $subsequentResponse);

                return false;
            }

            return json_decode($subsequentResponse->body(), true);
        }

        $this->logHiddifyRequestFailure($pannelID, 'PUT', $url, $subsequentResponse);

        return false;
    }
    public function sendPostRequestToHiddifyPannel($pannelID, $requestAPi, $params = [])
    {
        $pannel = Pannel::find($pannelID);
        $secretValue = $pannel->secret_code;
        $url = $this->buildHiddifyRequestUrl($pannel, $requestAPi);
        if ($url === '') {
            $this->logInvalidHiddifyPanelUrl($pannelID, $pannel, $requestAPi);

            return false;
        }

        try {
            $subsequentResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Hiddify-API-Key' => $secretValue,
            ])->post($url, $params);
        } catch (\Throwable $th) {
            $this->logHiddifyRequestFailure($pannelID, 'POST', $url, error: $th);

            return false;
        }

        if ($subsequentResponse->successful()) {
            $checkIsHtmlPage = strpos($subsequentResponse->body(), '<html>');
            if ($checkIsHtmlPage !== false) {
                $this->logHiddifyRequestFailure($pannelID, 'POST', $url, $subsequentResponse);

                return false;
            }

            $decoded = json_decode($subsequentResponse->body(), true);

            return is_array($decoded) ? $decoded : [];
        }

        $this->logHiddifyRequestFailure($pannelID, 'POST', $url, $subsequentResponse);

        return false;
    }
    public function sendPatchRequestToHiddifyPannel($pannelID, $requestAPi, $params = [])
    {
        $pannel = Pannel::find($pannelID);
        $secretValue = $pannel->secret_code;
        $url = $this->buildHiddifyRequestUrl($pannel, $requestAPi);
        if ($url === '') {
            $this->logInvalidHiddifyPanelUrl($pannelID, $pannel, $requestAPi);

            return false;
        }

        try {
            $subsequentResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Hiddify-API-Key' => $secretValue,
            ])->patch($url, $params);
        } catch (\Throwable $th) {
            $this->logHiddifyRequestFailure($pannelID, 'PATCH', $url, error: $th);

            return false;
        }
        if ($subsequentResponse->successful()) {
            return $subsequentResponse;
        }

        $this->logHiddifyRequestFailure($pannelID, 'PATCH', $url, $subsequentResponse);

        return false;
    }
}
