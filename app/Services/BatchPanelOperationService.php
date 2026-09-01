<?php

namespace App\Services;

use App\Http\Controllers\HiddifyPannelController;
use App\Http\Controllers\MarzbanPannelController;
use App\Http\Controllers\SanaeiPannelController;
use App\Models\Pannel;
use App\Models\Product;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class BatchPanelOperationService
{
    public function supportsPanel(?Pannel $panel): bool
    {
        if ($panel === null || $panel->isInventoryPanel()) {
            return false;
        }

        return $panel->type === 'hiddify'
            || $panel->type === 'sanaei'
            || $panel->isMarzbanCompatible();
    }

    public function execute(string $action, array $config, Pannel $panel, array $extra): bool
    {
        $config = $this->normalizeConfig($config);

        if ($panel->type === 'sanaei') {
            return $this->executeSanaei($action, $config, $panel, $extra);
        }

        if ($panel->isMarzbanCompatible()) {
            return $this->executeMarzban($action, $config, $panel, $extra);
        }

        return $this->executeHiddify($action, $config, $panel->id, $extra);
    }

    private function normalizeConfig(array $config): array
    {
        return [
            'uuid' => (string) ($config['uuid'] ?? ''),
            'name' => (string) ($config['name'] ?? $config['uuid'] ?? ''),
            'packageDays' => (int) ($config['packageDays'] ?? $config['package_days'] ?? 0),
            'usageLimitGB' => (float) ($config['usageLimitGB'] ?? $config['usage_limit_GB'] ?? 0),
            'currentUsageGB' => (float) ($config['currentUsageGB'] ?? $config['current_usage_GB'] ?? 0),
        ];
    }

    private function executeHiddify(string $action, array $config, int $panelId, array $extra): bool
    {
        $hiddify = app(HiddifyPannelController::class);
        $uuid = $config['uuid'];
        $name = $config['name'];

        switch ($action) {
            case 'inc_days':
                $days = (int) ($extra['days'] ?? 0);
                $params = [
                    'uuid' => $uuid,
                    'name' => $name,
                    'package_days' => $config['packageDays'] + $days,
                    'mode' => 'no_reset',
                    'comment' => 'افزایش روزها در ' . Verta::now() . " به مدت {$days} روز",
                ];

                return $hiddify->sendPatchRequestToHiddifyPannel($panelId, "/api/v2/admin/user/{$uuid}/", $params) !== false;

            case 'dec_days':
                $days = (int) ($extra['days'] ?? 0);
                $params = [
                    'uuid' => $uuid,
                    'name' => $name,
                    'package_days' => $config['packageDays'] - $days,
                    'mode' => 'no_reset',
                    'comment' => 'کاهش روزها در ' . Verta::now() . " به مدت {$days} روز",
                ];

                return $hiddify->sendPatchRequestToHiddifyPannel($panelId, "/api/v2/admin/user/{$uuid}/", $params) !== false;

            case 'modify_days':
                $days = (int) ($extra['days'] ?? 0);
                $params = [
                    'uuid' => $uuid,
                    'name' => $name,
                    'package_days' => $days,
                    'mode' => 'no_reset',
                    'comment' => 'تغییر روزها در ' . Verta::now() . " به میزان {$days} روز",
                ];

                return $hiddify->sendPatchRequestToHiddifyPannel($panelId, "/api/v2/admin/user/{$uuid}/", $params) !== false;

            case 'inc_vol':
                $vol = (float) ($extra['vol'] ?? 0);
                $params = [
                    'uuid' => $uuid,
                    'name' => $name,
                    'usage_limit_GB' => $config['usageLimitGB'] + $vol,
                    'mode' => 'no_reset',
                    'comment' => 'افزایش حجم در ' . Verta::now() . " به میزان {$vol} GB",
                ];

                return $hiddify->sendPatchRequestToHiddifyPannel($panelId, "/api/v2/admin/user/{$uuid}/", $params) !== false;

            case 'dec_vol':
                $vol = (float) ($extra['vol'] ?? 0);
                $params = [
                    'uuid' => $uuid,
                    'name' => $name,
                    'usage_limit_GB' => max(0, $config['usageLimitGB'] - $vol),
                    'mode' => 'no_reset',
                    'comment' => 'کاهش حجم در ' . Verta::now() . " به میزان {$vol} GB",
                ];

                return $hiddify->sendPatchRequestToHiddifyPannel($panelId, "/api/v2/admin/user/{$uuid}/", $params) !== false;

            case 'modify_vol':
                $vol = (float) ($extra['vol'] ?? 0);
                $params = [
                    'uuid' => $uuid,
                    'name' => $name,
                    'usage_limit_GB' => $vol,
                    'mode' => 'no_reset',
                    'comment' => 'تغییر حجم در ' . Verta::now() . " به میزان {$vol} GB",
                ];

                return $hiddify->sendPatchRequestToHiddifyPannel($panelId, "/api/v2/admin/user/{$uuid}/", $params) !== false;

            case 'reset':
                $today = date('Y-m-d');
                $params = [
                    'uuid' => $uuid,
                    'name' => $name,
                    'current_usage_GB' => 0,
                    'usage_limit_GB' => $config['usageLimitGB'],
                    'package_days' => $config['packageDays'],
                    'mode' => 'no_reset',
                    'start_date' => $today,
                    'comment' => 'ریست در ' . Verta::now(),
                ];

                return $hiddify->sendPatchRequestToHiddifyPannel($panelId, "/api/v2/admin/user/{$uuid}/", $params) !== false;

            case 'active':
            case 'deactive':
                $req = new Request();
                $req->pannelID = $panelId;
                $req->uuid = $uuid;
                $req->enable = $action === 'active';
                $req->comment = ($action === 'active' ? 'فعالسازی' : 'غیرفعال سازی') . ' در ' . Verta::now();
                $result = $hiddify->changeUserActivationOfHiddifyPanelApi($req);

                return $result !== false;

            case 'delete':
                $result = $hiddify->deleteUserOfHiddifyPanel($panelId, $uuid);
                if ($result === false) {
                    return false;
                }
                $product = Product::where('subscription_link', "/{$uuid}/all.txt?name=sublink-unknown&asn=unknown&mode=new")->first();
                if ($product !== null) {
                    $product->delete();
                }

                return true;

            default:
                return false;
        }
    }

    private function executeSanaei(string $action, array $config, Pannel $panel, array $extra): bool
    {
        $sn = new SanaeiPannelController();
        $uuid = $config['uuid'];
        if ($uuid === '') {
            return false;
        }

        switch ($action) {
            case 'inc_days':
                return (bool) $sn->rechargeClient($panel, $uuid, (int) ($extra['days'] ?? 0), 0);

            case 'dec_days':
                return $this->adjustSanaeiDays($sn, $panel, $uuid, -1 * (int) ($extra['days'] ?? 0));

            case 'modify_days':
                return $this->setSanaeiDays($sn, $panel, $uuid, (int) ($extra['days'] ?? 0));

            case 'inc_vol':
                return (bool) $sn->rechargeClient($panel, $uuid, 0, (int) ($extra['vol'] ?? 0));

            case 'dec_vol':
                return $this->adjustSanaeiVolume($sn, $panel, $uuid, -1 * (float) ($extra['vol'] ?? 0));

            case 'modify_vol':
                return $this->setSanaeiVolume($sn, $panel, $uuid, (float) ($extra['vol'] ?? 0));

            case 'reset':
                $found = $sn->findClientByUUID($panel, $uuid);
                if (! $found) {
                    return false;
                }
                $inboundId = $found['inbound']['id'] ?? 1;
                $email = $found['client']['email'] ?? '';

                return (bool) $sn->resetClientTraffic($panel, $inboundId, $email);

            case 'active':
                return (bool) $sn->changeUserActivation($panel, $uuid, true);

            case 'deactive':
                return (bool) $sn->changeUserActivation($panel, $uuid, false);

            case 'delete':
                return (bool) $sn->deleteUser($panel, $uuid);

            default:
                return false;
        }
    }

    private function executeMarzban(string $action, array $config, Pannel $panel, array $extra): bool
    {
        $mb = MarzbanPannelController::resolve($panel);
        $username = $config['name'] !== '' ? $config['name'] : $config['uuid'];
        if ($username === '') {
            return false;
        }

        $user = $mb->getUser($panel, $username);
        if (! is_array($user) && ! in_array($action, ['delete'], true)) {
            return false;
        }

        switch ($action) {
            case 'inc_days':
                $days = (int) ($extra['days'] ?? 0);

                return $mb->modifyUser(
                    $panel,
                    $username,
                    $this->marzbanRemainingDays($user) + $days,
                    $this->marzbanVolGb($user),
                    false
                );

            case 'dec_days':
                $days = (int) ($extra['days'] ?? 0);

                return $mb->modifyUser(
                    $panel,
                    $username,
                    max(0, $this->marzbanRemainingDays($user) - $days),
                    $this->marzbanVolGb($user),
                    false
                );

            case 'modify_days':
                return $mb->modifyUser(
                    $panel,
                    $username,
                    (int) ($extra['days'] ?? 0),
                    $this->marzbanVolGb($user),
                    false
                );

            case 'inc_vol':
                $vol = (float) ($extra['vol'] ?? 0);

                return $mb->modifyUser(
                    $panel,
                    $username,
                    $this->marzbanRemainingDays($user),
                    $this->marzbanVolGb($user) + $vol,
                    false
                );

            case 'dec_vol':
                $vol = (float) ($extra['vol'] ?? 0);

                return $mb->modifyUser(
                    $panel,
                    $username,
                    $this->marzbanRemainingDays($user),
                    max(0, $this->marzbanVolGb($user) - $vol),
                    false
                );

            case 'modify_vol':
                return $mb->modifyUser(
                    $panel,
                    $username,
                    $this->marzbanRemainingDays($user),
                    (float) ($extra['vol'] ?? 0),
                    false
                );

            case 'reset':
                return $mb->resetTraffic($panel, $username);

            case 'active':
                return $mb->changeUserActivation($panel, $username, true);

            case 'deactive':
                return $mb->changeUserActivation($panel, $username, false);

            case 'delete':
                return $mb->deleteUser($panel, $username);

            default:
                return false;
        }
    }

    private function adjustSanaeiDays(SanaeiPannelController $sn, Pannel $panel, string $uuid, int $deltaDays): bool
    {
        $found = $sn->findClientByUUID($panel, $uuid);
        if (! $found) {
            return false;
        }

        $client = $found['client'];
        $nowMs = now('UTC')->timestamp * 1000;
        $currentExpiry = (int) ($client['expiryTime'] ?? 0);
        if ($currentExpiry <= 0) {
            $currentExpiry = $nowMs;
        }
        $newExpiry = $currentExpiry + ($deltaDays * 86400 * 1000);

        return (bool) $sn->updateClient($panel, $uuid, ['expiryTime' => max($nowMs, $newExpiry)]);
    }

    private function setSanaeiDays(SanaeiPannelController $sn, Pannel $panel, string $uuid, int $days): bool
    {
        $newExpiry = now('UTC')->addDays($days)->timestamp * 1000;

        return (bool) $sn->updateClient($panel, $uuid, ['expiryTime' => $newExpiry]);
    }

    private function adjustSanaeiVolume(SanaeiPannelController $sn, Pannel $panel, string $uuid, float $deltaGb): bool
    {
        $found = $sn->findClientByUUID($panel, $uuid);
        if (! $found) {
            return false;
        }

        $currentTotal = (int) ($found['client']['totalGB'] ?? 0);
        $deltaBytes = (int) round($deltaGb * 1024 * 1024 * 1024);
        $newTotal = max(0, $currentTotal + $deltaBytes);

        return (bool) $sn->updateClient($panel, $uuid, ['totalGB' => $newTotal]);
    }

    private function setSanaeiVolume(SanaeiPannelController $sn, Pannel $panel, string $uuid, float $gb): bool
    {
        $newTotal = (int) round($gb * 1024 * 1024 * 1024);

        return (bool) $sn->updateClient($panel, $uuid, ['totalGB' => $newTotal]);
    }

    private function marzbanRemainingDays(array $user): int
    {
        $expireTs = (int) ($user['expire'] ?? 0);
        if ($expireTs <= 0) {
            return 0;
        }

        return max(0, (int) Carbon::now('UTC')->diffInDays(Carbon::createFromTimestamp($expireTs, 'UTC'), false));
    }

    private function marzbanVolGb(array $user): float
    {
        $limitBytes = (int) ($user['data_limit'] ?? 0);
        if ($limitBytes <= 0) {
            return 0;
        }

        return round($limitBytes / 1024 / 1024 / 1024, 2);
    }
}
