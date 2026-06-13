<?php

use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupPaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_verification_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_verified');
            $table->string('payment_key', 50);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['is_verified', 'payment_key'], 'gvpm_verified_key_unique');
        });

        $defaultUserGroup = UserGroup::where('role_type', 'user')->where('is_default', true)->first();
        if ($defaultUserGroup) {
            User::where('user_group_id', $defaultUserGroup->id)->update(['user_group_id' => null]);
            UserGroupPaymentMethod::where('user_group_id', $defaultUserGroup->id)->delete();
            $defaultUserGroup->delete();
        }

        $paymentKeys = ['zarinpal', 'offline', 'nowpayments', 'cryptomus', 'usd_transaction'];
        foreach ([false, true] as $isVerified) {
            foreach ($paymentKeys as $key) {
                \DB::table('global_verification_payment_methods')->insert([
                    'is_verified' => $isVerified,
                    'payment_key' => $key,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('global_verification_payment_methods');
    }
};
