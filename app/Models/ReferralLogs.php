<?php

namespace App\Models;
use Verta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralLogs extends Model
{
    use HasFactory;

    protected $table = 'referral_logs';

    protected $fillable = [
        'referral_user_id',
        'referral_to_id',
        'transaction_id',
        'amount',
    ];
    public function getReferralLogsText()
    {
        $invitee = $this->referral_to;
        $inviteeName = $invitee != null
            ? ($invitee->name ?? $invitee->username ?? 'نامشخص')
            : 'نامشخص';

        return $inviteeName . ' - ' . $this->amount . ' - ' . $this->getRawOriginal('created_at');
    }
    public function getCreatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }
    public function referral_user()
    {
        return $this->belongsTo(User::class, 'referral_user_id', 'id');
    }

    public function referral_to()
    {
        return $this->belongsTo(User::class, 'referral_to_id', 'id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id', 'id');
    }

}
