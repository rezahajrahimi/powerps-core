<?php

namespace App\Models;

use App\Services\ConfigNameService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Verta;

class BotUser extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['account_id', 'username', 'first_name', 'last_name', 'phone_number', 'admin_alias'];

    // get user by account_id
    public function getUserByAccountID($accountId)
    {
        return $this->where('account_id', $accountId)->first();
    }

    public static function resolveConfigAccountLabel(int|string $accountId, int|string|null $suffix = null): string
    {
        return ConfigNameService::resolveAccountLabel($accountId, $suffix);
    }

    public function getUserNameByAccountID($accountId)
    {
        return $this->where('account_id', $accountId)->first()->username;
    }


    public function getCreatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }
    /**
     * Get all of the comments for the BotUser
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'account_id', 'account_id')
            ->with('product_category')
            ->orderBy('id', 'desc');
    }
    public function transaction()
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id')
            ->with('payment_types', 'transaction_image', 'user')
            ->orderBy('id', 'desc');
    }
    /**
     * Get the user associated with the BotUser
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function ballance()
    {
        return $this->hasOne(AccountBallance::class, 'account_id', 'account_id');
    }
    public function logs()
    {
        return $this->hasMany(Log::class, 'account_id', 'account_id')->orderBy('id', 'desc');
    }
    // public function referral_logs()
    // {
    //     return $this->hasMany(ReferralLogs::class, 'referral_to_id', 'account_id')->orderBy('id', 'desc');
    // }
    public function user()
    {
        return $this->hasOne(User::class, 'account_id', 'account_id')->with(['referral_wallet', 'loyalty_wallet']);
    }
    public function blocked_user()
    {
        return $this->hasOne(BlockedUser::class, 'account_id', 'account_id');
    }
    

    // public function referral_wallet()
    // {
    //     return $this->hasOne(ReferralWallet::class, 'referral_user_id', 'id');
    // }
}
