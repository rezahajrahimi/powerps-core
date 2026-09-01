<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'visit_card_text',
        'referral_percent',
        'is_active',
    ];

    protected $casts = [
        'referral_percent' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * Trim decimal zeros without turning 20 into 2 or 100 into 1.
     */
    public static function formatPercentValue(mixed $percent): string
    {
        $formatted = number_format((float) $percent, 2, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    public static function commissionFromAmount(float $amountToman, mixed $percent): float
    {
        $percent = (float) $percent;
        if ($amountToman <= 0 || $percent <= 0) {
            return 0.0;
        }

        return round(($amountToman / 100) * $percent, 2);
    }

    public function formattedPercent(): string
    {
        return self::formatPercentValue($this->referral_percent);
    }
}
