<?php

namespace App\Http\Controllers;

use App\Models\BotUser;
use App\Models\Pannel;
use App\Services\ConfigNameService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarzbanPannelController extends Controller
{
    private const USERNAME_MAX_LENGTH = 32;

    private ?int $lastMutationHttpStatus = null;

    private ?array $lastMutationErrorBody = null;

    public function sanitizeUsername(string $username): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9]/', '', $username);
    }

    public function buildBotUsername(int|string $chatId, int|string $productId): string
    {
        $botUser = BotUser::query()->where('account_id', $chatId)->first();
        if (ConfigNameService::useAdminAliasInConfigName() && $botUser && filled($botUser->admin_alias)) {
            $sanitized = $this->sanitizeUsername(BotUser::resolveConfigAccountLabel($chatId, $productId));
            if ($sanitized !== '') {
                return $sanitized;
            }
        }

        return $this->sanitizeUsername(
            ConfigNameService::buildMarzbanFallbackUsername($chatId, $productId)
        );
    }

    public function buildTestAccountUsername(int|string $chatId): string
    {
        $botUser = BotUser::query()->where('account_id', $chatId)->first();
        if (ConfigNameService::useAdminAliasInConfigName() && $botUser && filled($botUser->admin_alias)) {
            $sanitized = $this->sanitizeUsername(BotUser::resolveConfigAccountLabel($chatId, 'Test'));
            if ($sanitized !== '') {
                return $sanitized;
            }
        }

        return $this->sanitizeUsername(
            ConfigNameService::buildMarzbanTestFallbackUsername($chatId)
        );
    }

    protected function makeUniqueUsername(string $baseUsername): string
    {
        $suffix = bin2hex(random_bytes(2));
        $base = $this->sanitizeUsername($baseUsername);
        if ($base === '') {
            $base = 'BotUser';
        }

        $maxBaseLength = self::USERNAME_MAX_LENGTH - strlen($suffix);
        if ($maxBaseLength < 1) {
            $maxBaseLength = 1;
        }

        return substr($base, 0, $maxBaseLength) . $suffix;
    }

    protected function resolvePanel($panelOrId): ?Pannel
    {
        return $panelOrId instanceof Pannel ? $panelOrId : Pannel::find($panelOrId);
    }

    private function baseUrl(Pannel $panel): string
    {
        $url = trim((string) ($panel->url_port ?: $panel->admin_url));
        $url = str_replace('/dashboard/', '', $url);
        $url = str_replace('/dashboard', '', $url);

        return rtrim($url, '/');
    }

    private function authToken(Pannel $panel): string
    {
        $token = trim((string) ($panel->token ?? ''));
        if ($token === '' || $token === 'Bearer') {
            return '';
        }
        if (! str_starts_with(strtolower($token), 'bearer ')) {
            return 'Bearer ' . $token;
        }

        return $token;
    }

    private function headers(Pannel $panel): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'authorization' => $this->authToken($panel),
        ];
    }

    private function extractInboundTag(mixed $tag): string
    {
        if (is_string($tag)) {
            return trim($tag);
        }

        if (is_array($tag)) {
            foreach (['tag', 'name', 'remark'] as $key) {
                if (! empty($tag[$key]) && is_string($tag[$key])) {
                    return trim($tag[$key]);
                }
            }
        }

        if (is_object($tag)) {
            foreach (['tag', 'name', 'remark'] as $key) {
                if (! empty($tag->$key) && is_string($tag->$key)) {
                    return trim($tag->$key);
                }
            }
        }

        return '';
    }

    private function parseInboundsResponse(array $body): array
    {
        if (array_is_list($body)) {
            $result = [];
            foreach ($body as $item) {
                if (is_string($item)) {
                    $tag = trim($item);
                    if ($tag === '') {
                        continue;
                    }
                    $protocol = strtolower((string) strtok($tag, ' '));
                    if ($protocol === '') {
                        continue;
                    }
                    $result[$protocol][] = $tag;

                    continue;
                }

                $row = is_array($item) ? $item : (array) $item;
                $protocol = strtolower(trim((string) ($row['protocol'] ?? '')));
                $tag = $this->extractInboundTag($item);
                if ($protocol === '' || $tag === '') {
                    continue;
                }
                $result[$protocol][] = $tag;
            }

            return collect($result)
                ->map(fn ($tags) => array_values(array_unique($tags)))
                ->all();
        }

        $result = [];
        foreach ($body as $protocol => $tags) {
            if (! is_array($tags) || $tags === []) {
                continue;
            }

            $names = [];
            foreach ($tags as $tag) {
                $name = $this->extractInboundTag($tag);
                if ($name !== '') {
                    $names[] = $name;
                }
            }

            if ($names !== []) {
                $result[strtolower((string) $protocol)] = array_values(array_unique($names));
            }
        }

        return $result;
    }

    private function inboundsApiPath(Pannel $panel): string
    {
        return $panel->type === Pannel::TYPE_PASARGUARD
            ? '/api/inbounds/details'
            : '/api/inbounds';
    }

    private function fetchLiveInboundsMap(Pannel $panel): ?array
    {
        try {
            $body = $this->performRequest($panel, 'GET', $this->inboundsApiPath($panel));
            if (! is_array($body)) {
                return null;
            }

            return $this->parseInboundsResponse($body);
        } catch (\Throwable $e) {
            Log::warning('Marzban could not fetch live inbounds', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveLiveInboundsMap(Pannel $panel): ?array
    {
        $liveInbounds = $this->fetchLiveInboundsMap($panel);
        if ($liveInbounds !== null) {
            return $liveInbounds;
        }

        if ($this->refreshAuthToken($panel)) {
            $panel->refresh();

            return $this->fetchLiveInboundsMap($panel);
        }

        return null;
    }

    private function buildProxyPayload(Pannel $panel, ?array $selectedInbounds = null): array
    {
        $liveInbounds = $this->resolveLiveInboundsMap($panel);
        if ($liveInbounds === null || $liveInbounds === []) {
            Log::error('Marzban buildProxyPayload failed: no live inbounds on panel', [
                'panel_id' => $panel->id,
            ]);

            return [[], []];
        }

        if ($selectedInbounds !== null && $selectedInbounds !== []) {
            $filtered = [];
            foreach ($selectedInbounds as $protocol => $tags) {
                $protocolKey = strtolower((string) $protocol);
                if ($protocolKey === '' || ! is_array($tags)) {
                    continue;
                }
                $liveTags = $liveInbounds[$protocolKey] ?? [];
                $validTags = array_values(array_intersect(
                    array_map(static fn ($tag) => trim((string) $tag), $tags),
                    array_map(static fn ($tag) => trim((string) $tag), $liveTags)
                ));
                if ($validTags !== []) {
                    $filtered[$protocolKey] = $validTags;
                }
            }

            if ($filtered === []) {
                Log::error('Marzban buildProxyPayload failed: selected inbounds not found on panel', [
                    'panel_id' => $panel->id,
                    'selected' => $selectedInbounds,
                ]);

                return [[], []];
            }

            $liveInbounds = $filtered;
        }

        $proxy = [];
        $inbounds = [];
        foreach ($liveInbounds as $protocol => $tags) {
            if ($tags === []) {
                continue;
            }
            $proxy[$protocol] = new \stdClass();
            $inbounds[$protocol] = array_values($tags);
        }

        Log::info('Marzban using panel inbounds', [
            'panel_id' => $panel->id,
            'inbounds' => $inbounds,
            'filtered' => $selectedInbounds !== null && $selectedInbounds !== [],
        ]);

        return [$proxy, $inbounds];
    }

    /**
     * @param  array<string, array<int, string>>|null  $selectedInbounds
     * @param  int[]|null  $selectedGroupIds
     */
    protected function buildUserMutationParams(
        Pannel $panel,
        int $day,
        $volGb,
        ?array $selectedInbounds = null,
        ?array $selectedGroupIds = null,
        bool $assignGroups = true
    ): array {
        [$proxy, $inbounds] = $this->buildProxyPayload($panel, $selectedInbounds);
        if ($inbounds === []) {
            return [];
        }

        return [
            'expire' => $this->expireTimestamp($day),
            'data_limit' => $this->gbToBytes($volGb),
            'proxies' => $proxy,
            'inbounds' => $inbounds,
            'status' => 'active',
        ];
    }

    private function removeInvalidInboundFromParams(array &$params): bool
    {
        $detail = $params['_last_error_detail'] ?? null;
        unset($params['_last_error_detail']);

        $message = '';
        if (is_array($detail) && isset($detail['inbounds'])) {
            $message = (string) $detail['inbounds'];
        } elseif (is_string($detail)) {
            $message = $detail;
        }

        if ($message === '') {
            return false;
        }

        if (! preg_match('/tag:\s*([^,}]+)/', $message, $tagMatch)) {
            return false;
        }
        $badTag = trim($tagMatch[1]);

        $protocol = null;
        if (preg_match('/protocol:\s*([^,}]+)/', $message, $protocolMatch)) {
            $protocol = strtolower(trim($protocolMatch[1]));
        }

        $removed = false;
        foreach ($params['inbounds'] ?? [] as $proto => $tags) {
            if ($protocol !== null && $proto !== $protocol) {
                continue;
            }

            $filtered = array_values(array_filter(
                $tags,
                static fn ($tag) => trim((string) $tag) !== $badTag
            ));

            if (count($filtered) !== count($tags)) {
                $removed = true;
                if ($filtered === []) {
                    unset($params['inbounds'][$proto], $params['proxies'][$proto]);
                } else {
                    $params['inbounds'][$proto] = $filtered;
                }
            }
        }

        return $removed && ($params['inbounds'] ?? []) !== [];
    }

    private function userMutationPayload(array $params): array
    {
        unset($params['_last_error_detail']);

        return $params;
    }

    protected function isUserAlreadyExistsError(?array $errorBody): bool
    {
        if (! is_array($errorBody)) {
            return false;
        }

        $detail = $errorBody['detail'] ?? '';
        if (is_string($detail)) {
            return stripos($detail, 'already exists') !== false;
        }

        return false;
    }

    protected function performUserMutation(Pannel $panel, string $method, string $path, array &$params): ?array
    {
        $maxAttempts = 6;
        $this->lastMutationHttpStatus = null;
        $this->lastMutationErrorBody = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $payload = $this->userMutationPayload($params);
            $response = $this->sendRequest($panel, $method, $path, $payload);

            if ($response !== null && $response->status() === 401 && $this->refreshAuthToken($panel)) {
                $panel->refresh();
                $response = $this->sendRequest($panel, $method, $path, $payload);
            }

            if ($response !== null && $response->successful()) {
                $this->lastMutationHttpStatus = $response->status();
                $body = $response->json();

                return is_array($body) ? $body : [];
            }

            $status = $response?->status();
            $this->lastMutationHttpStatus = $status;
            $errorBody = $response?->json();
            $this->lastMutationErrorBody = is_array($errorBody) ? $errorBody : null;

            Log::info('Marzban API request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $status,
                'body' => $errorBody,
                'attempt' => $attempt,
            ]);

            if ($status === 422 && $attempt < $maxAttempts) {
                $params['_last_error_detail'] = $errorBody['detail'] ?? $errorBody;
                if ($this->removeInvalidInboundFromParams($params)) {
                    Log::info('Marzban retrying after removing invalid inbound', [
                        'panel_id' => $panel->id,
                        'remaining_inbounds' => $params['inbounds'] ?? [],
                    ]);
                    continue;
                }
            }

            return null;
        }

        return null;
    }

    protected function gbToBytes($gb): int
    {
        return (int) round((float) $gb * 1024 * 1024 * 1024);
    }

    /**
     * Normalize panel expire values to a unix timestamp (seconds).
     *
     * Marzban historically returns an int timestamp. Pasarguard (and newer APIs)
     * return ISO-8601 datetime strings. Casting those strings with (int) yields
     * only the year (e.g. 2026), which looks like "expired since 1970" and causes
     * auto-delete to wipe every account.
     */
    private function normalizeExpireTimestamp($expireRaw): int
    {
        if ($expireRaw === null || $expireRaw === '' || $expireRaw === false) {
            return 0;
        }

        if ($expireRaw instanceof \DateTimeInterface) {
            return (int) $expireRaw->getTimestamp();
        }

        if (is_numeric($expireRaw)) {
            $expireTs = (int) $expireRaw;
            if ($expireTs <= 0) {
                return 0;
            }

            // Milliseconds (13+ digits)
            if ($expireTs > 9999999999) {
                return (int) floor($expireTs / 1000);
            }

            return $expireTs;
        }

        if (is_string($expireRaw)) {
            $trimmed = trim($expireRaw);
            if ($trimmed === '' || $trimmed === '0' || strcasecmp($trimmed, 'null') === 0) {
                return 0;
            }

            try {
                return Carbon::parse($trimmed)->utc()->getTimestamp();
            } catch (\Throwable $e) {
                Log::warning('Failed to parse panel expire value', [
                    'expire' => $trimmed,
                    'error' => $e->getMessage(),
                ]);

                return 0;
            }
        }

        return 0;
    }

    protected function expireTimestamp(int $days): int
    {
        $utc = Carbon::now('UTC')->addDays($days);

        return $utc->getTimestamp();
    }

    private function refreshAuthToken(Pannel $panel): bool
    {
        $username = trim((string) ($panel->username ?? ''));
        $password = trim((string) ($panel->password ?? ''));
        if ($username === '' || $password === '') {
            return false;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post($this->baseUrl($panel) . '/api/admin/token', [
                'username' => $username,
                'password' => $password,
            ]);

        if (! $response->successful()) {
            Log::info('Marzban token refresh failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        $data = $response->json();
        $accessToken = trim((string) ($data['access_token'] ?? ''));
        if ($accessToken === '') {
            return false;
        }

        $tokenType = trim((string) ($data['token_type'] ?? 'Bearer'));
        $panel->token = $tokenType . ' ' . $accessToken;
        $panel->save();

        return true;
    }

    private function sendRequest(Pannel $panel, string $method, string $path, ?array $body = null)
    {
        $url = $this->baseUrl($panel) . $path;
        $request = Http::withHeaders($this->headers($panel));

        return match (strtoupper($method)) {
            'GET' => $request->get($url),
            'POST' => $request->post($url, $body ?? []),
            'PUT' => $request->put($url, $body ?? []),
            'DELETE' => $request->delete($url),
            default => null,
        };
    }

    protected function performRequest(Pannel $panel, string $method, string $path, ?array $body = null, bool $allowRetry = true)
    {
        $response = $this->sendRequest($panel, $method, $path, $body);

        if ($response !== null && $response->status() === 401 && $allowRetry && $this->refreshAuthToken($panel)) {
            $panel->refresh();
            $response = $this->sendRequest($panel, $method, $path, $body);
        }

        if ($response === null || ! $response->successful()) {
            Log::info('Marzban API request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response?->status(),
                'body' => $response?->json(),
            ]);

            return null;
        }

        return $response->json();
    }

    public function createUser($panelOrId, string $username, int $day, $volGb, ?array $selectedInbounds = null, ?array $selectedGroupIds = null): array|false
    {
        try {
            $panel = $this->resolvePanel($panelOrId);
            if (! $panel) {
                return false;
            }

            $params = $this->buildUserMutationParams($panel, $day, $volGb, $selectedInbounds, $selectedGroupIds);
            if ($params === []) {
                Log::error('Marzban createUser failed: no valid inbounds for panel', [
                    'panel_id' => $panel->id,
                ]);

                return false;
            }

            $baseUsername = $this->sanitizeUsername($username);
            if ($baseUsername === '') {
                $baseUsername = 'BotUser' . bin2hex(random_bytes(4));
            }

            $body = null;
            for ($usernameAttempt = 1; $usernameAttempt <= 8; $usernameAttempt++) {
                $params['username'] = $usernameAttempt === 1
                    ? $baseUsername
                    : $this->makeUniqueUsername($baseUsername);

                $body = $this->performUserMutation($panel, 'POST', '/api/user', $params);
                if (is_array($body) && ! empty($body['subscription_url'])) {
                    break;
                }

                if ($this->lastMutationHttpStatus !== 409 || ! $this->isUserAlreadyExistsError($this->lastMutationErrorBody)) {
                    return false;
                }

                if ($usernameAttempt === 1) {
                    $existing = $this->getUser($panel, $baseUsername);
                    if (is_array($existing) && ! empty($existing['subscription_url'])) {
                        return $this->buildCreateUserResult($panel, $existing, $baseUsername);
                    }
                }

                Log::info('Marzban createUser retrying after username conflict', [
                    'panel_id' => $panel->id,
                    'base_username' => $baseUsername,
                    'new_username' => $params['username'],
                    'attempt' => $usernameAttempt,
                ]);
            }

            if (! is_array($body) || empty($body['subscription_url'])) {
                return false;
            }

            return $this->buildCreateUserResult($panel, $body, $params['username']);
        } catch (\Throwable $th) {
            Log::error('Marzban createUser failed: ' . $th->getMessage());

            return false;
        }
    }

    protected function buildCreateUserResult(Pannel $panel, array $body, string $username): array
    {
        $mainUrl = $this->baseUrl($panel);
        $subPath = $body['subscription_url'];
        if (! str_starts_with($subPath, '/')) {
            $subPath = '/' . $subPath;
        }

        return [
            'username' => $body['username'] ?? $username,
            'links' => $body['links'] ?? [],
            'subscription_link' => $mainUrl . $subPath,
            'subscription_url' => $subPath,
            'body' => $body,
        ];
    }

    public function getUser($panelOrId, string $username): ?array
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return null;
        }

        $body = $this->performRequest($panel, 'GET', '/api/user/' . rawurlencode($username));

        return is_array($body) ? $body : null;
    }

    public function modifyUser($panelOrId, string $username, int $day, $volGb, bool $resetTraffic = true, ?array $selectedInbounds = null, ?array $selectedGroupIds = null): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $params = $this->buildUserMutationParams($panel, $day, $volGb, $selectedInbounds, $selectedGroupIds);
        if ($params === []) {
            Log::error('Marzban modifyUser failed: no valid inbounds for panel', [
                'panel_id' => $panel->id,
            ]);

            return false;
        }

        $body = $this->performUserMutation(
            $panel,
            'PUT',
            '/api/user/' . rawurlencode($username),
            $params
        );
        if (! is_array($body)) {
            return false;
        }

        if ($resetTraffic) {
            $this->resetTraffic($panel, $username);
        }

        return true;
    }

    public function updateLimits($panelOrId, string $username, int $day, $volGb): bool
    {
        return $this->modifyUser($panelOrId, $username, $day, $volGb, true);
    }

    public function rechargeUser($panelOrId, string $username, int $day, $volGb): bool
    {
        return $this->modifyUser($panelOrId, $username, $day, $volGb, true);
    }

    public function resetTraffic($panelOrId, string $username): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $body = $this->performRequest($panel, 'POST', '/api/user/' . rawurlencode($username) . '/reset');

        return is_array($body);
    }

    public function deleteUser($panelOrId, string $username): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $path = '/api/user/' . rawurlencode($username);
        $response = $this->sendRequest($panel, 'DELETE', $path);

        if ($response !== null && $response->status() === 401 && $this->refreshAuthToken($panel)) {
            $panel->refresh();
            $response = $this->sendRequest($panel, 'DELETE', $path);
        }

        if ($response === null) {
            return false;
        }

        if ($response->status() === 404) {
            return true;
        }

        return $response->successful();
    }

    public function changeUserActivation($panelOrId, string $username, bool $enable): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $params = [
            'status' => $enable ? 'active' : 'disabled',
        ];

        $body = $this->performRequest($panel, 'PUT', '/api/user/' . rawurlencode($username), $params);

        return is_array($body);
    }

    public function renameUser($panelOrId, string $oldUsername, string $newUsername): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $body = $this->performRequest(
            $panel,
            'PUT',
            '/api/user/' . rawurlencode($oldUsername),
            ['username' => $newUsername]
        );

        return is_array($body);
    }

    public function getClientStatus($panelOrId, string $username): ?array
    {
        $user = $this->getUser($panelOrId, $username);
        if (! $user) {
            return null;
        }

        $usedBytes = (int) ($user['used_traffic'] ?? 0);
        $limitBytes = (int) ($user['data_limit'] ?? 0);
        $currentUsageGb = round($usedBytes / 1024 / 1024 / 1024, 2);
        $usageLimitGb = $limitBytes > 0 ? round($limitBytes / 1024 / 1024 / 1024, 2) : 0;

        $expireTs = $this->normalizeExpireTimestamp($user['expire'] ?? 0);
        $startDate = null;
        $packageDays = 0;
        if ($expireTs > 0) {
            $startDate = Carbon::now('UTC')->toDateString();
            $packageDays = $this->remainingDaysUntil($expireTs);
        }

        $status = $user['status'] ?? 'unknown';
        $enable = $status === 'active';

        return array_merge($user, [
            'enable' => $enable,
            'is_active' => $enable,
            'current_usage_GB' => $currentUsageGb,
            'usage_limit_GB' => $usageLimitGb,
            'start_date' => $startDate,
            'package_days' => $packageDays,
            'expire_timestamp' => $expireTs > 0 ? $expireTs : null,
            'marzban' => true,
        ]);
    }

    public function getSubscriptionLink($panelOrId, string $username): ?string
    {
        $user = $this->getUser($panelOrId, $username);
        if (! $user || empty($user['subscription_url'])) {
            return null;
        }

        $mainUrl = $this->baseUrl($this->resolvePanel($panelOrId));
        $subPath = $user['subscription_url'];
        if (! str_starts_with($subPath, '/')) {
            $subPath = '/' . $subPath;
        }

        return $mainUrl . $subPath;
    }

    public function getAllUsers($panelOrId): array
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return [];
        }

        $allUsers = [];
        $offset = 0;
        $limit = 100;

        do {
            $body = $this->performRequest(
                $panel,
                'GET',
                '/api/users?offset=' . $offset . '&limit=' . $limit
            );
            if (! is_array($body)) {
                break;
            }

            $users = $body['users'] ?? [];
            foreach ($users as $user) {
                $username = $user['username'] ?? '';
                if ($username === '') {
                    continue;
                }

                $status = $this->formatUserForCron($user);
                $status['uuid'] = $username;
                $status['name'] = $username;
                $allUsers[] = $status;
            }

            $total = (int) ($body['total'] ?? count($users));
            $offset += $limit;
        } while ($offset < $total && count($users) === $limit);

        return $allUsers;
    }

    private function formatUserForCron(array $user): array
    {
        $usedBytes = (int) ($user['used_traffic'] ?? 0);
        $limitBytes = (int) ($user['data_limit'] ?? 0);
        $expireTs = $this->normalizeExpireTimestamp($user['expire'] ?? 0);

        $packageDays = $this->remainingDaysUntil($expireTs);
        $startDate = Carbon::now('UTC')->toDateString();

        return [
            'current_usage_GB' => round($usedBytes / 1024 / 1024 / 1024, 2),
            'usage_limit_GB' => $limitBytes > 0
                ? round($limitBytes / 1024 / 1024 / 1024, 2)
                : 0,
            'start_date' => $startDate,
            // Must be an int: Carbon float diffs break Flutter int.tryParse → shows 0 days.
            'package_days' => $packageDays,
            'expire_timestamp' => $expireTs > 0 ? $expireTs : null,
            'is_active' => ($user['status'] ?? '') === 'active',
        ];
    }

    /**
     * Whole remaining days until expire timestamp (0 if expired/missing).
     */
    private function remainingDaysUntil(int $expireTs): int
    {
        if ($expireTs <= 0) {
            return 0;
        }

        $secondsLeft = $expireTs - Carbon::now('UTC')->getTimestamp();
        if ($secondsLeft <= 0) {
            return 0;
        }

        return (int) ceil($secondsLeft / 86400);
    }

    public static function resolve($panelOrId = null): self
    {
        $panel = null;
        if ($panelOrId instanceof Pannel) {
            $panel = $panelOrId;
        } elseif (is_numeric($panelOrId)) {
            $panel = Pannel::find($panelOrId);
        }

        if ($panel && $panel->type === Pannel::TYPE_PASARGUARD) {
            return new PasarguardPannelController();
        }

        return new self();
    }

    public function isOnline($panelOrId): bool
    {
        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $result = $this->performRequest($panel, 'GET', $this->inboundsApiPath($panel));

        return is_array($result);
    }

    public function syncInbounds($pannelID)
    {
        try {
            $panel = Pannel::find((int) $pannelID);
            if (! $panel || ! $panel->isMarzbanCompatible()) {
                return response()->json(['success' => false, 'msg' => 'Panel not found'], 404);
            }

            $inbounds = $this->resolveLiveInboundsMap($panel);
            if ($inbounds === null || $inbounds === []) {
                return response()->json([
                    'success' => false,
                    'msg' => 'Could not fetch inbounds from panel',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'inbounds' => $inbounds,
            ]);
        } catch (\Throwable $e) {
            Log::error('syncMarzbanInbounds error: ' . $e->getMessage());

            return response()->json(['success' => false, 'msg' => $e->getMessage()], 500);
        }
    }
}
