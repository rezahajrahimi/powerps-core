<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupPaymentMethod;
use App\Models\UserGroupVerificationPaymentMethod;
use App\Models\GlobalVerificationPaymentMethod;

class PaymentAccessService
{
    public function getGroupForUser(User $user): ?UserGroup
    {
        if (!in_array($user->role, ['user', 'agent'])) {
            return null;
        }

        if ($user->user_group_id) {
            $group = UserGroup::find($user->user_group_id);
            if ($group && $group->role_type === $user->role && !$group->is_default) {
                return $group;
            }
        }

        if ($user->role === 'agent') {
            return UserGroup::where('role_type', 'agent')
                ->where('is_default', true)
                ->first();
        }

        return null;
    }

    public function getGroupForAccountId(int|string $accountId): ?UserGroup
    {
        $user = User::where('account_id', $accountId)->first();
        if (!$user) {
            return null;
        }

        return $this->getGroupForUser($user);
    }

    public function isAllowed(?UserGroup $group, string $paymentKey): bool
    {
        if (!$group) {
            return true;
        }

        $method = UserGroupPaymentMethod::where('user_group_id', $group->id)
            ->where('payment_key', $paymentKey)
            ->first();

        if (!$method) {
            return true;
        }

        return (bool) $method->is_enabled;
    }

    public function isAllowedForUser(User $user, string $paymentKey): bool
    {
        $group = $this->getGroupForUser($user);

        return $this->isAllowedForUserAndGroup($user, $group, $paymentKey);
    }

    public function isAllowedForUserAndGroup(User $user, ?UserGroup $group, string $paymentKey): bool
    {
        if ($user->role === 'user') {
            if ($group) {
                $verificationMethod = UserGroupVerificationPaymentMethod::where('user_group_id', $group->id)
                    ->where('is_verified', (bool) $user->is_verified)
                    ->where('payment_key', $paymentKey)
                    ->first();

                if ($verificationMethod) {
                    return (bool) $verificationMethod->is_enabled;
                }

                return $this->isAllowed($group, $paymentKey);
            }

            return $this->isGlobalVerificationPaymentAllowed((bool) $user->is_verified, $paymentKey);
        }

        return $this->isAllowed($group, $paymentKey);
    }

    public function isGlobalVerificationPaymentAllowed(bool $isVerified, string $paymentKey): bool
    {
        $method = GlobalVerificationPaymentMethod::where('is_verified', $isVerified)
            ->where('payment_key', $paymentKey)
            ->first();

        if (!$method) {
            return true;
        }

        return (bool) $method->is_enabled;
    }

    public function isAllowedForAccountId(int|string $accountId, string $paymentKey): bool
    {
        $user = User::where('account_id', $accountId)->first();
        if (!$user) {
            return $this->isAllowed($this->getGroupForAccountId($accountId), $paymentKey);
        }

        return $this->isAllowedForUser($user, $paymentKey);
    }

    public function getEnabledPaymentKeys(?UserGroup $group): array
    {
        if (!$group) {
            return UserGroup::PAYMENT_KEYS;
        }

        $methods = UserGroupPaymentMethod::where('user_group_id', $group->id)->get()->keyBy('payment_key');
        $enabled = [];

        foreach (UserGroup::PAYMENT_KEYS as $key) {
            if (!isset($methods[$key]) || $methods[$key]->is_enabled) {
                $enabled[] = $key;
            }
        }

        return $enabled;
    }

    public function assignDefaultGroup(User $user): void
    {
        if ($user->role === 'user') {
            $user->user_group_id = null;
            $user->save();
            return;
        }

        if ($user->role === 'agent') {
            $defaultGroup = UserGroup::where('role_type', 'agent')
                ->where('is_default', true)
                ->first();

            if ($defaultGroup) {
                $user->user_group_id = $defaultGroup->id;
                $user->save();
            }
        }
    }

    public function syncDefaultGroups(): void
    {
        $paymentKeys = UserGroup::PAYMENT_KEYS;

        $agentGroup = UserGroup::firstOrCreate(
            ['role_type' => 'agent', 'is_default' => true],
            ['name' => 'نمایندگان (پیش‌فرض)']
        );

        foreach ($paymentKeys as $key) {
            UserGroupPaymentMethod::firstOrCreate(
                ['user_group_id' => $agentGroup->id, 'payment_key' => $key],
                ['is_enabled' => true]
            );
        }

        $this->syncGlobalVerificationPayments();
    }

    public function syncGlobalVerificationPayments(): void
    {
        foreach ([false, true] as $isVerified) {
            foreach (UserGroup::PAYMENT_KEYS as $key) {
                GlobalVerificationPaymentMethod::firstOrCreate(
                    ['is_verified' => $isVerified, 'payment_key' => $key],
                    ['is_enabled' => true]
                );
            }
        }
    }
}
