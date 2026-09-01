<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionSetting extends Model
{
    use HasFactory;
    protected $fillable = ['dollar_transaction'];
    protected $guarded = ['id'];
    protected $visible = ['dollar_transaction'];
    public function getDollarTransactionSetting()
    {
        $data = TransactionSetting::first();
        return $data->dollar_transaction;
    }

}
