<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['code', 'start_date', 'end_date', 'discount', 'count_of_use', 'count_of_use_per_user'];
    public function usedGiftCard()
    {
        return $this->hasMany(UsedGiftCard::class, 'gift_cards_id', 'id');
    }
    // public function getStartDateAttribute($value)
    // {
    //     return verta($value)->format('Y-m-d h:i:s');
    // }
    // public function getEndDateAttribute($value)
    // {
    //     return verta($value)->format('Y-m-d h:i:s');
    // }
}
