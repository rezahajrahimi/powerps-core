<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarzbanPannelController;
use App\Models\Inbound;
use App\Models\Pannel;
use App\Models\Proxy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestMarzbanPanel extends Command
{
    protected $signature = 'marzban:test
        {--panel-id= : شناسه پنل ذخیره‌شده در دیتابیس}
        {--url= : آدرس پنل مرزبان (مثلا https://lid.dreek.net)}
        {--username= : نام کاربری ادمین}
        {--password= : رمز عبور ادمین}
        {--keep-user : کاربر تست حذف نشود}
        {--user= : نام کاربر تست (پیش‌فرض: BotLiveTest)}';

    protected $description = 'تست زنده تمام عملیات مرزبان مورد استفاده در ربات';

    private MarzbanPannelController $controller;

    private ?Pannel $panel = null;

    private string $token = '';

    private string $baseUrl = '';

    private array $results = [];

    private bool $useController = false;

    private bool $createdTempPanel = false;

    public function handle(): int
    {
        $this->controller = new MarzbanPannelController();

        if (! $this->resolvePanel()) {
            return self::FAILURE;
        }

        $testUser = $this->option('user') ?: 'BotLiveTest' . now()->format('His');
        $renamedUser = $testUser . 'Renamed';

        $this->newLine();
        $this->info('شروع تست زنده مرزبان');
        $this->line('پنل: ' . $this->baseUrl);
        $this->line('کاربر تست: ' . $testUser);
        $this->newLine();

        $this->runStep('اتصال و دریافت توکن', fn () => $this->ensureToken());
        $this->runStep('دریافت inboundها', fn () => $this->fetchInbounds());
        $this->runStep('لیست کاربران (getAllUsers)', fn () => $this->testGetAllUsers());
        $this->runStep('ساخت کاربر (createUser)', fn () => $this->testCreateUser($testUser));
        $this->runStep('دریافت کاربر (getUser)', fn () => $this->testGetUser($testUser));
        $this->runStep('وضعیت کاربر (getClientStatus)', fn () => $this->testGetClientStatus($testUser));
        $this->runStep('لینک ساب (getSubscriptionLink)', fn () => $this->testGetSubscriptionLink($testUser));
        $this->runStep('غیرفعال‌سازی (changeUserActivation)', fn () => $this->testChangeActivation($testUser, false));
        $this->runStep('فعال‌سازی (changeUserActivation)', fn () => $this->testChangeActivation($testUser, true));
        $this->runStep('تمدید (updateLimits)', fn () => $this->testUpdateLimits($testUser));
        $this->runStep('ریست ترافیک (resetTraffic)', fn () => $this->testResetTraffic($testUser));
        $this->runStep('تغییر نام (renameUser)', fn () => $this->testRenameUser($testUser, $renamedUser));

        $deleteUsername = $renamedUser;
        if (! $this->option('keep-user')) {
            $this->runStep('حذف کاربر (deleteUser)', fn () => $this->testDeleteUser($deleteUsername));
        } else {
            $this->warn('گزینه --keep-user فعال است؛ کاربر تست حذف نشد: ' . $deleteUsername);
        }

        $this->cleanupTempPanel();
        $this->printSummary();

        return collect($this->results)->contains(fn ($r) => ! $r['ok']) ? self::FAILURE : self::SUCCESS;
    }

    private function resolvePanel(): bool
    {
        $panelId = $this->option('panel-id');

        if ($panelId) {
            try {
                $this->panel = Pannel::with('proxies.inbounds')->find($panelId);
            } catch (\Throwable $e) {
                $this->error('دیتابیس در دسترس نیست: ' . $e->getMessage());

                return false;
            }

            if (! $this->panel || $this->panel->type !== 'marzban') {
                $this->error('پنل مرزبان با شناسه ' . $panelId . ' یافت نشد.');

                return false;
            }

            $this->baseUrl = $this->normalizeUrl((string) ($this->panel->url_port ?: $this->panel->admin_url));
            $this->token = $this->normalizeToken((string) ($this->panel->token ?? ''));
            $this->useController = true;

            if ($this->panel->proxies->where('is_active', true)->isEmpty()) {
                $this->warn('پنل inbound فعال در دیتابیس ندارد؛ inboundها از API مرزبان بارگذاری می‌شوند.');
                $this->bootstrapProxiesFromApi();
            }

            return true;
        }

        $url = $this->option('url') ?: env('MARZBAN_TEST_URL');
        $username = $this->option('username') ?: env('MARZBAN_TEST_USERNAME');
        $password = $this->option('password') ?: env('MARZBAN_TEST_PASSWORD');

        if (! $url || ! $username || ! $password) {
            $this->error('یکی از این حالت‌ها را مشخص کنید:');
            $this->line('  php artisan marzban:test --panel-id=1');
            $this->line('  php artisan marzban:test --url=https://panel.example.com --username=admin --password=secret');

            return false;
        }

        $this->baseUrl = $this->normalizeUrl($url);
        $credentials = [
            'type' => 'marzban',
            'url_port' => $this->baseUrl,
            'username' => $username,
            'password' => $password,
        ];

        try {
            $this->panel = new Pannel($credentials);
            $this->bootstrapTempPanelRecord();
            $this->useController = true;
        } catch (\Throwable $e) {
            $this->warn('راه‌اندازی پنل موقت ناموفق: ' . $e->getMessage());
            $this->warn('تست با HTTP مستقیم ادامه می‌یابد.');
            $this->useController = false;
            $this->panel = new Pannel($credentials);
        }

        return true;
    }

    private function bootstrapTempPanelRecord(): void
    {
        $username = $this->panel->username;
        $password = $this->panel->password;

        try {
            $this->panel = Pannel::create([
                'type' => 'marzban',
                'url_port' => $this->baseUrl,
                'username' => $username,
                'password' => $password,
                'token' => null,
                'capacity' => 1000,
                'location' => 'marzban-test',
            ]);
            $this->createdTempPanel = true;

            $this->ensureToken();
            $this->panel->token = $this->token;
            $this->panel->save();

            $this->bootstrapProxiesFromApi();
            $this->panel->load('proxies.inbounds');
        } catch (\Throwable $e) {
            $this->cleanupTempPanel();
            throw $e;
        }
    }

    private function bootstrapProxiesFromApi(): void
    {
        if (! $this->panel?->id) {
            return;
        }

        $inbounds = $this->fetchInboundsMap();
        if ($inbounds === []) {
            throw new \RuntimeException('هیچ inbound فعالی در پنل مرزبان یافت نشد.');
        }

        foreach ($this->panel->proxies as $proxy) {
            $proxy->inbounds()->delete();
            $proxy->delete();
        }

        foreach ($inbounds as $protocol => $tags) {
            $proxy = Proxy::create([
                'pannel_id' => $this->panel->id,
                'type' => $protocol,
                'is_active' => true,
            ]);

            foreach ($tags as $tag) {
                $inbound = new Inbound();
                $inbound->proxy_id = $proxy->id;
                $inbound->name = $tag;
                $inbound->data = $tag;
                $inbound->is_active = true;
                $inbound->save();
            }
        }

        $this->panel->load('proxies.inbounds');
    }

    private function ensureToken(): void
    {
        if ($this->token !== '' && $this->token !== 'Bearer') {
            return;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post($this->baseUrl . '/api/admin/token', [
                'username' => $this->panel->username,
                'password' => $this->panel->password,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('دریافت توکن ناموفق (HTTP ' . $response->status() . '): ' . json_encode($response->json()));
        }

        $data = $response->json();
        $this->token = $this->normalizeToken(($data['token_type'] ?? 'Bearer') . ' ' . ($data['access_token'] ?? ''));

        if ($this->panel->exists) {
            $this->panel->token = $this->token;
            $this->panel->save();
        }
    }

    private function fetchInboundsMap(): array
    {
        $this->ensureToken();

        $response = $this->sendHttpRequest('GET', '/api/inbounds');

        if (! $response->successful()) {
            throw new \RuntimeException('دریافت inbound ناموفق (HTTP ' . $response->status() . ')');
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $protocol => $tags) {
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

    private function fetchInbounds(): bool
    {
        $map = $this->fetchInboundsMap();
        if ($map === []) {
            throw new \RuntimeException('inbound فعالی یافت نشد.');
        }

        $count = collect($map)->flatten()->count();
        $this->line('  → ' . $count . ' inbound در ' . count($map) . ' پروتکل');

        return true;
    }

    private function testGetAllUsers(): bool
    {
        if ($this->useController && $this->panel?->id) {
            $users = $this->controller->getAllUsers($this->panel);
            $this->line('  → ' . count($users) . ' کاربر');

            return is_array($users);
        }

        $response = $this->apiGet('/api/users?offset=0&limit=5');
        $total = (int) ($response['total'] ?? 0);
        $this->line('  → ' . $total . ' کاربر (نمونه ۵ تایی)');

        return isset($response['users']);
    }

    private function testCreateUser(string $username): bool
    {
        if ($this->useController && $this->panel?->id) {
            $result = $this->controller->createUser($this->panel, $username, 3, 1);
            if ($result === false) {
                throw new \RuntimeException('createUser ناموفق بود.');
            }
            $this->line('  → ساب: ' . ($result['subscription_link'] ?? '-'));

            return true;
        }

        $inbounds = $this->fetchInboundsMap();
        $proxies = $this->buildProxiesPayload(array_keys($inbounds));

        $body = $this->apiPost('/api/user', [
            'username' => $username,
            'expire' => now()->addDays(3)->timestamp,
            'data_limit' => 1073741824,
            'proxies' => $proxies,
            'inbounds' => $inbounds,
            'status' => 'active',
        ]);

        $this->line('  → ساب: ' . $this->baseUrl . ($body['subscription_url'] ?? ''));

        return ! empty($body['subscription_url']);
    }

    private function testGetUser(string $username): bool
    {
        $user = $this->useController && $this->panel?->id
            ? $this->controller->getUser($this->panel, $username)
            : $this->apiGet('/api/user/' . rawurlencode($username));

        if (! is_array($user) || ($user['username'] ?? '') !== $username) {
            throw new \RuntimeException('getUser ناموفق بود.');
        }

        return true;
    }

    private function testGetClientStatus(string $username): bool
    {
        if (! $this->useController || ! $this->panel?->id) {
            $user = $this->apiGet('/api/user/' . rawurlencode($username));
            $this->line('  → وضعیت: ' . ($user['status'] ?? 'unknown'));

            return isset($user['status']);
        }

        $status = $this->controller->getClientStatus($this->panel, $username);
        if ($status === null) {
            throw new \RuntimeException('getClientStatus ناموفق بود.');
        }

        $this->line('  → حجم: ' . $status['current_usage_GB'] . ' / ' . $status['usage_limit_GB'] . ' GB');
        $this->line('  → روز باقی‌مانده: ' . $status['package_days']);

        return true;
    }

    private function testGetSubscriptionLink(string $username): bool
    {
        if ($this->useController && $this->panel?->id) {
            $link = $this->controller->getSubscriptionLink($this->panel, $username);
        } else {
            $user = $this->apiGet('/api/user/' . rawurlencode($username));
            $path = $user['subscription_url'] ?? '';
            $link = $path ? $this->baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path) : null;
        }

        if (! $link) {
            throw new \RuntimeException('لینک ساب یافت نشد.');
        }

        $this->line('  → ' . $link);

        return true;
    }

    private function testChangeActivation(string $username, bool $enable): bool
    {
        if ($this->useController && $this->panel?->id) {
            if (! $this->controller->changeUserActivation($this->panel, $username, $enable)) {
                throw new \RuntimeException('changeUserActivation ناموفق بود.');
            }

            return true;
        }

        $this->apiPut('/api/user/' . rawurlencode($username), [
            'status' => $enable ? 'active' : 'disabled',
        ]);

        return true;
    }

    private function testUpdateLimits(string $username): bool
    {
        if ($this->useController && $this->panel?->id) {
            if (! $this->controller->updateLimits($this->panel, $username, 5, 2)) {
                throw new \RuntimeException('updateLimits ناموفق بود.');
            }

            return true;
        }

        $inbounds = $this->fetchInboundsMap();
        $proxies = $this->buildProxiesPayload(array_keys($inbounds));

        $this->apiPut('/api/user/' . rawurlencode($username), [
            'expire' => now()->addDays(5)->timestamp,
            'data_limit' => 2147483648,
            'proxies' => $proxies,
            'inbounds' => $inbounds,
            'status' => 'active',
        ]);
        $this->apiPost('/api/user/' . rawurlencode($username) . '/reset', []);

        return true;
    }

    private function testResetTraffic(string $username): bool
    {
        if ($this->useController && $this->panel?->id) {
            if (! $this->controller->resetTraffic($this->panel, $username)) {
                throw new \RuntimeException('resetTraffic ناموفق بود.');
            }

            return true;
        }

        $this->apiPost('/api/user/' . rawurlencode($username) . '/reset', []);

        return true;
    }

    private function testRenameUser(string $old, string $new): bool
    {
        if ($this->useController && $this->panel?->id) {
            if (! $this->controller->renameUser($this->panel, $old, $new)) {
                throw new \RuntimeException('renameUser ناموفق بود.');
            }

            return true;
        }

        $this->apiPut('/api/user/' . rawurlencode($old), ['username' => $new]);

        return true;
    }

    private function testDeleteUser(string $username): bool
    {
        if ($this->useController && $this->panel?->id) {
            if (! $this->controller->deleteUser($this->panel, $username)) {
                throw new \RuntimeException('deleteUser ناموفق بود.');
            }

            return true;
        }

        $response = $this->sendHttpRequest('DELETE', '/api/user/' . rawurlencode($username));

        if (! $response->successful()) {
            throw new \RuntimeException('deleteUser ناموفق (HTTP ' . $response->status() . ')');
        }

        return true;
    }

    private function apiGet(string $path): array
    {
        $response = $this->sendHttpRequest('GET', $path);

        if (! $response->successful()) {
            throw new \RuntimeException($path . ' ناموفق (HTTP ' . $response->status() . '): ' . json_encode($response->json()));
        }

        return $response->json() ?? [];
    }

    private function apiPost(string $path, array $body = []): array
    {
        $response = $this->sendHttpRequest('POST', $path, $body);

        if (! $response->successful()) {
            throw new \RuntimeException($path . ' ناموفق (HTTP ' . $response->status() . '): ' . json_encode($response->json()));
        }

        return $response->json() ?? [];
    }

    private function apiPut(string $path, array $body): array
    {
        $response = $this->sendHttpRequest('PUT', $path, $body);

        if (! $response->successful()) {
            throw new \RuntimeException($path . ' ناموفق (HTTP ' . $response->status() . '): ' . json_encode($response->json()));
        }

        return $response->json() ?? [];
    }

    private function sendHttpRequest(string $method, string $path, array $body = [])
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $request = Http::withHeaders($this->authHeaders())
                    ->acceptJson()
                    ->timeout(20);

                $url = $this->baseUrl . $path;
                $response = match (strtoupper($method)) {
                    'GET' => $request->get($url),
                    'POST' => $request->post($url, $body),
                    'PUT' => $request->put($url, $body),
                    'DELETE' => $request->delete($url),
                    default => throw new \InvalidArgumentException('Unsupported method: ' . $method),
                };

                if ($response->status() === 401 && $attempt === 1) {
                    $this->token = '';
                    $this->ensureToken();
                    if ($this->panel?->exists) {
                        $this->panel->token = $this->token;
                        $this->panel->save();
                    }
                    continue;
                }

                return $response;
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($attempt < 5) {
                    usleep(800000);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('درخواست HTTP ناموفق بود.');
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

    private function buildProxiesPayload(array $protocols): array
    {
        $proxies = [];
        foreach ($protocols as $protocol) {
            $proxies[$protocol] = new \stdClass();
        }

        return $proxies;
    }

    private function authHeaders(): array
    {
        $this->ensureToken();

        return [
            'Accept' => 'application/json',
            'Authorization' => $this->token,
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = str_replace('/dashboard/', '', $url);
        $url = str_replace('/dashboard', '', $url);

        return rtrim($url, '/');
    }

    private function normalizeToken(string $token): string
    {
        $token = trim($token);
        if ($token === '' || strtolower($token) === 'bearer') {
            return '';
        }
        if (! str_starts_with(strtolower($token), 'bearer ')) {
            return 'Bearer ' . $token;
        }

        return $token;
    }

    private function runStep(string $title, callable $callback): void
    {
        usleep(400000);

        try {
            $callback();
            $this->results[] = ['title' => $title, 'ok' => true, 'error' => null];
            $this->line('<fg=green>✓</> ' . $title);
        } catch (\Throwable $e) {
            $this->results[] = ['title' => $title, 'ok' => false, 'error' => $e->getMessage()];
            $this->line('<fg=red>✗</> ' . $title);
            $this->line('  <fg=red>' . $e->getMessage() . '</>');
        }
    }

    private function cleanupTempPanel(): void
    {
        if (! $this->createdTempPanel || ! $this->panel?->id) {
            return;
        }

        try {
            foreach ($this->panel->proxies as $proxy) {
                $proxy->inbounds()->delete();
                $proxy->delete();
            }
            $this->panel->delete();
            $this->line('پنل موقت تست از دیتابیس حذف شد.');
        } catch (\Throwable $e) {
            $this->warn('حذف پنل موقت ناموفق: ' . $e->getMessage());
        }
    }

    private function printSummary(): void
    {
        $passed = collect($this->results)->where('ok', true)->count();
        $failed = collect($this->results)->where('ok', false)->count();

        $this->newLine();
        $this->info("خلاصه: {$passed} موفق، {$failed} ناموفق از " . count($this->results));

        if ($failed > 0) {
            $this->newLine();
            $this->error('خطاها:');
            foreach ($this->results as $result) {
                if (! $result['ok']) {
                    $this->line('  - ' . $result['title'] . ': ' . $result['error']);
                }
            }
        }
    }
}
