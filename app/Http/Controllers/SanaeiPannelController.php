<?php

namespace App\Http\Controllers;

use App\Models\Pannel;
use App\Services\ConfigNameService;
use App\Services\LicenseFeatureService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nette\Utils\Random;
use Carbon\Carbon;

class SanaeiPannelController extends Controller
{
    private $apiPrefix = '/panel/api';

    /** @var array<int, string> */
    private array $panelApiVersions = [];

    /** @var array<int|string, array<string, string>> */
    private array $panelAuthHeaders = [];

    /** @var array<int|string, array<string, string>> */
    private array $panelAuthCookies = [];

    /** @var array<int|string, string> */
    private array $panelCsrfTokens = [];

    private function panelKey(Pannel $panel): int|string
    {
        return $panel->id ?? spl_object_id($panel);
    }

    private function authToken(Pannel $panel): string
    {
        $token = trim((string) ($panel->token ?? ''));
        if ($token === '' || $token === 'Bearer') {
            return '';
        }
        if (!str_starts_with($token, 'Bearer ')) {
            return 'Bearer ' . $token;
        }
        return $token;
    }

    private function normalizeApiVersion(?string $version): ?string
    {
        $v = strtolower(trim((string) $version));
        if (in_array($v, ['v3', '3'], true)) {
            return 'v3';
        }
        if (in_array($v, ['v2', '2', 'v1', '1'], true)) {
            return 'v2';
        }
        return null;
    }

    private function storedApiVersion(Pannel $panel): string
    {
        return $this->normalizeApiVersion($panel->api_version) ?? 'v3';
    }

    private function apiPrefixFor(Pannel $panel): string
    {
        return $this->resolveApiVersion($panel) === 'v3' ? '/panel/api' : '/xui/API';
    }

    /** Pick the API path segment for the panel's configured version (v3 vs v2). */
    private function apiPathFor(Pannel $panel, string $v3Path, string $v2Path): string
    {
        return $this->isV3($panel) ? $v3Path : $v2Path;
    }

    private function resolveApiVersion(Pannel $panel): string
    {
        $id = $panel->id ?? $this->panelKey($panel);
        if (isset($this->panelApiVersions[$id])) {
            return $this->panelApiVersions[$id];
        }
        $version = $this->storedApiVersion($panel);
        $this->panelApiVersions[$id] = $version;
        $this->apiPrefix = $this->apiPrefixFor($panel);
        return $version;
    }

    private function detectApiVersion(Pannel $panel): string
    {
        return $this->resolveApiVersion($panel);
    }

    private function isV3(Pannel $panel): bool
    {
        return $this->resolveApiVersion($panel) === 'v3';
    }

    private function performRequestWithFallback(
        Pannel $panel,
        string $method,
        string $v3Path,
        string $v2Path,
        $body = null,
        $asJson = true
    ) {
        if ($this->resolveApiVersion($panel) === 'v3') {
            return $this->performRequest($panel, $method, $v3Path, $body, $asJson);
        }
        return $this->performRequest($panel, $method, $v2Path, $body, $asJson);
    }

    private function normalizeV3ClientRecord(array $rec, ?array $inboundIds = null): array
    {
        $uuid = $rec['uuid'] ?? ($rec['id'] ?? '');
        $client = array_merge($rec, ['id' => $uuid]);
        $inboundId = $inboundIds[0] ?? 1;
        return [
            'inbound' => ['id' => $inboundId],
            'client' => $client,
        ];
    }

    /**
     * @return int[]
     */
    private function resolveInboundIdsFromRequest(Request $request, Pannel $panel): array
    {
        $resolved = [];

        $appendIds = function (mixed $raw) use (&$resolved): void {
            if ($raw === null || $raw === '' || $raw === []) {
                return;
            }

            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $raw = $decoded;
                } else {
                    $parts = preg_split('/[,; ]+/', trim($raw));
                    $raw = array_filter($parts ?? [], fn ($part) => $part !== '');
                }
            }

            if (! is_array($raw)) {
                return;
            }

