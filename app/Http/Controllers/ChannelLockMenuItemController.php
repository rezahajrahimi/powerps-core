<?php

namespace App\Http\Controllers;
use App\Models\ChannelLockMenuItem;

use Illuminate\Http\Request;

class ChannelLockMenuItemController extends Controller
{
    public function seed()
    {
        if (ChannelLockMenuItem::all()->isEmpty()) {
            $data             = new ChannelLockMenuItem();
            $data->name       = 'main';
            $data->alias_name = 'برای شروع، لطفا در کانالهای زیر عضو بشوید.';
            $data->level      = 1;
            $data->save();
            return true;
        }
        return false;
    }
    public function getChannelLockMainMenuTitle()
    {
        $data = ChannelLockMenuItem::where('name', 'main')->first();
        if ($data != null) {
            return $data;
        } else {
            $this->seed();
            return $this->getChannelLockMainMenuTitle();
        }
    }
    public function getChannelLockMenuText(){
        $data = ChannelLockMenuItem::where('name', "main")->first();
        return $data->alias_name;

    }
    public function updateChannelLockMenuAlisNameByLevel(Request $request)
    {
        $data = ChannelLockMenuItem::where('level', $request->level)->first();
        if ($data != null) {
            $data->alias_name = $request->alias_name;
            $data->update();
            return true;
        } else {
            return response()->json(false, 401);        }
    }
}
