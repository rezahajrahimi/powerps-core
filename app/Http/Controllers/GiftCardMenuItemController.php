<?php
namespace App\Http\Controllers;

use App\Models\GiftCardMenuItem;
use Illuminate\Http\Request;

class GiftCardMenuItemController extends Controller
{
    public function seed()
    {
        if (GiftCardMenuItem::all()->isEmpty()) {
            $payment             = new GiftCardMenuItem();
            $payment->name       = 'main';
            $payment->alias_name = 'کد گیفت کارت را وارد کنید.';
            $payment->level      = 1;
            $payment->save();
            $response             = new GiftCardMenuItem();
            $response->name       = 'accepted';
            $response->alias_name = 'کد گیفت کارت با موفقیت ثبت شد.';
            $response->level      = 2;
            $response->save();
            $response             = new GiftCardMenuItem();
            $response->name       = 'expired';
            $response->alias_name = 'این کد منقضی شده است.';
            $response->level      = 3;
            $response->save();
            return true;
        }
        return false;
    }
    public function getGiftCardMainMenuTitle()
    {
        $data = GiftCardMenuItem::where('name', 'main')->first();
        if ($data != null) {
            return $data;
        } else {
            $this->seed();
            return $this->getGiftCardMainMenuTitle();
        }
    }
    public function getGiftCardAcceptedMenuTitle()
    {
        $data = GiftCardMenuItem::where('name', 'accepted')->first();
        return $data->alias_name;

    }
    public function getGiftCardExpiredMenuTitle()
    {
        $data = GiftCardMenuItem::where('name', 'expired')->first();
        return $data->alias_name;

    }
    public function getAllGiftCardMenues()
    {
        $data = GiftCardMenuItem::first();
        // \Log::info($data);

        if ($data != null) {
            $data = GiftCardMenuItem::all();

            return $data;
        } else {
            $this->getGiftCardMainMenuTitle();
            $data = GiftCardMenuItem::all();

            return $data;
        }
    }
    public function updateGiftCardMenuAlisNameByLevel(Request $request)
    {
        $data = GiftCardMenuItem::where('level', $request->level)->first();
        if ($data != null) {
            // \Log::info($request->alias_name);

            $data->alias_name = $request->alias_name;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
}