            foreach ($raw as $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $resolved[] = (int) $value;
            }
        };

        $appendIds($request->input('inbound_ids'));

        if ($request->filled('inbound_id')) {
            $resolved[] = (int) $request->input('inbound_id');
        }

        $resolved = array_values(array_unique($resolved));
        if ($resolved !== []) {
            sort($resolved);

            return $resolved;
        }

        if (! empty($panel->inbound_id)) {
            return [(int) $panel->inbound_id];
        }

        return [1];
    }

    /** 3x-ui v3 may return JSON fields as arrays; v2 returns JSON strings. */
    private function decodeJsonField(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** Normalize client payload for 3x-ui v3 JSON API (tgId/reset must be numbers). */
    private function normalizeV3ClientForApi(array $client): array
    {
        $tg = $client['tgId'] ?? 0;
        if ($tg === '' || $tg === null) {
            $client['tgId'] = 0;
        } else {
            $client['tgId'] = (int) $tg;
        }

        $client['reset'] = (int) ($client['reset'] ?? 0);
        $client['limitIp'] = (int) ($client['limitIp'] ?? 0);
        $client['totalGB'] = (int) ($client['totalGB'] ?? 0);
        $client['expiryTime'] = (int) ($client['expiryTime'] ?? 0);
        $client['enable'] = (bool) ($client['enable'] ?? true);
        $client['comment'] = (string) ($client['comment'] ?? '');

        return $client;
    }

    private function buildNewSanaeiClient(
        string $uuid,
        string $email,
        int $totalBytes,
        int $expiryMs,
        int $limitIp,
        string $subId,
        ?string $flow = null,
        bool $forV3 = true
    ): array {
        $client = [
            'id' => $uuid,
            'email' => $email,
            'limitIp' => $limitIp,
            'totalGB' => $totalBytes,
            'expiryTime' => $expiryMs,
            'enable' => true,
            'subId' => $subId,
        ];
        if ($flow) {
            $client['flow'] = $flow;
        }
        if ($forV3) {
            $client['tgId'] = 0;
            $client['reset'] = 0;
            $client['comment'] = '';
            return $this->normalizeV3ClientForApi($client);
        }

        $client['tgId'] = '';

        return $client;
    }

    private function baseUrl(Pannel $panel): string
    {
        $url = trim((string) $panel->admin_url);
        if ($url === '') {
            return '';
        }
        return rtrim($url, '/');
    }

    /**
     * Subscription base URL: scheme://host[:port] without 3x-ui web path prefix.
     * Uses sub_port when configured on the panel.
     */
    public function subscriptionBaseUrl(Pannel $panel): string
    {
        $source = trim((string) ($panel->admin_url ?: $panel->user_link ?: $panel->url_port ?: ''));
        if ($source === '') {
            return '';
        }

        $parsed = parse_url($source);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return rtrim($source, '/');
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = $parsed['host'];

        if (!empty($panel->sub_port)) {
            $port = (int) $panel->sub_port;
        } elseif (isset($parsed['port'])) {
            $port = (int) $parsed['port'];
        } else {
            $port = $scheme === 'https' ? 443 : 80;
        }

        $usePort = ($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80);

        return $usePort ? "{$scheme}://{$host}:{$port}" : "{$scheme}://{$host}";
    }

    public function buildSubscriptionLink(Pannel $panel, string $subId): string
    {
        $base = $this->subscriptionBaseUrl($panel);
        if ($base === '') {
            return '';
        }

        return rtrim($base, '/') . '/sub/' . trim($subId, '/');
    }

    /**
     * Fast dashboard health check — uses existing session only, no slow re-login.
     */
    public function dashboardStatus(Pannel $panel): array
    {
        $offline = ['is_online' => false, 'online_users' => 0];
        try {
            $this->resolveApiVersion($panel);
            $base = $this->baseUrl($panel);
            if ($base === '' || empty($panel->cookie_session)) {
                return $offline;
            }

            $prefix = $this->apiPrefixFor($panel);
            $headers = $this->headers($panel, false);
            $cookieHeader = $this->buildCookieHeader($panel);
            if ($cookieHeader !== '') {
                $headers['Cookie'] = $cookieHeader;
            }

            $listUrl = $base . $prefix . '/inbounds/list';
            $r = $this->panelHttp()->timeout(6)->connectTimeout(6)->withHeaders($headers)->get($listUrl);
            $json = $r->json();
            if (!$r->ok() || !is_array($json) || !($json['success'] ?? false)) {
                return $offline;
            }

            $onlineUsers = 0;
            try {
                $onlinesPath = $this->isV3($panel) ? '/clients/onlines' : '/inbounds/onlines';
                $postHeaders = $this->applyCsrfHeader($panel, $headers, true);
                $or = $this->panelHttp()->timeout(6)->connectTimeout(6)
                    ->withHeaders($postHeaders)
                    ->post($base . $prefix . $onlinesPath, []);
                $oj = $or->json();
                if ($or->ok() && is_array($oj) && ($oj['success'] ?? false)) {
                    $onlineUsers = count($oj['obj'] ?? []);
                }
            } catch (\Throwable $th) {
                \Log::debug('dashboardStatus onlines skipped: ' . $th->getMessage());
            }

            return ['is_online' => true, 'online_users' => $onlineUsers];
        } catch (\Throwable $th) {
            \Log::debug('dashboardStatus failed: ' . $th->getMessage());

            return $offline;
        }
    }

    private function panelHttp()
    {
        return Http::withoutVerifying()
            ->connectTimeout(25)
            ->timeout(60)
            ->withOptions([
                'curl' => [
                    CURLOPT_TCP_KEEPALIVE => 1,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                ],
            ]);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withPanelRetries(callable $callback, int $maxAttempts = 3)
    {
        $last = null;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                return $callback();
            } catch (\Throwable $th) {
                $last = $th;
                $message = $th->getMessage();
                $retryable = str_contains($message, 'error 52')
                    || str_contains($message, 'error 28')
                    || str_contains($message, 'error 7')
                    || str_contains($message, 'Empty reply')
                    || str_contains($message, 'Connection refused')
                    || str_contains($message, 'timed out');
                if ($retryable && $attempt < $maxAttempts - 1) {
                    \Log::warning('Panel HTTP retry ' . ($attempt + 1) . ': ' . $message);
                    usleep(800000 * ($attempt + 1));
                    continue;
                }
                throw $th;
            }
        }
        throw $last ?? new \RuntimeException('Panel HTTP request failed');
    }

    private function headers(Pannel $panel, bool $withAuthorization = true): array
    {
        $base = $this->baseUrl($panel);
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => $base . '/',
            'User-Agent' => 'Mozilla/5.0 (compatible; powerps/1.0)',
        ];
        if ($withAuthorization) {
            $key = $this->panelKey($panel);
            if (isset($this->panelAuthHeaders[$key])) {
                return array_merge($headers, $this->panelAuthHeaders[$key]);
            }
            $token = $this->authToken($panel);
            if ($token !== '') {
                $headers['Authorization'] = $token;
            }
        }
        return $headers;
    }

    private function getServerHost(Pannel $panel): string
    {
        if (!empty($panel->url_port)) {
            return $panel->url_port;
        }
        $parsed = parse_url($panel->admin_url);
        return $parsed['host'] ?? '';
    }

    private function urlencodeIfNotNull(?string $value): string
    {
        return $value === null ? '' : rawurlencode($value);
    }

    private function buildCookieHeader(Pannel $panel): string
    {
        $cookies = json_decode($panel->cookie_session ?? '[]', true) ?? [];
        $parts = [];
        foreach ($cookies as $c) {
            $name = $c['Name'] ?? ($c['name'] ?? null);
            $value = $c['Value'] ?? ($c['value'] ?? null);
            if ($name !== null && $value !== null) {
                $parts[] = $name . '=' . $value;
            }
        }
        return implode('; ', $parts);
    }

    private function usesCookieAuth(Pannel $panel): bool
    {
        $key = $this->panelKey($panel);
        return $this->buildCookieHeader($panel) !== '' || isset($this->panelAuthCookies[$key]);
    }

    private function fetchCsrfTokenForPanel(Pannel $panel): ?string
    {
        $base = $this->baseUrl($panel);
        if ($base === '') {
            return null;
        }
        try {
            return $this->withPanelRetries(function () use ($panel, $base) {
                $endpoints = $this->usesCookieAuth($panel)
                    ? ['/panel/csrf-token', '/csrf-token']
                    : ['/csrf-token'];

                foreach ($endpoints as $endpoint) {
                    $r = $this->httpWithAuth($panel, false)->withHeaders(['Accept' => 'application/json'])->get($base . $endpoint);
                    $json = $r->json();
                    if ($r->ok() && is_array($json) && ($json['success'] ?? false) && !empty($json['obj'])) {
                        return (string) $json['obj'];
                    }
                }

                $headers = $this->headers($panel, false);
                $cookieHeader = $this->buildCookieHeader($panel);
                if ($cookieHeader !== '') {
                    $headers['Cookie'] = $cookieHeader;
                }
                $root = $this->panelHttp()->withHeaders($headers)->get($base . '/');
                if ($root->ok() && preg_match('/csrf-token"\s+content="([^"]+)"/', $root->body(), $m)) {
                    return $m[1];
                }

                return null;
            });
        } catch (\Throwable $th) {
            \Log::warning('fetchCsrfTokenForPanel failed: ' . $th->getMessage());
            return null;
        }
    }

    private function ensureCsrfToken(Pannel $panel, bool $refresh = false): ?string
    {
        if (!$this->isV3($panel)) {
            return null;
        }
        $key = $this->panelKey($panel);
        if (!$refresh && isset($this->panelCsrfTokens[$key])) {
            return $this->panelCsrfTokens[$key];
        }
        $token = $this->fetchCsrfTokenForPanel($panel);
        if ($token !== null) {
            $this->panelCsrfTokens[$key] = $token;
        }
        return $token;
    }

    private function applyCsrfHeader(Pannel $panel, array $headers, bool $unsafeRequest): array
    {
        if (!$unsafeRequest || !$this->isV3($panel)) {
            return $headers;
        }
        if (!$this->usesCookieAuth($panel) && isset($this->panelAuthHeaders[$this->panelKey($panel)])) {
            return $headers;
        }
        $csrf = $this->ensureCsrfToken($panel);
        if ($csrf !== null) {
            $headers['x-csrf-token'] = $csrf;
        }
        return $headers;
    }

    private function clearPanelAuthCache(Pannel $panel): void
    {
        $key = $this->panelKey($panel);
        if ($panel->id) {
            unset($this->panelApiVersions[$panel->id]);
        }
        unset($this->panelAuthHeaders[$key], $this->panelAuthCookies[$key], $this->panelCsrfTokens[$key]);
    }

    private function httpWithAuth(Pannel $panel, bool $unsafeRequest = false)
    {
        $key = $this->panelKey($panel);
        $cookieHeader = $this->buildCookieHeader($panel);
        $hasSessionCookies = $cookieHeader !== '';
        $hasApiKeyCookie = isset($this->panelAuthCookies[$key]);
        $useBearer = $this->authToken($panel) !== '' && !$hasSessionCookies && !$hasApiKeyCookie;

        $headers = $this->headers($panel, $useBearer || isset($this->panelAuthHeaders[$key]));
        if ($hasSessionCookies) {
            $headers['Cookie'] = $cookieHeader;
        }
        $headers = $this->applyCsrfHeader($panel, $headers, $unsafeRequest);

        $req = $this->panelHttp()->withHeaders($headers);
        if ($hasApiKeyCookie) {
            $host = parse_url($this->baseUrl($panel), PHP_URL_HOST) ?: '';
            if ($host !== '') {
                $req = $req->withCookies($this->panelAuthCookies[$key], $host);
            }
        }

        return $req;
    }

    private function newCookieJar(): CookieJar
    {
        return new CookieJar();
    }

    private function persistCookies(Pannel $panel, CookieJar $jar, array $extraCookies = []): void
    {
        $cookies = [];
        foreach ($jar as $cookie) {
            $cookies[] = [
                'Name' => $cookie->getName(),
                'Value' => $cookie->getValue(),
                'Domain' => $cookie->getDomain(),
                'Path' => $cookie->getPath(),
            ];
        }
        foreach ($extraCookies as $cookie) {
            $cookies[] = [
                'Name' => $cookie['Name'] ?? ($cookie['name'] ?? ''),
                'Value' => $cookie['Value'] ?? ($cookie['value'] ?? ''),
                'Domain' => $cookie['Domain'] ?? ($cookie['domain'] ?? ''),
                'Path' => $cookie['Path'] ?? ($cookie['path'] ?? '/'),
            ];
        }
        $cookies = array_values(array_filter($cookies, fn ($c) => ($c['Name'] ?? '') !== ''));
        if (!empty($cookies)) {
            $panel->cookie_session = json_encode($cookies);
            if ($panel->exists) {
                $panel->update();
            }
        }
    }

    private function fetchCsrfToken(string $base, CookieJar $jar): ?string
    {
        try {
            return $this->withPanelRetries(function () use ($base, $jar) {
                $this->panelHttp()->withOptions(['cookies' => $jar])->get($base . '/');
                $r = $this->panelHttp()
                    ->withOptions(['cookies' => $jar])
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get($base . '/csrf-token');
                $json = $r->json();
                if ($r->ok() && is_array($json) && ($json['success'] ?? false) && !empty($json['obj'])) {
                    return (string) $json['obj'];
                }
                $root = $this->panelHttp()->withOptions(['cookies' => $jar])->get($base . '/');
                if ($root->ok() && preg_match('/csrf-token"\s+content="([^"]+)"/', $root->body(), $m)) {
                    return $m[1];
                }
                return null;
            });
        } catch (\Throwable $th) {
            \Log::warning('fetchCsrfToken failed: ' . $th->getMessage());
        }
        return null;
    }

    private function cacheCsrfAfterLogin(Pannel $panel): void
    {
        if (!$this->isV3($panel)) {
            return;
        }
        $csrf = $this->fetchCsrfTokenForPanel($panel);
        if ($csrf !== null) {
            $this->panelCsrfTokens[$this->panelKey($panel)] = $csrf;
        }
    }

    private function validateBearerToken(Pannel $panel, string $token): bool
    {
        $base = $this->baseUrl($panel);
        $prefix = $this->apiPrefixFor($panel);
        $paths = [$prefix . '/server/status', $prefix . '/inbounds/list'];
        $rawToken = str_starts_with($token, 'Bearer ') ? trim(substr($token, 7)) : $token;

        $authHeaderSets = [
            ['Authorization' => 'Bearer ' . $rawToken],
            ['Authorization' => $token],
            ['Authorization' => $rawToken],
            ['X-Api-Key' => $rawToken],
            ['X-API-KEY' => $rawToken],
        ];

        $key = $this->panelKey($panel);
        foreach ($authHeaderSets as $authHeaders) {
            foreach ($paths as $path) {
                try {
                    $r = $this->withPanelRetries(fn () => $this->panelHttp()
                        ->withHeaders(array_merge($authHeaders, ['Accept' => 'application/json']))
                        ->get($base . $path));
                    $json = $r->json();
                    if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                        $this->panelAuthHeaders[$key] = $authHeaders;
                        return true;
                    }
                } catch (\Throwable $th) {
                    \Log::warning('Bearer token validation exception: ' . $th->getMessage());
                }
            }
        }

        $host = parse_url($base, PHP_URL_HOST) ?: '';
        if ($host !== '') {
            foreach (['apiKey', 'Api-Key', 'API_KEY'] as $cookieName) {
                foreach ($paths as $path) {
                    try {
                        $r = $this->withPanelRetries(fn () => $this->panelHttp()
                            ->withCookies([$cookieName => $rawToken], $host)
                            ->withHeaders(['Accept' => 'application/json'])
                            ->get($base . $path));
                        $json = $r->json();
                        if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                            $this->panelAuthCookies[$key] = [$cookieName => $rawToken];
                            return true;
                        }
                    } catch (\Throwable $th) {
                        \Log::warning('API key cookie validation exception: ' . $th->getMessage());
                    }
                }
            }
        }

        return false;
    }

    private function verifySession(Pannel $panel): bool
    {
        $base = $this->baseUrl($panel);
        if ($base === '') {
            return false;
        }
        try {
            return $this->withPanelRetries(function () use ($panel, $base) {
                $prefix = $this->apiPrefixFor($panel);
                $url = $base . $prefix . '/inbounds/list';
                $r = $this->httpWithAuth($panel)->get($url);
                $json = $r->json();
                if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                    $this->apiPrefix = $prefix;
                    return true;
                }
                if (!empty($panel->cookie_session)) {
                    $r = $this->rawGetWithCookie($panel, $url);
                    $json = $r->json();
                    if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                        $this->apiPrefix = $prefix;
                        return true;
                    }
                }
                return false;
            });
        } catch (\Throwable $th) {
            \Log::warning('verifySession failed: ' . $th->getMessage());
            return false;
        }
    }

    private function performPanelLogin(Pannel $panel): bool
    {
        $base = $this->baseUrl($panel);
        try {
            return $this->withPanelRetries(function () use ($panel, $base) {
                $jar = $this->newCookieJar();
                $csrf = $this->fetchCsrfToken($base, $jar);
                $headers = array_merge($this->headers($panel, false), ['Accept' => 'application/json']);
                if ($csrf !== null) {
                    $headers['x-csrf-token'] = $csrf;
                }

                $username = $panel->username ?? 'admin';
                $password = $panel->password ?? 'admin';

                $res = $this->panelHttp()
                    ->withOptions(['cookies' => $jar])
                    ->withHeaders($headers)
                    ->post($base . '/login', [
                        'username' => $username,
                        'password' => $password,
                    ]);
                $json = $res->json();
                if ($res->ok() && is_array($json) && ($json['success'] ?? false)) {
                    $this->persistCookies($panel, $jar, $res->cookies()->toArray());
                    $this->cacheCsrfAfterLogin($panel);
                    return $this->verifySession($panel);
                }

                $res = $this->panelHttp()
                    ->withOptions(['cookies' => $jar])
                    ->asForm()
                    ->withHeaders($headers)
                    ->post($base . '/login', [
                        'username' => $username,
                        'password' => $password,
                        'remember' => 'on',
                    ]);
                if ($res->status() === 200 || $res->status() === 302) {
                    $json = $res->json();
                    if (is_array($json) && array_key_exists('success', $json) && !($json['success'] ?? false)) {
                        return false;
                    }
                    $this->persistCookies($panel, $jar, $res->cookies()->toArray());
                    $this->cacheCsrfAfterLogin($panel);
                    return $this->verifySession($panel);
                }

                \Log::error("Login failed. URL: {$base}/login, Status: " . $res->status() . ", Body: " . substr($res->body(), 0, 500));
                return false;
            });
        } catch (\Throwable $th) {
            \Log::error('performPanelLogin failed: ' . $th->getMessage());
            return false;
        }
    }

    public function login($panelOrId)
    {
        if ($panelOrId instanceof Pannel) {
            $panel = $panelOrId;
        } else {
            $panel = Pannel::find($panelOrId);
        }
        if (!$panel) {
            \Log::error("login: panel not found for ID " . (is_object($panelOrId) ? 'OBJECT' : $panelOrId));
            return false;
        }
        $this->resolveApiVersion($panel);
        $pannelID = $panel->id;

        // If a token exists, validate it before assuming we're authenticated.
        $token = $this->authToken($panel);
        if ($token !== '' && $this->validateBearerToken($panel, $token)) {
            \Log::info("Token validated for panel $pannelID");
            return true;
        }
        if ($token !== '') {
            \Log::warning("Token present but validation failed for panel $pannelID");
        }

        $base = $this->baseUrl($panel);
        if ($base === '') {
            \Log::error("Panel $pannelID has no base URL");
            return false;
        }

        // Check if cookies are valid and not expired
        if (!empty($panel->cookie_session)) {
            try {
                $prefix = $this->apiPrefixFor($panel);
                $r = $this->httpWithAuth($panel)->get($base . $prefix . '/inbounds/list');
                $json = $r->json();
                if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                    $this->apiPrefix = $prefix;
                    $this->cacheCsrfAfterLogin($panel);
                    return true;
                }
                \Log::info("Existing session invalid or expired for panel $pannelID. Proceeding to login.");
            } catch (\Throwable $th) {
                \Log::warning("Session check exception for panel $pannelID: " . $th->getMessage());
            }
        }

        try {
            $rootRes = $this->panelHttp()->get($base);
            \Log::info("Server check for $base: Status=" . $rootRes->status());
        } catch (\Throwable $e) {
            \Log::error("Server check failed for $base: " . $e->getMessage());
        }

        return $this->performPanelLogin($panel);
    }

    public function addUserToSanaeiPanel(Request $request, ?array $inboundIdsOverride = null)
    {
        try {
            $pannelID = (int) $request->pannelID;
            $day = (int) $request->day;
            $volGb = (int) $request->vol;
            $accountLabel = ConfigNameService::resolvePanelAccountLabel(
                $request->chat_id ?? null,
                $request->product_id ?? null,
                $request->accountId ?? 'bot'
            );

            $panel = Pannel::find($pannelID);
            if (!$panel) {
                \Log::error("Panel $pannelID not found");
                return false;
            }
            if (!$this->login($panel)) {
                \Log::error("Login failed for panel $pannelID");
                return false;
            }

            $panel->refresh();
            $this->ensureCsrfToken($panel);

            if ($inboundIdsOverride !== null && $inboundIdsOverride !== []) {
                $inboundIds = array_values(array_unique(array_map('intval', $inboundIdsOverride)));
            } else {
                $inboundIds = $this->resolveInboundIdsFromRequest($request, $panel);
            }
            \Log::info('addUserToSanaeiPanel resolved inbound IDs', [
                'panel_id' => $pannelID,
                'inbound_ids' => $inboundIds,
                'override' => $inboundIdsOverride !== null,
            ]);
            if ($inboundIds === []) {
                \Log::error("No inbound IDs configured for panel $pannelID");
                return false;
            }

            $validatedInboundIds = [];
            foreach ($inboundIds as $candidateId) {
                $inbound = $this->getInboundFromPanel($panel, $candidateId);
                if (!$inbound) {
                    \Log::error("Inbound $candidateId not found in panel $pannelID");
                    return false;
                }
                $validatedInboundIds[] = (int) $inbound['id'];
            }
            $validatedInboundIds = array_values(array_unique($validatedInboundIds));
            $primaryInboundId = $validatedInboundIds[0];
            $inbound = $this->getInboundFromPanel($panel, $primaryInboundId);

            $uuid = (new HiddifyPannelController())->generateUUID();
            $expireSec = now('UTC')->addDays($day)->timestamp;
            $totalBytes = $volGb * 1024 * 1024 * 1024;
            $expiryMs = $expireSec * 1000;

            $flow = '';
            $streamSettings = $this->decodeJsonField($inbound['streamSettings'] ?? null) ?? [];
            if (isset($streamSettings['security']) && $streamSettings['security'] === 'reality') {
                $flow = 'xtls-rprx-vision';
            }

            $client = $this->buildNewSanaeiClient(
                $uuid,
                ConfigNameService::buildSanaeiClientId(
                    $accountLabel,
                    Random::generate(4),
                    $request->chat_id ?? null,
                    $request->product_id ?? null
                ),
                $totalBytes,
                $expiryMs,
                (int) ($request->ip_limit ?? 0),
                Random::generate(16),
                $flow ?: null,
                $this->isV3($panel)
            );

            if ($this->isV3($panel)) {
                $v3Body = [
                    'client' => $client,
                    'inboundIds' => $validatedInboundIds,
                ];
                $res = $this->performRequest($panel, 'POST', '/clients/add', $v3Body, true);
                if ($res !== null) {
                    return ['uuid' => $uuid, 'subId' => $client['subId'], 'email' => $client['email']];
                }
                \Log::error('Failed to add client (v3) on panel ' . $pannelID);
                return false;
            }

            foreach ($validatedInboundIds as $inboundId) {
                $body = [
                    'id' => $inboundId,
                    'settings' => json_encode(['clients' => [$client]]),
                ];
                $res = $this->performRequest($panel, 'POST', '/inbounds/addClient', $body, false);
                if ($res === null) {
                    \Log::error("Failed to add client (v2) on panel $pannelID inbound $inboundId");
                    return false;
                }
            }

            return ['uuid' => $uuid, 'subId' => $client['subId'], 'email' => $client['email']];

        } catch (\Throwable $th) {
            \Log::error('addUserToSanaeiPanel error: ' . $th->getMessage());
            return false;
        }
    }

    public function changeUserActivationOfSanaeiPanelApi(Request $request)
    {
        try {
            $found = $this->findClientByUUID($request->pannelID, $request->uuid);
            if (!$found) {
                return response()->json(['success' => false, 'msg' => 'Client not found'], 404);
            }

            $res = $this->updateClient($request->pannelID, $found['client']['id'], ['enable' => (bool) $request->enable]);
            if ($res) {
                return response()->json(['success' => true], 200);
            }
            return response()->json(['success' => false], 400);
        } catch (\Throwable $th) {
            \Log::error('changeUserActivationOfSanaeiPanelApi error: ' . $th->getMessage());
            return response()->json(['success' => false, 'msg' => $th->getMessage()], 500);
        }
    }

    public function getUserLinks($panelOrId, $uuid, $remark = '', $inboundId = null, $email = null)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return [];

            if (!$this->login($panel)) {
                return [];
            }

            $panel->refresh();

            // v3: fetch links directly from panel
            if ($email === null) {
                $found = $this->findClientByUUID($panel, $uuid);
                $email = $found['client']['email'] ?? null;
            }
            if ($email) {
                $linksRes = $this->performRequestWithFallback(
                    $panel,
                    'GET',
                    '/clients/links/' . rawurlencode($email),
                    '/inbounds/getClientTraffics/' . rawurlencode($email)
                );
                $obj = $linksRes['obj'] ?? null;
                if (is_array($obj)) {
                    if (isset($obj[0]) && is_string($obj[0])) {
                        return $obj;
                    }
                    if (isset($obj['links']) && is_array($obj['links'])) {
                        return $obj['links'];
                    }
                }
            }

            $inboundId = $inboundId ?: ($panel->inbound_id ?: 1);
            $inbound = $this->getInboundFromPanel($panel, $inboundId);
            if (!$inbound)
                return [];

            return $this->generateLinksFromInboundData($inbound, $uuid, $remark, $this->getServerHost($panel));

        } catch (\Throwable $th) {
            \Log::error('getUserLinks error: ' . $th->getMessage());
            return [];
        }
    }

    private function getInboundFromPanel($panel, $id)
    {
        $base = $this->baseUrl($panel);
        $prefix = $this->apiPrefixFor($panel);
        $url = $base . $prefix . "/inbounds/get/$id";

        $r = $this->httpWithAuth($panel)->get($url);
        if ($r->status() === 404 && !empty($panel->cookie_session)) {
            try {
                $raw = $this->rawGetWithCookie($panel, $url);
                if ($raw->ok()) {
                    $r = $raw;
                }
            } catch (\Throwable $th) {
                \Log::warning("Raw cookie GET failed: " . $th->getMessage());
            }
        }
        $json = $r->json();
        if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
            $this->apiPrefix = $prefix;
            return $json['obj'];
        }

        $listUrl = $base . $prefix . "/inbounds/list";
        $r = $this->httpWithAuth($panel)->get($listUrl);
        if ($r->status() === 404 && !empty($panel->cookie_session)) {
            try {
                $raw = $this->rawGetWithCookie($panel, $listUrl);
                if ($raw->ok()) {
                    $r = $raw;
                }
            } catch (\Throwable $th) {
                \Log::warning("Raw cookie GET failed for list: " . $th->getMessage());
            }
        }
        $json = $r->json();
        if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
            $this->apiPrefix = $prefix;
            foreach ($json['obj'] as $item) {
                if ($item['id'] == $id) {
                    return $item;
                }
            }
            if (count($json['obj']) > 0) {
                return $json['obj'][0];
            }
        }

        \Log::error("Inbound $id not found and no fallbacks available.");
        return null;
    }
    private function generateLinksFromInboundData($inbound, $uuid, $remark, $host)
    {
        $links = [];
        $protocol = $inbound['protocol'];
        $port = $inbound['port'];
        $stream = $this->decodeJsonField($inbound['streamSettings'] ?? null) ?? [];
        $network = $stream['network'] ?? 'tcp';
        $security = $stream['security'] ?? 'none';

        $sni = '';
        if (isset($stream['tlsSettings']['serverName']))
            $sni = $stream['tlsSettings']['serverName'];
        if (isset($stream['realitySettings']['serverNames'][0]))
            $sni = $stream['realitySettings']['serverNames'][0];

        if ($protocol === 'vless') {
            $query = ['type' => $network, 'security' => $security, 'encryption' => 'none'];
            if ($sni)
                $query['sni'] = $sni;
            if (isset($stream['realitySettings']['publicKey']))
                $query['pbk'] = $stream['realitySettings']['publicKey'];
            if (isset($stream['realitySettings']['fingerprint']))
                $query['fp'] = $stream['realitySettings']['fingerprint'];
            if (isset($stream['realitySettings']['shortId']))
                $query['sid'] = $stream['realitySettings']['shortId'];

            // Network-specific parameters
            if ($network === 'tcp') {
                if (isset($stream['tcpSettings']['header']['type']) && $stream['tcpSettings']['header']['type'] === 'http') {
                    $query['headerType'] = 'http';
                    if (isset($stream['tcpSettings']['header']['request']['headers']['Host'][0])) {
                        $query['host'] = $stream['tcpSettings']['header']['request']['headers']['Host'][0];
                    }
                    if (isset($stream['tcpSettings']['header']['request']['path'][0])) {
                        $query['path'] = $stream['tcpSettings']['header']['request']['path'][0];
                    }
                }
            } elseif ($network === 'ws') {
                if (isset($stream['wsSettings']['path'])) {
                    $query['path'] = $stream['wsSettings']['path'];
                }
                if (isset($stream['wsSettings']['headers']['Host'])) {
                    $query['host'] = $stream['wsSettings']['headers']['Host'];
                }
            } elseif ($network === 'grpc') {
                if (isset($stream['grpcSettings']['serviceName'])) {
                    $query['serviceName'] = $stream['grpcSettings']['serviceName'];
                }
            } elseif ($network === 'http') {
                if (isset($stream['httpSettings']['host'][0])) {
                    $query['host'] = $stream['httpSettings']['host'][0];
                }
                if (isset($stream['httpSettings']['path'])) {
                    $query['path'] = $stream['httpSettings']['path'];
                }
            }

            $q = http_build_query($query);
            $links[] = "vless://$uuid@$host:$port?$q#" . rawurlencode($remark);
        }

        return $links;
    }

    /**
     * Perform a GET request to $url using a raw Cookie header built from panel->cookie_session
     * Some panel setups require the Cookie header to be present exactly as a single header.
     */
    private function rawGetWithCookie(Pannel $panel, $url)
    {
        $headers = $this->headers($panel, false);
        $cookieHeader = $this->buildCookieHeader($panel);
        if ($cookieHeader !== '') {
            $headers['Cookie'] = $cookieHeader;
        }
        $headers['X-Raw-Cookie-Retry'] = '1';
        return $this->panelHttp()->withHeaders($headers)->get($url);
    }

    private function rawPostWithCookie(Pannel $panel, $url, $body, $asJson = true)
    {
        $headers = $this->headers($panel, false);
        $cookieHeader = $this->buildCookieHeader($panel);
        if ($cookieHeader !== '') {
            $headers['Cookie'] = $cookieHeader;
        }
        $headers = $this->applyCsrfHeader($panel, $headers, true);
        $headers['X-Raw-Cookie-Retry'] = '1';

        $req = $this->panelHttp()->withHeaders($headers);
        if ($asJson) {
            return $req->post($url, $body);
        }

        return $req->asForm()->post($url, $body);
    }

    /**
     * Generic request helper that tries known API prefixes and falls back to raw cookie requests when needed.
     * Returns decoded JSON array on success, or null on failure.
     */
    private function sendPanelRequest(
        Pannel $panel,
        string $method,
        string $url,
        $body = null,
        bool $asJson = true,
        bool $refreshCsrf = false
    ) {
        $isPost = strtoupper($method) !== 'GET';
        if ($refreshCsrf && $isPost && $this->isV3($panel)) {
            $this->ensureCsrfToken($panel, true);
        }

        if ($isPost) {
            try {
                $preview = json_encode($body ?? []);
            } catch (\Throwable $th) {
                $preview = '[unserializable body]';
            }
            \Log::debug('POST ' . $url . ' asJson=' . ($asJson ? '1' : '0') . ' body_preview=' . substr($preview, 0, 2000));

            if ($asJson) {
                return $this->httpWithAuth($panel, true)->asJson()->post($url, $body ?? []);
            }

            return $this->httpWithAuth($panel, true)->asForm()->post($url, $body ?? []);
        }

        return $this->httpWithAuth($panel, false)->get($url);
    }

    private function performRequest(Pannel $panel, string $method, string $path, $body = null, $asJson = true)
    {
        $base = $this->baseUrl($panel);
        $prefix = $this->apiPrefixFor($panel);
        $url = $base . $prefix . $path;
        try {
            $r = $this->sendPanelRequest($panel, $method, $url, $body, $asJson);
            if ($r->status() === 403 && strtoupper($method) !== 'GET' && $this->isV3($panel)) {
                \Log::info("POST $url returned 403, refreshing CSRF and retrying");
                $r = $this->sendPanelRequest($panel, $method, $url, $body, $asJson, true);
            }
            if ($r->status() === 404 && !empty($panel->cookie_session)) {
                if (strtoupper($method) === 'GET') {
                    $raw = $this->rawGetWithCookie($panel, $url);
                } else {
                    $raw = $this->rawPostWithCookie($panel, $url, $body ?? [], $asJson);
                }
                if ($raw->ok()) {
                    $r = $raw;
                }
            }

            $json = null;
            try {
                $json = $r->json();
            } catch (\Throwable $th) {
            }

            if ($r->ok() && is_array($json) && ($json['success'] ?? false)) {
                $this->apiPrefix = $prefix;
                return $json;
            }

            if ($r->ok() && !($json['success'] ?? false) && !empty($panel->cookie_session)) {
                try {
                    if (strtoupper($method) === 'GET') {
                        $raw = $this->rawGetWithCookie($panel, $url);
                    } else {
                        $raw = $this->rawPostWithCookie($panel, $url, $body ?? [], $asJson);
                    }
                    $rawJson = null;
                    try {
                        $rawJson = $raw->json();
                    } catch (\Throwable $th) {
                    }
                    if ($raw->ok() && is_array($rawJson) && ($rawJson['success'] ?? false)) {
                        $this->apiPrefix = $prefix;
                        return $rawJson;
                    }
                    \Log::warning('Raw cookie retry for ' . $url . ' returned Status: ' . $raw->status() . ', Body: ' . substr($raw->body(), 0, 2000));
                } catch (\Throwable $th) {
                    \Log::warning('Raw cookie retry exception for ' . $url . ': ' . $th->getMessage());
                }
            }

            $apiMsg = is_array($json) && !empty($json['msg']) ? $json['msg'] : '';
            \Log::warning('Request to ' . $url . ' failed. Status: ' . $r->status()
                . ($apiMsg !== '' ? ', API: ' . $apiMsg : '')
                . ', Body: ' . substr($r->body(), 0, 2000));
        } catch (\Throwable $th) {
            \Log::warning('Request exception for ' . $url . ': ' . $th->getMessage());
        }

        return null;
    }

    // --- High-level API wrappers ---

    public function deleteUser($panelOrId, $uuid)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            $found = $this->findClientByUUID($panel, $uuid);
            if (!$found) {
                \Log::info("deleteUser: client $uuid not found on panel {$panel->id}, treating as already deleted");

                return true;
            }
            $inboundId = $found['inbound']['id'] ?? 1;
            return $this->deleteClient($panel, $inboundId, $uuid);
        } catch (\Throwable $th) {
            \Log::error('deleteUser error: ' . $th->getMessage());
            return false;
        }
    }

    public function deleteClient($panelOrId, $inboundId, $clientId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for deleteClient on panel {$panel->id}");
                return false;
            }

            $found = $this->findClientByUUID($panel, $clientId);
            $email = $found['client']['email'] ?? null;

            if ($this->isV3($panel)) {
                if ($email === null || $email === '') {
                    return false;
                }
                $res = $this->performRequest($panel, 'POST', '/clients/del/' . rawurlencode($email));
                return $res !== null;
            }

            $path = "/inbounds/$inboundId/delClient/$clientId";
            $res = $this->performRequest($panel, 'POST', $path);
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('deleteClient error: ' . $th->getMessage());
            return false;
        }
    }

    public function updateClient($panelOrId, $clientId, array $data)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for updateClient on panel {$panel->id}");
                return false;
            }

            $found = $this->findClientByUUID($panel, $clientId);
            if (!$found) {
                \Log::error("updateClient: client $clientId not found on panel {$panel->id}");
                return false;
            }

            $inbound = $found['inbound'];
            $inboundId = $inbound['id'] ?? ($inbound['listen'] ?? 0);
            $currentClient = $found['client'];
            $mergedClient = array_merge($currentClient, $data);
            $email = $mergedClient['email'] ?? '';

            if ($this->isV3($panel)) {
                if ($email === '') {
                    return false;
                }
                $v3Path = '/clients/update/' . rawurlencode($email);
                $res = $this->performRequest($panel, 'POST', $v3Path, $this->normalizeV3ClientForApi($mergedClient));
                return $res !== null;
            }

            $body = [
                'id' => $inboundId,
                'settings' => json_encode(['clients' => [$mergedClient]]),
            ];

            $paths = [
                "/inbounds/updateClient/$clientId",
                "/inbounds/updateClient/$inboundId",
                "/inbounds/updateClient",
            ];

            foreach ($paths as $path) {
                \Log::debug("Trying updateClient at $path");
                $res = $this->performRequest($panel, 'POST', $path, $body, false);
                if ($res !== null) {
                    return true;
                }
            }

            try {
                $settings = $this->decodeJsonField($inbound['settings'] ?? null);
                if (isset($settings['clients']) && is_array($settings['clients'])) {
                    foreach ($settings['clients'] as &$c) {
                        if (($c['id'] ?? '') === $clientId) {
                            $c = array_merge($c, $data);
                        }
                    }
                    $fullBody = [
                        'id' => $inboundId,
                        'settings' => json_encode($settings),
                    ];
                    \Log::info("updateClient final fallback: sending full settings payload for client $clientId");
                    $res2 = $this->performRequest($panel, 'POST', "/inbounds/updateClient/$inboundId", $fullBody, false);
                    if ($res2 !== null) {
                        return true;
                    }
                }
            } catch (\Throwable $th) {
                \Log::warning('updateClient final fallback failed: ' . $th->getMessage());
            }

            return false;
        } catch (\Throwable $th) {
            \Log::error('updateClient error: ' . $th->getMessage());
            return false;
        }
    }

    /**
     * Recharge a client by UUID: add days to expiry and add GB to total quota.
     * $addDays: integer days to add
    /**
     * $addDays: integer days to add
     * $addGb: integer GB to add
     */
    public function rechargeClient($panelOrId, $uuid, int $addDays = 0, int $addGb = 0)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel) {
                \Log::error("rechargeClient: panel not found");
                return false;
            }

            $found = $this->findClientByUUID($panel, $uuid);
            if (!$found) {
                \Log::error("rechargeClient: client $uuid not found in panel {$panel->id}");
                return false;
            }

            $client = $found['client'];
            $clientId = $client['id'];

            // compute new expiry
            $nowMs = now('UTC')->timestamp * 1000;
            $currentExpiry = (int) ($client['expiryTime'] ?? 0);
            if ($currentExpiry <= 0) {
                $currentExpiry = $nowMs;
            }
            $addMs = $addDays * 86400 * 1000;
            $newExpiry = $currentExpiry + $addMs;

            // compute new totalGB (stored as bytes in this project)
            $currentTotal = (int) ($client['totalGB'] ?? 0);
            $addBytes = $addGb * 1024 * 1024 * 1024;
            $newTotal = $currentTotal + $addBytes;

            $data = [
                // Sanaei API uses updateClient by clientId
                'expiryTime' => $newExpiry,
                'totalGB' => $newTotal,
            ];

            return $this->updateClient($panel, $clientId, $data);
        } catch (\Throwable $th) {
            \Log::error('rechargeClient error: ' . $th->getMessage());
            return false;
        }
    }

    /**
     * Update the client's email (used as the 'remark' / package name in products)
     */
    public function updateClientEmail($panelOrId, $uuid, string $newEmail)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel) {
                \Log::error("updateClientEmail: panel not found");
                return false;
            }

            $found = $this->findClientByUUID($panel, $uuid);
            if (!$found) {
                \Log::error("updateClientEmail: client $uuid not found in panel {$panel->id}");
                return false;
            }

            $client = $found['client'];
            $clientId = $client['id'] ?? null;
            if (!$clientId) {
                \Log::error("updateClientEmail: client id missing for uuid $uuid on panel {$panel->id}");
                return false;
            }

            $ok = $this->updateClient($panel, $clientId, ['email' => $newEmail]);
            if ($ok) {
                \Log::info("updateClientEmail: updated email for client $clientId on panel {$panel->id} to $newEmail");
                return true;
            }
            \Log::warning("updateClientEmail: updateClient returned false for client $clientId on panel {$panel->id}");
            return false;
        } catch (\Throwable $th) {
            \Log::error('updateClientEmail error: ' . $th->getMessage());
            return false;
        }
    }

    public function resetClientTraffic($panelOrId, $inboundId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for resetClientTraffic on panel {$panel->id}");
                return false;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                "/clients/resetTraffic/$encoded",
                "/inbounds/$inboundId/resetClientTraffic/$encoded"
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('resetClientTraffic error: ' . $th->getMessage());
            return false;
        }
    }

    public function changeUserActivation($panelOrId, $uuid, bool $enable)
    {
        return $this->updateClient($panelOrId, $uuid, ['enable' => $enable]);
    }

    public function updateLimits($panelOrId, $uuid, int $days, int $gb)
    {
        $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
        if (!$panel) {
            return false;
        }

        $found = $this->findClientByUUID($panel, $uuid);
        if (!$found) {
            return false;
        }

        $client = $found['client'];
        $inboundId = $found['inbound']['id'] ?? 1;

        // 1. Reset traffic
        $this->resetClientTraffic($panel, $inboundId, $client['email']);

        // 2. Update expiry and totalGB
        $newExpiry = now('UTC')->addDays($days)->timestamp * 1000;
        $newTotal = $gb * 1024 * 1024 * 1024;

        return $this->updateClient($panel, $uuid, [
            'expiryTime' => $newExpiry,
            'totalGB' => $newTotal,
            'enable' => true
        ]);
    }

    public function resetAllTraffics($panelOrId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for resetAllTraffics on panel {$panel->id}");
                return false;
            }
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                '/clients/resetAllTraffics',
                '/inbounds/resetAllTraffics'
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('resetAllTraffics error: ' . $th->getMessage());
            return false;
        }
    }

    public function delDepletedClients($panelOrId, $inboundId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                \Log::error("Login failed for delDepletedClients on panel {$panel->id}");
                return false;
            }
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                '/clients/delDepleted',
                "/inbounds/delDepletedClients/$inboundId"
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('delDepletedClients error: ' . $th->getMessage());
            return false;
        }
    }

    public function getClientTrafficsByEmail($panelOrId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'GET',
                "/clients/traffic/$encoded",
                "/inbounds/getClientTraffics/$encoded"
            );
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('getClientTrafficsByEmail error: ' . $th->getMessage());
            return null;
        }
    }

    public function getClientTrafficsById($panelOrId, $id)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            if ($this->isV3($panel)) {
                $found = $this->findClientByUUID($panel, $id);
                $email = $found['client']['email'] ?? null;
                if ($email) {
                    return $this->getClientTrafficsByEmail($panel, $email);
                }
                return null;
            }
            $path = "/inbounds/getClientTrafficsById/$id";
            $res = $this->performRequest($panel, 'GET', $path);
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('getClientTrafficsById error: ' . $th->getMessage());
            return null;
        }
    }

    public function onlines($panelOrId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                '/clients/onlines',
                '/inbounds/onlines'
            );
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('onlines error: ' . $th->getMessage());
            return null;
        }
    }

    public function lastOnline($panelOrId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                '/clients/lastOnline',
                '/inbounds/lastOnline'
            );
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('lastOnline error: ' . $th->getMessage());
            return null;
        }
    }

    public function clientIps($panelOrId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                "/clients/ips/$encoded",
                "/inbounds/clientIps/$encoded"
            );
            return $res['obj'] ?? null;
        } catch (\Throwable $th) {
            \Log::error('clientIps error: ' . $th->getMessage());
            return null;
        }
    }

    public function clearClientIps($panelOrId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                return false;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                "/clients/clearIps/$encoded",
                "/inbounds/clearClientIps/$encoded"
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('clearClientIps error: ' . $th->getMessage());
            return false;
        }
    }

    public function delClientByEmail($panelOrId, $inboundId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return false;

            if (!$this->login($panel)) {
                return false;
            }
            $encoded = rawurlencode($email);
            $res = $this->performRequestWithFallback(
                $panel,
                'POST',
                "/clients/del/$encoded",
                "/inbounds/$inboundId/delClientByEmail/$encoded"
            );
            return $res !== null;
        } catch (\Throwable $th) {
            \Log::error('delClientByEmail error: ' . $th->getMessage());
            return false;
        }
    }

    /**
     * Find a client in an inbound by email and return client data and inbound id
     * Returns array ['inbound' => <inbound>, 'client' => <client>] or null
     */
    public function findClientByEmail($panelOrId, $email)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }

            if ($this->isV3($panel)) {
                $detail = $this->performRequest($panel, 'GET', '/clients/get/' . rawurlencode($email));
                if ($detail !== null && isset($detail['obj']['client'])) {
                    $inboundIds = $detail['obj']['inboundIds'] ?? [1];
                    return $this->normalizeV3ClientRecord($detail['obj']['client'], $inboundIds);
                }
                return null;
            }

            $res = $this->performRequest($panel, 'GET', '/inbounds/list');
            $list = $res['obj'] ?? [];
            foreach ($list as $inbound) {
                $settings = $this->decodeJsonField($inbound['settings'] ?? null);
                $clients = $settings['clients'] ?? [];
                foreach ($clients as $client) {
                    if (($client['email'] ?? '') === $email) {
                        return ['inbound' => $inbound, 'client' => $client];
                    }
                }
            }
            return null;
        } catch (\Throwable $th) {
            \Log::error('findClientByEmail error: ' . $th->getMessage());
            return null;
        }
    }

    /**
     * Find a client by UUID across inbounds. Returns ['inbound'=>..., 'client'=>...] or null
     */
    public function findClientByUUID($panelOrId, $uuid)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            if (!$this->login($panel)) {
                return null;
            }

            if ($this->isV3($panel)) {
                $clientsRes = $this->performRequest($panel, 'GET', '/clients/list');
                if ($clientsRes !== null) {
                    foreach ($clientsRes['obj'] ?? [] as $rec) {
                        if (($rec['uuid'] ?? '') === $uuid) {
                            $email = $rec['email'] ?? '';
                            if ($email !== '') {
                                $detail = $this->performRequest($panel, 'GET', '/clients/get/' . rawurlencode($email));
                                if ($detail !== null && isset($detail['obj']['client'])) {
                                    return $this->normalizeV3ClientRecord(
                                        $detail['obj']['client'],
                                        $detail['obj']['inboundIds'] ?? [1]
                                    );
                                }
                            }
                            return $this->normalizeV3ClientRecord($rec);
                        }
                    }
                }
                return null;
            }

            $res = $this->performRequest($panel, 'GET', '/inbounds/list');
            $list = $res['obj'] ?? [];
            foreach ($list as $inbound) {
                $settings = $this->decodeJsonField($inbound['settings'] ?? null);
                $clients = $settings['clients'] ?? [];
                foreach ($clients as $client) {
                    if (($client['id'] ?? '') === $uuid) {
                        return ['inbound' => $inbound, 'client' => $client];
                    }
                }
            }
            return null;
        } catch (\Throwable $th) {
            \Log::error('findClientByUUID error: ' . $th->getMessage());
            return null;
        }
    }

    /**
     * Returns all clients from all inbounds in a format compatible with HiddifyConfig model
     */
    public function getAllClients($panelOrId)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return [];

            if (!$this->login($panel))
                return [];

            if ($this->isV3($panel)) {
                $clientsRes = $this->performRequest($panel, 'GET', '/clients/list');
                if ($clientsRes === null) {
                    return [];
                }
                $allClients = [];
                foreach ($clientsRes['obj'] ?? [] as $rec) {
                    $uuid = $rec['uuid'] ?? ($rec['id'] ?? '');
                    $email = $rec['email'] ?? '';
                    $total = (int) ($rec['totalGB'] ?? 0);
                    $enable = $rec['enable'] ?? true;
                    $expiry = $rec['expiryTime'] ?? ($rec['expiry_time'] ?? 0);
                    $traffic = $this->getClientTrafficsByEmail($panel, $email);
                    $up = (int) ($traffic['up'] ?? 0);
                    $down = (int) ($traffic['down'] ?? 0);

                    $allClients[] = [
                        'uuid' => $uuid,
                        'name' => $email,
                        'current_usage_GB' => round(($up + $down) / 1024 / 1024 / 1024, 2),
                        'usage_limit_GB' => round($total / 1024 / 1024 / 1024, 2),
                        'package_days' => $this->packageDaysFromSanaeiExpiry($expiry),
                        'is_active' => $enable,
                    ];
                }
                return $allClients;
            }

            $res = $this->performRequest($panel, 'GET', '/inbounds/list');
            $list = $res['obj'] ?? [];
            $allClients = [];

            foreach ($list as $inbound) {
                $settings = $this->decodeJsonField($inbound['settings'] ?? null);
                $clients = $settings['clients'] ?? [];
                foreach ($clients as $client) {
                    $uuid = $client['id'] ?? ($client['uuid'] ?? '');
                    $email = $client['email'] ?? '';

                    // Basic info
                    $total = (int) ($client['totalGB'] ?? ($client['total'] ?? 0));
                    $expiry = $client['expiryTime'] ?? ($client['expiry_time'] ?? 0);
                    $enable = $client['enable'] ?? true;

                    // We might want to fetch traffic for each, but that's many requests.
                    // For the list view, maybe just basic info is enough or we use what's in the client object.
                    $up = (int) ($client['up'] ?? 0);
                    $down = (int) ($client['down'] ?? 0);

                    $allClients[] = [
                        'uuid' => $uuid,
                        'name' => $email,
                        'current_usage_GB' => round(($up + $down) / 1024 / 1024 / 1024, 2),
                        'usage_limit_GB' => round($total / 1024 / 1024 / 1024, 2),
                        'package_days' => $this->packageDaysFromSanaeiExpiry($expiry),
                        'is_active' => $enable,
                    ];
                }
            }
            return $allClients;
        } catch (\Throwable $th) {
            \Log::error('getAllClients error: ' . $th->getMessage());
            return [];
        }
    }

    /**
     * Remaining whole days until Sanaei/3x-ui expiryTime.
     * expiryTime: 0 = unlimited, >0 unix ms (or seconds), <0 relative ms from now (panel convention).
     */
    private function packageDaysFromSanaeiExpiry(mixed $expiryRaw): int
    {
        $expiry = (int) $expiryRaw;
        if ($expiry === 0) {
            return 0;
        }

        $nowMs = (int) floor(microtime(true) * 1000);
        if ($expiry < 0) {
            // Relative remaining duration encoded as negative milliseconds.
            $secondsLeft = (int) ceil(abs($expiry) / 1000);
        } elseif ($expiry > 9999999999) {
            // Absolute expiry in milliseconds.
            $secondsLeft = (int) ceil(($expiry - $nowMs) / 1000);
        } else {
            // Absolute expiry in seconds.
            $secondsLeft = $expiry - time();
        }

        if ($secondsLeft <= 0) {
            return 0;
        }

        return (int) ceil($secondsLeft / 86400);
    }

    /**
     * Returns structured status for a client by uuid: enable, current_usage_GB, usage_limit_GB, start_date, package_days
     */
    public function getClientStatus($panelOrId, $uuid)
    {
        try {
            $panel = $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
            if (!$panel)
                return null;

            $found = $this->findClientByUUID($panel, $uuid);
            if (!$found)
                return null;
            $inbound = $found['inbound'];
            $client = $found['client'];

            // usage from traffics endpoint
            $usageObj = $this->getClientTrafficsByEmail($panel, $client['email'] ?? '');

            $up = (int) ($client['up'] ?? 0);
            $down = (int) ($client['down'] ?? 0);
            $limitBytes = (int) ($client['totalGB'] ?? ($client['total'] ?? 0));
            $expiryMs = $client['expiryTime'] ?? ($client['expiry_time'] ?? 0);

            if (is_array($usageObj)) {
                if ($up <= 0)
                    $up = (int) ($usageObj['up'] ?? 0);
                if ($down <= 0)
                    $down = (int) ($usageObj['down'] ?? 0);
                if ($expiryMs <= 0) {
                    $expiryMs = (int) ($usageObj['expiryTime'] ?? 0);
                }
            }

            $usageBytes = $up + $down;
            $current_usage_GB = round($usageBytes / 1024 / 1024 / 1024, 2);
            $usage_limit_GB = round($limitBytes / 1024 / 1024 / 1024, 2);

            // dates
            $createdMs = $client['created_at'] ?? ($client['createdAt'] ?? null);
            $startDate = null;
            $package_days = 0;
            if ($createdMs) {
                $startSec = intval($createdMs / 1000);
                $startDate = Carbon::createFromTimestamp($startSec)->toIso8601String();
            }
            if ($createdMs && $expiryMs > 0) {
                $startSec = intval($createdMs / 1000);
                $expirySec = intval($expiryMs / 1000);
                $diffDays = max(0, ceil(($expirySec - $startSec) / 86400));
                $package_days = intval($diffDays);
            }

            $isEnabled = ($client['enable'] ?? ($usageObj['enable'] ?? true));

            return [
                'enable' => $isEnabled,
                'is_active' => $isEnabled, // for compatibility with Hiddify models
                'current_usage_GB' => $current_usage_GB,
                'usage_limit_GB' => $usage_limit_GB,
                'start_date' => $startDate,
                'package_days' => $package_days,
                'inbound' => $inbound,
                'client' => $client,
                'traffic' => $usageObj,
            ];
        } catch (\Throwable $th) {
            \Log::error('getClientStatus error: ' . $th->getMessage());
            return null;
        }
    }

    /**
     * Alias kept for backward compatibility with AgentProductController.
     */
    public function updateUser($panelOrId, $uuid, array $data)
    {
        if (isset($data['email'])) {
            return $this->updateClientEmail($panelOrId, $uuid, (string) $data['email']);
        }
        return $this->updateClient($panelOrId, $uuid, $data);
    }

    public function checkSanaeiPanelUrl(Request $request)
    {
        $adminUrl = $this->normalizeAdminUrl($request->pannelUrl ?? $request->admin_url ?? '');
        $panel = new Pannel([
            'type' => 'sanaei',
            'admin_url' => $adminUrl,
            'username' => $request->username ?? 'admin',
            'password' => $request->password ?? '',
            'token' => !empty($request->token) ? $request->token : ($request->apiToken ?? null),
            'api_version' => $this->normalizeApiVersion($request->api_version) ?? 'v3',
        ]);
        $ok = $this->login($panel);
        $version = $ok ? $this->detectApiVersion($panel) : null;
        $message = $ok
            ? null
            : 'اتصال برقرار نشد. اگر API Token ندارید فیلد Token را خالی بگذارید؛ در غیر این صورت توکن را از تنظیمات پنل 3x-ui کپی کنید.';
        return response()->json([
            'success' => $ok,
            'message' => $message,
            'api_version' => $version,
        ], $ok ? 200 : 400);
    }

    private function normalizeAdminUrl(?string $adminUrl): string
    {
        $adminUrl = trim((string) $adminUrl);
        if ($adminUrl === '') {
            return '';
        }
        if (str_ends_with($adminUrl, '/')) {
            $adminUrl = rtrim($adminUrl, '/');
        }
        $adminPos = stripos($adminUrl, 'admin');
        if ($adminPos !== false) {
            $adminUrl = substr($adminUrl, 0, $adminPos);
            $adminUrl = rtrim($adminUrl, '/');
        }
        return $adminUrl;
    }

    public function addSanaeiPannel(Request $request)
    {
        try {
            $licenseService = new LicenseFeatureService();
            if (! $licenseService->canAddPanel(Pannel::count())) {
                return $licenseService->panelLimitReachedResponse();
            }

            $adminUrl = $this->normalizeAdminUrl($request->admin_url ?? $request->pannelUrl ?? '');

            $panel = new Pannel();
            $panel->type = 'sanaei';
            $panel->username = $request->username ?? 'admin';
            $panel->password = $request->password ?? '';
            $panel->token = !empty($request->token) ? $request->token : null;
            $panel->location = $request->location ?? null;
            $panel->url_port = $request->url_port ?? (parse_url($adminUrl, PHP_URL_HOST) ?: null);
            $panel->sub_port = $request->sub_port ?: null;
            $panel->admin_url = $adminUrl ?: null;
            $panel->user_link = $request->user_link ?? null;
            $panel->capacity = $request->capacity ?? 1333333;
            $panel->api_version = $this->normalizeApiVersion($request->api_version) ?? 'v3';
            $panel->save();

            if (!$this->login($panel)) {
                $panel->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'اتصال به پنل سنایی برقرار نشد. نام کاربری، رمز یا API Token را بررسی کنید.',
                ], 422);
            }

            return response()->json(['success' => true, 'id' => $panel->id, 'api_version' => $panel->api_version], 200);
        } catch (\Throwable $th) {
            \Log::error('addSanaeiPannel failed: ' . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در ذخیره پنل.',
            ], 500);
        }
    }

    public function updateSanaeiPannel(Request $request)
    {
        try {
            $panel = Pannel::find($request->id);
            if (!$panel || $panel->type !== 'sanaei') {
                return response()->json(['success' => false, 'message' => 'پنل سنایی یافت نشد.'], 404);
            }

            $adminUrl = $this->normalizeAdminUrl(
                $request->admin_url ?? $request->pannelUrl ?? (string) $panel->admin_url
            );

            $panel->username = $request->username ?? $panel->username ?? 'admin';
            $panel->password = $request->password ?? $panel->password ?? '';
            $panel->token = !empty($request->token) ? $request->token : null;
            $panel->location = $request->location ?? $panel->location;
            $panel->url_port = $request->url_port ?? (parse_url($adminUrl, PHP_URL_HOST) ?: $panel->url_port);
            $panel->sub_port = $request->sub_port ?: null;
            $panel->admin_url = $adminUrl ?: $panel->admin_url;
            $panel->user_link = $request->user_link ?? $panel->user_link;
            $panel->capacity = $request->capacity ?? $panel->capacity;
            if ($request->has('api_version')) {
                $panel->api_version = $this->normalizeApiVersion($request->api_version) ?? $panel->api_version;
            }
            $panel->cookie_session = null;
            $this->clearPanelAuthCache($panel);
            $panel->save();

            if (!$this->login($panel)) {
                return response()->json([
                    'success' => false,
                    'message' => 'اطلاعات ذخیره شد اما اتصال به پنل برقرار نشد. نام کاربری، رمز یا API Token را بررسی کنید.',
                ], 422);
            }

            return response()->json(['success' => true, 'id' => $panel->id, 'api_version' => $panel->api_version], 200);
        } catch (\Throwable $th) {
            \Log::error('updateSanaeiPannel failed: ' . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در ویرایش پنل.',
            ], 500);
        }
    }

    public function checkLoginStatus($pannelID)
    {
        $panel = Pannel::find($pannelID);
        if (!$panel) {
            return response()->json(['success' => false, 'msg' => 'Panel not found'], 404);
        }
        $ok = $this->login($panel);
        $version = $ok ? $this->detectApiVersion($panel) : null;
        return response()->json([
            'success' => $ok,
            'api_version' => $version,
            'has_token' => $this->authToken($panel) !== '',
        ], $ok ? 200 : 401);
    }

    public function refreshLogin($pannelID)
    {
        $panel = Pannel::find($pannelID);
        if (!$panel) {
            return response()->json(['success' => false, 'msg' => 'Panel not found'], 404);
        }
        $this->clearPanelAuthCache($panel);
        $panel->cookie_session = null;
        $panel->save();
        $ok = $this->login($panel);
        return response()->json([
            'success' => $ok,
            'api_version' => $ok ? $this->detectApiVersion($panel) : null,
        ], $ok ? 200 : 401);
    }

    public function syncInbounds($pannelID)
    {
        $panel = Pannel::find($pannelID);
        if (!$panel) {
            return response()->json(['success' => false, 'msg' => 'Panel not found'], 404);
        }
        if (!$this->login($panel)) {
            return response()->json(['success' => false, 'msg' => 'Login failed'], 401);
        }
        $res = $this->performRequestWithFallback(
            $panel,
            'GET',
            '/inbounds/options',
            '/inbounds/list'
        );
        if ($res === null) {
            return response()->json(['success' => false, 'msg' => 'Could not fetch inbounds'], 500);
        }
        $inbounds = [];
        foreach ($res['obj'] ?? [] as $item) {
            $inbounds[] = [
                'id' => $item['id'] ?? null,
                'remark' => $item['remark'] ?? ($item['tag'] ?? ''),
                'protocol' => $item['protocol'] ?? '',
                'port' => $item['port'] ?? null,
            ];
        }
        return response()->json(['success' => true, 'inbounds' => $inbounds, 'api_version' => $this->detectApiVersion($panel)]);
    }

    public function checkInboundSources($pannelID)
    {
        $panel = Pannel::find($pannelID);
        if (!$panel) {
            return response()->json(['success' => false], 404);
        }
        if (!$this->login($panel)) {
            return response()->json(['success' => false, 'msg' => 'Login failed'], 401);
        }
        $version = $this->detectApiVersion($panel);
        $list = $this->performRequest($panel, 'GET', '/inbounds/list');
        $clientsCount = 0;
        if ($this->isV3($panel)) {
            $clients = $this->performRequest($panel, 'GET', '/clients/list');
            $clientsCount = count($clients['obj'] ?? []);
        } else {
            foreach ($list['obj'] ?? [] as $inbound) {
                $settings = $this->decodeJsonField($inbound['settings'] ?? null);
                $clientsCount += count($settings['clients'] ?? []);
            }
        }
        return response()->json([
            'success' => true,
            'api_version' => $version,
            'inbounds_count' => count($list['obj'] ?? []),
            'clients_count' => $clientsCount,
        ]);
    }

    public function addUserWithTemplate(Request $request)
    {
        return $this->addUserToSanaeiPanel($request);
    }
}


