<?php

namespace App\Http\Controllers;

use App\Models\AppInfo;
use App\Services\LicenseFeatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AppInfoController extends Controller
{
    private function goldLicenseRequired()
    {
        $license = new LicenseFeatureService();
        if (! $license->isGold()) {
            return $license->goldRequiredResponse();
        }

        return null;
    }

    public function index()
    {
        $appInfo = AppInfo::first();
        if (empty($appInfo)) {
            // seed appinfo
            $data = new AppInfo();
            $data->name = 'Power PS';
            $data->version = '1.0.0';
            $data->image = 'default.png'; // Set a default image or handle it as needed
            $data->save();
            $appInfo = $data; // Reassign to the newly created AppInfo instance
        }
        return response()->json($appInfo->getAppInfo());
    }

    public function update(Request $request)
    {
        if ($denied = $this->goldLicenseRequired()) {
            return $denied;
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'version' => 'nullable|string|max:50',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'background_color' => 'nullable|string|max:20',
            'panel_title' => 'nullable|string|max:255',
            'footer_text' => 'nullable|string|max:500',
            'show_powerps_credit' => 'nullable|boolean',
        ]);

        $appInfo = AppInfo::first();
        $appInfo->setAppInfo($data);

        return response()->json(['message' => 'App info updated successfully']);
    }
    public function save_image(Request $request)
    {
        if ($denied = $this->goldLicenseRequired()) {
            return $denied;
        }

        $image = $request->file('image');
        $imagePath = 'images/appinfo/';
        $imageName = time() . '.' . $image->getClientOriginalExtension(); // نام یکتا با پسوند
        try {
            // ایجاد دایرکتوری اگر وجود ندارد
            Storage::disk('public')->makeDirectory($imagePath);

            // ذخیره فایل با نام یکتا و پسوند صحیح در دیسک public
            Storage::disk('public')->putFileAs($imagePath, $image, $imageName);

            $appInfo = AppInfo::first();
            $appInfo->image = '/storage/' . $imagePath . $imageName;
            $appInfo->save();
            return response()->json($appInfo->image, 200);





        } catch (\Throwable $th) {
            \Log::info("save app image:  $th");
        }

        // Return the image path or any other response as needed
        $appInfo = AppInfo::first();
        if (!$appInfo) {
            return response()->json(['error' => 'App info not found'], 404);
        }
        return response()->json($appInfo->image, 200);

        // Save the filename to the database or perform any other necessary actions
        // This is a placeholder for the actual implementation
    }
}
