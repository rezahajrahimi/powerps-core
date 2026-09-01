<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsedTestAccount extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['test_account_id', 'account_id'];
    /**
     * Get the user that owns the UsedTestAccount
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function testAccount(): BelongsTo
    {
        return $this->belongsTo(TestAccount::class, 'test_account_id');
    }

}
