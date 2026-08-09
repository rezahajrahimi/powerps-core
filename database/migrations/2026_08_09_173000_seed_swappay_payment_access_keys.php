<?php

use App\Models\GlobalVerificationPaymentMethod;
use App\Models\UserGroup;
use App\Models\UserGroupPaymentMethod;
use App\Models\UserGroupVerificationPaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_group_payment_methods')) {
            return;
        }

        $groups = UserGroup::query()->get(['id']);
        foreach ($groups as $group) {
            UserGroupPaymentMethod::firstOrCreate(
                [
                    'user_group_id' => $group->id,
                    'payment_key' => 'swappay',
                ],
                ['is_enabled' => true]
            );

            if (Schema::hasTable('user_group_verification_payment_methods')) {
                foreach ([false, true] as $isVerified) {
                    UserGroupVerificationPaymentMethod::firstOrCreate(
                        [
                            'user_group_id' => $group->id,
                            'is_verified' => $isVerified,
                            'payment_key' => 'swappay',
                        ],
                        ['is_enabled' => true]
                    );
                }
            }
        }

        if (Schema::hasTable('global_verification_payment_methods')) {
            foreach ([false, true] as $isVerified) {
                GlobalVerificationPaymentMethod::firstOrCreate(
                    [
                        'is_verified' => $isVerified,
                        'payment_key' => 'swappay',
                    ],
                    ['is_enabled' => true]
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_group_payment_methods')) {
            UserGroupPaymentMethod::where('payment_key', 'swappay')->delete();
        }
        if (Schema::hasTable('user_group_verification_payment_methods')) {
            UserGroupVerificationPaymentMethod::where('payment_key', 'swappay')->delete();
        }
        if (Schema::hasTable('global_verification_payment_methods')) {
            GlobalVerificationPaymentMethod::where('payment_key', 'swappay')->delete();
        }
    }
};
