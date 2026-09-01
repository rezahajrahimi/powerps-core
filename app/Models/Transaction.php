<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $guarded = ['id','account_id','payment_type_id'];
    protected $fillable = ['account_id','username','payment_type_id','amount','confirmed','recipe_number','amount_dollar'];
    public function getTransactionText()
    {
        return $this->payment_types->name . " - " . $this->amount . " - " . $this->created_at . " - " . ($this->confirmed ? "تایید شده" : "تایید نشده");
    }
    public function getCreatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }
    /**
     * Get the user that owns the Transaction
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment_types()
    {
        return $this->belongsTo(PaymentType::class, 'payment_type_id');
    }
    /**
     * Get the user associated with the Transaction
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function transaction_image()
    {
        return $this->hasOne(TransactionImage::class, 'transaction_id', 'id');
    }
    // /**
    //  * Get all of the comments for the Transaction
    //  *
    //  * @return \Illuminate\Database\Eloquent\Relations\HasMany
    //  */
    // public function transaction_image()
    // {
    //     return $this->hasMany(TransactionImage::class, 'transaction_id');
    // }
    public function user(){
        return $this->belongsTo(BotUser::class, 'account_id', 'account_id');
    }



}
