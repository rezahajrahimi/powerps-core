<?php
namespace App\Http\Controllers;

use App\Http\Controllers\GeneralController;
use App\Models\User;
use App\Models\BlockedUser;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\TelegramService;
use App\Http\Controllers\CustomTextController;
use App\Services\TelegramMessageFormatter;
use App\Services\LicenseCheckService;

class AuthController extends Controller
{
    private GeneralController $generalCntrl;
    private TelegramService $telegramService;
    private CustomTextController $customText;
    private LicenseCheckService $licenseCheckService;

    public function __construct(?LicenseCheckService $licenseCheckService = null)
    {
        $this->generalCntrl = new GeneralController();
        $this->telegramService = new TelegramService();
        $this->customText = new CustomTextController();
        $this->licenseCheckService = $licenseCheckService ?? app(LicenseCheckService::class);
    }
    public function me()
    {
        return response()->json(auth('sanctum')->user());
    }
    public function getHostName()
    {

        $hostUrl = env('FRONT_URL');

        // get host
        return $hostUrl;
    }
    public function getPowerPsLicenseType()
    {
        return "gold";
        // return $this->licenseCheckService->getLicenseType();
    }

    public function createFirstAdminUser()
    {
        try {
            $admin = User::where('role', 'admin')->first();
            if ($admin == null) {
                // get admin id from .env
                $adminId = env('TELEGRAM_ADMIN_ID');
                $admin = User::create([
                    'name' => 'admin',
                    'account_id' => $adminId,
                    'role' => 'admin',
                    'password' => Hash::make('admin123456'),
                ]);
                $this->generalCntrl->boot_seeding_data();
            }
            return $admin;
        } catch (\Exception $e) {
            \Log::error($e);
            return $e;
        }
    }
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:users',
            'account_id' => 'required|max:8|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'account_id' => $request->account_id,
            'role' => $request->role,
            'password' => Hash::make($request->password),
        ]);

        return response()->json(
            [
                'user' => $user,
                'token' => $user->createToken('token-name')->plainTextToken,
            ],
            201,
        );
    }
    public function login(Request $request)
    {
        // check blocked user
        $blockedUser = new BlockedUser();
        $isBlocked = $blockedUser->where('account_id', $request->account_id)->first();
        if ($isBlocked) {
            return response()->json(['error' => 'The provided credentials are incorrect.'], 401);
        }

        // check first admin login
        $this->createFirstAdminUser();

        $request->validate([
            'account_id' => 'required|max:255', // it's also can be name
            'password' => 'required|string',
        ]);

        $user = User::where('account_id', $request->account_id)
            ->orWhere('name', $request->account_id)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'The provided credentials are incorrect.'], 401);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('token-name')->plainTextToken,
        ]);
    }
    public function logout(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user) {
            $user->tokens()->delete();
        }

        return response()->json('Logged out successfully');
    }
    public function forgetPassword(Request $request)
    {
        // check blocked user
        $blockedUser = new BlockedUser();
        $isBlocked = $blockedUser->where('account_id', $request->account_id)->first();
        if ($isBlocked) {
            return response()->json(['error' => 'The provided credentials are incorrect.'], 401);
        }
        $request->validate([
            'account_id' => 'required|min:8',
        ]);
        $user = User::where('account_id', $request->account_id)->first();
        if (!$user) {
            return response()->json(false);
        }
        $user_password = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRTUVWXYZ2346789'), 0, 8);
        $user->password = Hash::make($user_password);
        $user->update();
        // \Log::info("passss {$user_password}");
        $user_id = $user->account_id;
        $text = "کاربر گرامی \n\r";
        $text .= "رمز عبور شما به پنل تغییر یافت \n\r";
        $text .= 'نام کاربری ورود به پنل:';
        $this->telegramService->sendMessage($user_id, $text, ['parse_mode' => 'Markdown']);
        $this->telegramService->sendMessage($user_id, "<code>{$user_id}</code>", ['parse_mode' => 'HTML']);

        $text = "پسورد ورود به پنل:  \n\r";
        $this->telegramService->sendMessage($user_id, $text, ['parse_mode' => 'Markdown']);
        $this->telegramService->sendMessage($user_id, "<code>{$user_password}</code>", ['parse_mode' => 'HTML']);

        return response()->json(true);
    }
    public function generate_auto_login_link(Request $request)
    {
        try {
            $user = User::where('account_id', $request->account_id)->first();
            if (!$user) {
                return response()->json(false);
            }

            $frontUrl = env('FRONT_URL');

            if (str_ends_with($frontUrl, '/')) {
                $frontUrl = substr($frontUrl, 0, -1);
            }

            $user_password = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRTUVWXYZ2346789'), 0, 8);
            $user->password = Hash::make($user_password);
            $user->update();
            $user_id = $user->account_id;
            $mainMenuCntrl = new MainMenuItemController();
            $menuAliasName = $mainMenuCntrl->getMenuAliasNameByName('webapp');
            $text = $this->customText->getText('action.web.generate_auto_login_link', ['link' => $frontUrl, 'username' => $user_id, 'password' => $user_password]);
            $formatter = new TelegramMessageFormatter($this->telegramService);
            $message = $formatter->addFormattedText('', $text)->getMessage();

            $this->telegramService->sendMessage($user_id, $message);
            $text = $this->customText->getText('action.web.auto_login_link');
            $link = "{$frontUrl}/#/login/{$user_id}/{$user_password}";

            $opr = [
                [
                    'text' => "$menuAliasName",
                    'url' => "$link",
                ],
            ];

            $this->telegramService->sendMessageWithLinkButtons($user_id, $text, $opr);

            return response()->json(true);
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json(false);
        }
    }
}
