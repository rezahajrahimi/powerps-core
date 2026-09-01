<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvanceSettingLookup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'value', 'description'];

    public function scopeGetByName($query, $name)
    {
        return $query->where('name', $name)->first();
    }
    public function scopeGetByNameAndValue($query, $name, $value)
    {
        return $query->where('name', $name)->where('value', $value)->first();
    }
    // get boolean value
    public function getBooleanValueAttribute()
    {
        return $this->value === 'true';

    }
}
