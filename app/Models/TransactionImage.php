<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionImage extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'transaction_id'];

    protected $fillable = ['account_id', 'transaction_id', 'img_src','user_text'];

    /**
     * Get the user that owns the TransactionImage
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

}
