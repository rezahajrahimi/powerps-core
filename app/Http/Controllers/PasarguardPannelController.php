<?php

namespace App\Http\Controllers;

use App\Models\Pannel;
use Illuminate\Support\Facades\Log;

class PasarguardPannelController extends MarzbanPannelController
{
    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function fetchGroups(Pannel $panel): ?array
    {
        $allGroups = [];
        $offset = 0;
        $limit = 100;

        do {
            $body = $this->performRequest(
                $panel,
                'GET',
                '/api/groups?offset=' . $offset . '&limit=' . $limit
            );
            if (! is_array($body)) {
                return $allGroups === [] ? null : $allGroups;
            }

            $groups = $body['groups'] ?? [];
            if (! is_array($groups)) {
                break;
            }

            $allGroups = array_merge($allGroups, $groups);
            $total = (int) ($body['total'] ?? count($allGroups));
            $offset += $limit;
        } while ($offset < $total && count($groups) === $limit);

        return $allGroups === [] ? null : $allGroups;
    }

    /**
     * @param  int[]|null  $selectedGroupIds
     * @return int[]
     */
    protected function validateGroupIds(Pannel $panel, ?array $selectedGroupIds): array
    {
        if ($selectedGroupIds === null || $selectedGroupIds === []) {
            return [];
        }

        $groups = $this->fetchGroups($panel);
        if ($groups === null || $groups === []) {
            return [];
        }

        $enabledIds = [];
        foreach ($groups as $group) {
            if ($group['is_disabled'] ?? false) {
                continue;
            }
            $enabledIds[] = (int) $group['id'];
        }

        $valid = array_values(array_unique(array_intersect(
            array_map('intval', $selectedGroupIds),
            $enabledIds
        )));
        sort($valid);

        return $valid;
    }

    /**
     * @param  array<string, array<int, string>>|null  $selectedInbounds
     * @return int[]
     */
    protected function resolveGroupIdsFromInbounds(Pannel $panel, ?array $selectedInbounds = null): array
    {
        $groups = $this->fetchGroups($panel);
        if ($groups === null || $groups === []) {
            return [];
        }

        $enabledGroups = array_values(array_filter(
            $groups,
            static fn (array $group): bool => ! (bool) ($group['is_disabled'] ?? false)
        ));

        if ($enabledGroups === []) {
            return [];
        }

        if ($selectedInbounds === null || $selectedInbounds === []) {
            return array_values(array_unique(array_map(
                static fn (array $group): int => (int) $group['id'],
                $enabledGroups
            )));
        }

        $selectedTags = [];
        foreach ($selectedInbounds as $tags) {
            if (! is_array($tags)) {
                continue;
            }
            foreach ($tags as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $selectedTags[] = $tag;
                }
            }
        }
        $selectedTags = array_values(array_unique($selectedTags));

        if ($selectedTags === []) {
            return array_values(array_unique(array_map(
                static fn (array $group): int => (int) $group['id'],
                $enabledGroups
            )));
        }

        foreach ($enabledGroups as $group) {
            $groupTags = array_map(
                static fn ($tag) => trim((string) $tag),
                $group['inbound_tags'] ?? []
            );
            if (array_diff($selectedTags, $groupTags) === []) {
                return [(int) $group['id']];
            }
        }

        $remaining = $selectedTags;
        $groupIds = [];
        $scored = $enabledGroups;
        usort($scored, static function (array $a, array $b) use ($remaining): int {
            $aMatch = count(array_intersect($remaining, $a['inbound_tags'] ?? []));
            $bMatch = count(array_intersect($remaining, $b['inbound_tags'] ?? []));

            return $bMatch <=> $aMatch;
        });

        foreach ($scored as $group) {
            $groupTags = $group['inbound_tags'] ?? [];
            $matches = array_values(array_intersect($remaining, $groupTags));
            if ($matches === []) {
                continue;
            }
            $groupIds[] = (int) $group['id'];
            $remaining = array_values(array_diff($remaining, $matches));
            if ($remaining === []) {
                break;
            }
        }

        if ($remaining !== []) {
            Log::error('Pasarguard resolveGroupIds: selected inbounds not found in any group', [
                'panel_id' => $panel->id,
                'missing_tags' => $remaining,
                'selected' => $selectedInbounds,
            ]);

            return [];
        }

        return array_values(array_unique($groupIds));
    }

    /**
     * @param  array<string, array<int, string>>|null  $selectedInbounds
     * @param  int[]|null  $selectedGroupIds
     */
    protected function resolvePasarguardGroupIds(
        Pannel $panel,
        ?array $selectedInbounds = null,
        ?array $selectedGroupIds = null
    ): array {
        $validated = $this->validateGroupIds($panel, $selectedGroupIds);
        if ($validated !== []) {
            return $validated;
        }

        return $this->resolveGroupIdsFromInbounds($panel, $selectedInbounds);
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
        $base = [
            'expire' => $this->expireTimestamp($day),
            'data_limit' => $this->gbToBytes($volGb),
            'data_limit_reset_strategy' => 'no_reset',
            'proxy_settings' => new \stdClass(),
            'status' => 'active',
        ];

        if (! $assignGroups) {
            return $base;
        }

        $groupIds = $this->resolvePasarguardGroupIds($panel, $selectedInbounds, $selectedGroupIds);
        if ($groupIds === []) {
            return [];
        }

        Log::info('Pasarguard using group_ids for user', [
            'panel_id' => $panel->id,
            'group_ids' => $groupIds,
            'selected_group_ids' => $selectedGroupIds,
            'selected_inbounds' => $selectedInbounds,
        ]);

        $base['group_ids'] = $groupIds;

        return $base;
    }

    public function modifyUser(
        $panelOrId,
        string $username,
        int $day,
        $volGb,
        bool $resetTraffic = true,
        ?array $selectedInbounds = null,
        ?array $selectedGroupIds = null
    ): bool {
        $assignGroups = ($selectedGroupIds !== null && $selectedGroupIds !== [])
            || ($selectedInbounds !== null && $selectedInbounds !== []);

        $panel = $this->resolvePanel($panelOrId);
        if (! $panel) {
            return false;
        }

        $params = $this->buildUserMutationParams(
            $panel,
            $day,
            $volGb,
            $selectedInbounds,
            $selectedGroupIds,
            $assignGroups
        );
        if ($params === []) {
            Log::error('Pasarguard modifyUser failed: no valid groups for panel', [
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

    public function syncGroups($pannelID)
    {
        try {
            $panel = Pannel::find((int) $pannelID);
            if (! $panel || $panel->type !== Pannel::TYPE_PASARGUARD) {
                return response()->json(['success' => false, 'msg' => 'Panel not found'], 404);
            }

            $groups = $this->fetchGroups($panel);
            if ($groups === null || $groups === []) {
                return response()->json([
                    'success' => false,
                    'msg' => 'Could not fetch groups from panel',
                ], 400);
            }

            $items = [];
            foreach ($groups as $group) {
                if ($group['is_disabled'] ?? false) {
                    continue;
                }
                $items[] = [
                    'id' => (int) ($group['id'] ?? 0),
                    'name' => (string) ($group['name'] ?? ''),
                    'inbound_tags' => array_values($group['inbound_tags'] ?? []),
                ];
            }

            return response()->json([
                'success' => true,
                'groups' => $items,
            ]);
        } catch (\Throwable $e) {
            Log::error('syncPasarguardGroups error: ' . $e->getMessage());

            return response()->json(['success' => false, 'msg' => $e->getMessage()], 500);
        }
    }
}
