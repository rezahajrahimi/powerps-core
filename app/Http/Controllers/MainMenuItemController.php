<?php

namespace App\Http\Controllers;
use App\Models\MainMenuItem;
use App\Services\LicenseFeatureService;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

class MainMenuItemController extends Controller
{
    public function seed()
    {
        // check if data was empty create new menu
        if (MainMenuItem::all()->isEmpty()) {
             $menu1 = new MainMenuItem();
            $menu1->name = 'خرید اشتراک';
            $menu1->alias_name = 'خرید اشتراک';
            $menu1->is_active = true;
            $menu1->position = 1;
            $menu1->solo_row = true;
            $menu1->save();
            $menu2 = new MainMenuItem();

            $menu2->name = 'webapp';
            $menu2->alias_name = 'استفاده در وب اپلیکیشن';
            $menu2->is_active = true;
            $menu2->position = 2;
            $menu2->save();
            $menu3 = new MainMenuItem();

            $menu3->name = 'سابقه خرید';
            $menu3->alias_name = 'سابقه خرید';
            $menu3->is_active = true;
            $menu3->position = 3;
            $menu3->save();
            $menu4 = new MainMenuItem();

            $menu4->name = 'پشتیبانی';
            $menu4->alias_name = 'پشتیبانی';
            $menu4->is_active = true;
            $menu4->position = 4;
            $menu4->save();
            $menu5 = new MainMenuItem();

            $menu5->name = 'آموزش استفاده و سوالات متداول';
            $menu5->alias_name = 'آموزش استفاده و سوالات متداول';
            $menu5->is_active = true;
            $menu5->position = 5;
            $menu5->save();
            $menu6 = new MainMenuItem();

            $menu6->name = 'اطلاعات حساب';
            $menu6->alias_name = 'اطلاعات حساب';
            $menu6->is_active = true;
            $menu6->position = 6;
            $menu6->save();
            $menu7 = new MainMenuItem();

            $menu7->name = 'اکانت آزمایشی';
            $menu7->alias_name = 'اکانت آزمایشی';
            $menu7->is_active = true;
            $menu7->position = 7;
            $menu7->save();
            $menu8 = new MainMenuItem();

            $menu8->name = 'دانلود برنامه';
            $menu8->alias_name = 'دانلود برنامه';
            $menu8->is_active = true;
            $menu8->position = 8;
            $menu8->save();
            $menu9 = new MainMenuItem();

            $menu9->name = 'گیفت کارت';
            $menu9->alias_name = 'گیفت کارت';
            $menu9->is_active = true;
            $menu9->position = 9;
            $menu9->save();
            $menu10 = new MainMenuItem();

            $menu10->name = 'کسب درآمد';
            $menu10->alias_name = 'کسب درآمد';
            $menu10->is_active = true;
            $menu10->position = 10;
            $menu10->save();
        }
        return true;
    }
    public function getAllMainMenuItems()
    {
        $data = MainMenuItem::all();
        // check if data was empty create new menu
        if ($data->isEmpty()) {
            $this->seed();
        }
        $newData = MainMenuItem::all();

        return $newData;
    }
    public function getMenuNameByID($id)
    {
        return MainMenuItem::where('id', $id)->first();
    }
    public function getMenuNameByAliasName($aliasName)
    {
        $data = MainMenuItem::where('alias_name', $aliasName)->first();
        if ($data != null && $data->name != null && $data->is_active == true) {
            return $data->name;
        }
        return 'خیر';
    }
    public function getMenuItemByAliasName($aliasName)
    {
        return MainMenuItem::where('alias_name', $aliasName)->first();
    }
    public function getMenuAliasNameByName($name)
    {
        $data = MainMenuItem::where('name', $name)->first();
        if ($data != null) {
            return $data->alias_name;
        }
        return 'خیر';
    }
    public function getMenuIdByName($name)
    {
        return MainMenuItem::where('name', $name)->first();
    }
    public function getAllActivatedMainMenuItems()
    {
        return MainMenuItem::where('is_active', true)->orderby('position', 'asc')->get();
    }
    public function deActiveMainMenuItem($name)
    {
        $data = MainMenuItem::where('name', $name)->first();
        if ($data != null) {
            $data->is_active = false;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
    public function reActiveMainMenuItem($name)
    {
        $data = MainMenuItem::where('name', $name)->first();
        if ($data != null) {
            $data->is_active = true;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
    public function changeMainMenuAliasName(Request $request)
    {
        $data = MainMenuItem::where('name', $request->oldName)->first();
        if ($data != null) {
            $data->alias_name = $request->newName;
            $data->update();
            return true;
        } else {
            return false;
        }
    }
    public function reorderMainMenuItems(Request $request) {
        try {
            DB::beginTransaction();
            
            $data = MainMenuItem::all();
            $newOrder = $request->all()['items'];
            
            // ابتدا همه position ها را به یک مقدار موقت تغییر می‌دهیم
            foreach ($data as $menuItem) {
                $menuItem->position = $menuItem->id + 50; // یک عدد بزرگ موقت
                $menuItem->save();
            }
            
            // حالا position های جدید را تنظیم می‌کنیم
            foreach ($data as $menuItem) {
                $newPosition = collect($newOrder)->first(function($item) use ($menuItem) {
                    return $item['id'] == $menuItem->id;
                });
                
                if ($newPosition) {
                    $menuItem->position = $newPosition['position'];
                    $menuItem->save();
                }
            }
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return false;
        }
    }


    public function changeMainMenuPosition(Request $request)
    {
        $data = MainMenuItem::where('name', $request->name)->first();
        $checkAvilabel = MainMenuItem::where('position', $request->position)->first();
        if ($data != null && $checkAvilabel == null) {
            $data->position = $request->position;
            $data->update();
            return true;
        } else {
            return false;
        }
    }

    public function updateMainMenuButtonStyle(Request $request)
    {
        $license = new LicenseFeatureService();
        if (! $license->canCustomizeBotButtons()) {
            return $license->silverRequiredResponse();
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'button_style' => 'nullable|string|in:primary,success,danger',
            'icon_custom_emoji_id' => 'nullable|string|max:64',
            'solo_row' => 'nullable|boolean',
        ]);

        $item = MainMenuItem::where('name', $validated['name'])->first();
        if ($item === null) {
            return response()->json(['success' => false], 404);
        }

        if (array_key_exists('button_style', $validated)) {
            $item->button_style = $validated['button_style'];
        }
        if (array_key_exists('icon_custom_emoji_id', $validated)) {
            $item->icon_custom_emoji_id = $validated['icon_custom_emoji_id'];
        }
        if (array_key_exists('solo_row', $validated)) {
            $item->solo_row = (bool) $validated['solo_row'];
        }

        $item->update();

        return response()->json(['success' => true, 'item' => $item]);
    }
}
