<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestAccount extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['pannel_id', 'volume', 'expire_day'];
    public function usedTesrAccounts()
    {
        return $this->hasMany(UsedTestAccount::class, 'test_account_id', 'id');
    }
    /**
     * Get the user that owns the TestAccount
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pannel_id');
    }

}
