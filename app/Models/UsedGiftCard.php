<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\BotUser;

class UsedGiftCard extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'gift_cards_id'];
    protected $fillable = ['gift_cards_id', 'account_id'];
    /**
     * Get the user that owns the UsedGiftCard
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(BotUser::class, 'account_id', 'account_id');
    }

    /**
     * Get the gift card that was used
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function giftCard()
    {
        return $this->belongsTo(GiftCard::class, 'gift_cards_id');
    }

}
