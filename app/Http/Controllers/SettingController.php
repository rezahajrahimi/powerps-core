<?php

namespace App\Http\Controllers;
use App\Models\Setting;
use App\Http\Controllers\DotenvEditor;
use App\Services\ConfigNameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SettingController extends Controller
{
    public function seed()
    {
        if (Setting::all()->isEmpty()) {
            $setting = new Setting();
            $setting->bot_name = 'powerPsBot';
            $setting->admin_id = env('TELEGRAM_ADMIN_ID');
            $setting->bot_token = env('TELEGRAM_BOT_TOKEN');
            $setting->welcome_message = 'به ربات  [@powerPsBot] خوش آمدید.';
            $setting->panel_address = env('APP_URL');
            $setting->config_name_prefix = ConfigNameService::DEFAULT_PREFIX;
            $setting->config_name_format = ConfigNameService::DEFAULT_FORMAT;
            $setting->use_admin_alias_in_config_name = true;
            $setting->save();
            return true;
        }
        return false;
    }
    public function getWelcomeMessage()
    {
        return Setting::All()->first()->welcome_message;
    }
    public function getAdminId()
    {
        return Setting::All()->first()->admin_id;
    }
    public function getBotToken()
    {
        return Setting::find(1)->bot_token;
    }
    public function getBotSetting()
    {
        $setting = Setting::All()->first();
        if ($setting != null) {
            return $setting;
        } else {
            $this->seed();
            return $this->getBotSetting();
        }
    }
    public function updateBotSetting(Request $request)
    {
        $request->validate([
            'bot_token' => 'required|string',
            'bot_name' => 'required|string',
            'admin_id' => 'required',
            'panel_address' => 'required|string',
            'config_name_prefix' => 'nullable|string|max:20|regex:/^[a-zA-Z0-9]*$/',
            'config_name_format' => 'nullable|string|max:64|regex:/^[a-zA-Z0-9_{}\-]*$/',
            'use_admin_alias_in_config_name' => 'nullable|boolean',
        ]);

        $data = Setting::first();
        if (!$data) {
            $data = new Setting();
        }

        $data->bot_name = $request->bot_name;
        $data->admin_id = $request->admin_id;
        $data->bot_token = $request->bot_token;
        $data->panel_address = $request->panel_address;
        $data->config_name_prefix = ConfigNameService::normalizePrefix(
            $request->input('config_name_prefix')
        );
        $data->config_name_format = ConfigNameService::normalizeFormat(
            $request->input('config_name_format')
        );
        if ($request->has('use_admin_alias_in_config_name')) {
            $data->use_admin_alias_in_config_name = $request->boolean('use_admin_alias_in_config_name');
        } elseif (! $data->exists) {
            $data->use_admin_alias_in_config_name = true;
        }

        // Only set welcome message if it's empty
        if (empty($data->welcome_message)) {
            $data->welcome_message = "خوش آمدید";
        }

        if ($data->save()) {
            // تغییر مقادیر در فایل .env
            $path = base_path('.env');
            if (file_exists($path) && is_writable($path)) {
                $envContent = file_get_contents($path);

                $replacements = [
                    'TELEGRAM_BOT_TOKEN' => $request->bot_token,
                    'TELEGRAM_ADMIN_ID' => $request->admin_id,
                    'APP_URL' => $request->panel_address,
                ];

                foreach ($replacements as $key => $value) {
                    if (preg_match("/^{$key}=/m", $envContent)) {
                        $envContent = preg_replace("/^{$key}=.*/m", "{$key}=\"{$value}\"", $envContent);
                    } else {
                        $envContent .= "\n{$key}=\"{$value}\"";
                    }
                }

                file_put_contents($path, $envContent);
            }

            Artisan::call('config:clear');

            return response()->json([
                'status' => true,
                'message' => 'تنظیمات با موفقیت بروزرسانی شد',
                'data' => $data
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'خطا در بروزرسانی تنظیمات'
            ], 500);
        }
    }
    public function getMainUrl()
    {
        $data = Setting::find(1);
        $string = $data->panel_address;
        $endsWith = '/';
        $result = str_ends_with($string, $endsWith) ? 'is' : 'is not';
        if ($result == 'is not') {
            return $data->panel_address;
        } else {
            // remove last charecter in string
            $string = substr($string, 0, -1);
            return $string;
        }
    }
    public function get_bot_name()
    {
        $data = Setting::All()->first();
        $name = $data->bot_name;
        if ($name != null) {
            // check is name have @ , if has remove it
            $name = str_replace('@', '', $name);
            return $name;
        } else {
            return 'setbotname';
        }
    }
}
