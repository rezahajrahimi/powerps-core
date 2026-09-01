<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MainMenuItem extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'alias_name', 'is_active', 'position', 'button_style', 'icon_custom_emoji_id', 'solo_row'];
    public $timestamps = false;

    // is item is active or not by alias name
    public function isActiveByAliasName($aliasName)
    {
        $item = MainMenuItem::where('alias_name', $aliasName)->first();
        if ($item != null) {
            return $item->is_active;
        }
        return false;
    }
    // is item active by name
    public function isActiveByName($name)
    {
        $item = MainMenuItem::where('name', $name)->first();
        if ($item != null) {
            return $item->is_active;
        }
        return false;
    }
    // get alias name by name
    public function getAliasNameByName($name)
    {
        $item = MainMenuItem::where('name', $name)->first();
        return $item->alias_name;
    }
}
