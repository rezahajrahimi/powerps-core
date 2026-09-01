<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'account_id', 'role', 'password', 'user_group_id', 'is_verified'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'is_verified' => 'boolean',
    ];
    public function get_role_by_account_id($account_id){
        $user = $this->where('account_id', $account_id)->first();
        if ($user) {
            return $user->role;
        }
        return null;
    }

    /**
     * Get the user associated with the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function agent_products()
    {
        return $this->hasMany(AgentProduct::class, 'user_id', 'id');
    }
    public function agent_permisson()
    {
        return $this->hasOne(AgentPermisson::class, 'user_id', 'id');
    }
    public function reserved_products()
    {
        return $this->hasMany(ReserverdConfig::class, 'user_id', 'id');
    }
    public function referral_wallet()
    {
        return $this->hasOne(ReferralWallet::class, 'referral_user_id', 'id');
    }

    public function loyalty_wallet()
    {
        return $this->hasOne(LoyaltyWallet::class, 'user_id', 'id');
    }
    public function shetab_verifies()
    {
        return $this->hasMany(ShetabVerify::class, 'user_id', 'id');
    }

    public static function hasUserGroupColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn((new static)->getTable(), 'user_group_id');
        }

        return $hasColumn;
    }

    public static function resolveUserGroupIdForAccount($accountId): ?int
    {
        if (! static::hasUserGroupColumn()) {
            return null;
        }

        $groupId = static::where('account_id', $accountId)->value('user_group_id');

        return $groupId !== null ? (int) $groupId : null;
    }

    public function userGroup()
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function botUser()
    {
        return $this->hasOne(BotUser::class, 'account_id', 'account_id');
    }
}
