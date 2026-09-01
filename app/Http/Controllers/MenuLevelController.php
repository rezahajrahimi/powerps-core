<?php

namespace App\Http\Controllers;
use App\Models\MenuLevel;

use Illuminate\Http\Request;

class MenuLevelController extends Controller
{
    public function getUserLevel($account_id)
    {
        $data = MenuLevel::where('account_id', $account_id)->first();
        if ($data != null) {
            return $data->level;
        } else {
            return 0;
        }
    }
    public function newUserLevel($account_id,$level)
    {
        $data = MenuLevel::where('account_id', $account_id)->first();
        if ($data != null) {
            $data->level = $level;
            $data->update;
            return true;
        } else {
            return false;
        }
    }
}
