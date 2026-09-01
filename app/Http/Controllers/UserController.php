<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccountBallance;
use App\Models\BotUser;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentAccessService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class UserController extends Controller
{
    private function paymentAccessService(): PaymentAccessService
    {
        return app(PaymentAccessService::class);
    }
    public function getUsers()
    {
        $users = User::all();
        return response()->json(
            [
                'users' => $users,
            ],
            200,
        );
    }
    private function appendBotUserMeta(User $user): User
    {
        $botUser = $user->relationLoaded('botUser')
            ? $user->botUser
            : BotUser::where('account_id', $user->account_id)->first();
        $user->bot_user_id = $botUser?->id;
        $user->admin_alias = $botUser?->admin_alias;

        return $user;
    }

    private function appendAgentStats(User $user): User
    {
        $balance = AccountBallance::where('account_id', $user->account_id)->first();
        $user->balance_toman = $balance?->ballance ?? 0;
        $user->balance_dollar = $balance?->account_ballance_in_dollar ?? 0;
        $user->sales_count = Product::where('account_id', $user->account_id)->count();
        $user->agent_products_count = $user->agent_products_count
            ?? $user->agent_products()->count();

        $agentPrCntrl = new AgentProductController();
        $user->agent_limit_usage = $agentPrCntrl->getAgentLimitUsage($user->id);

        return $this->appendBotUserMeta($user);
    }

    public function getAgents()
    {
        try {
            $users = User::where('role', 'agent')
                ->with(['userGroup', 'botUser'])
                ->withCount('agent_products')
                ->get()
                ->map(fn (User $user) => $this->appendAgentStats($user));

            return response()->json(
                [
                    'agents' => $users,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(null, 500);
        }
    }
    public function getAgentByIdWithProductsAndPremissons($id)
    {
        try {
            $users = User::where('role', 'agent')
                ->where('id', $id)
                ->with(['agent_products.product_categories', 'agent_permisson', 'userGroup', 'botUser'])
                ->withCount('agent_products')
                ->get()
                ->map(fn (User $user) => $this->appendAgentStats($user));

            return response()->json($users, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(null, 500);
        }
    }
    public function getNormalUsers()
    {
        try {
            $users = User::where('role', 'user')
                ->with(['userGroup', 'botUser'])
                ->get()
                ->map(fn (User $user) => $this->appendBotUserMeta($user));
            return response()->json(
                [
                    'users' => $users,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(null, 500);
        }
    }
    public function get_admin_users()
    {
        $users = User::where('role', 'admin')->get();
        return response()->json([
            'admins' => $users,
        ]);
    }
    public function change_user_role_to_admin($id){

        $user = User::where('account_id', $id)->first();
        if (!$user) {
           return response()->json(null, 401);
        }
        $user->role = 'admin';
        $user->update();
        return response()->json(true, 201);
    }

    public function getUserById($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(
                [
                    'message' => 'User not found',
                ],
                404,
            );
        }
        return response()->json(
            [
                'user' => $user,
            ],
            200,
        );
    }
    public function changeUserRoleToAgent($id)
    {
        $user = User::find($id);
        if (!$user) {
            return null;
        }
        $user->role = 'agent';
        $user->update();
        $this->paymentAccessService()->assignDefaultGroup($user);
        return true;
    }
    public function changeAgentRoleToUser($id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
            return null;
        }
        // checl if user_account_id is not null and is not equal to TELEGRAM_ADMIN_ID in .env
            if ($user->role != 'agent') {
                \Log::info("user {$user->id} is  not agent");
                return null;
            }

            $user->role = 'user';
            $user->update();
            $this->paymentAccessService()->assignDefaultGroup($user);
            return true;
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return false;
        }
    }
    public function change_user_role_to_user($id)
    {
        $user = User::where('account_id', $id)->first();
        if (!$user) {
            return response()->json(null, 401);
        }
        // checl if user_account_id is not null and is not equal to TELEGRAM_ADMIN_ID in .env
        if ($user->account_id == env('TELEGRAM_ADMIN_ID')) {
            return response()->json(null, 401);
        }

        $user->role = 'user';
        $user->update();
        return response()->json(true, 201);
    }
    public function getUserIdByTelegramID($telID)
    {
        $user = User::where('account_id', $telID)->first();
        if (!$user) {
            return null;
        }
        return $user->id;
    }
    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
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

        $this->paymentAccessService()->assignDefaultGroup($user);

        return response()->json(
            [
                'message' => 'User created successfully',
                'user' => $user,
            ],
            201,
        );
    }
    public function updateUser(Request $request)
    {
        try {
            //code..

            $request->validate([
                'name' => 'required|string|max:255',
                'account_id' => 'required|max:8',
                'password' => 'nullable|string|min:8',
                'role' => 'required|string',
            ]);

            $user = User::where('account_id', $request->account_id)->first();
            if (!$user) {
                return response()->json(
                    [
                        'message' => 'User not found',
                    ],
                    404,
                );
            }

            $user->name = $request->name;
            $user->account_id = $request->account_id;
            $user->role = $request->role;
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            $user->save();

            return response()->json(
                [
                    'message' => 'User updated successfully',
                    'user' => $user,
                ],
                200,
            );
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
        }
    }
    public function update_logged_password(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8',
        ]);
        $accountID = auth('sanctum')->user()->account_id;
        $user = User::where('account_id', $accountID)->first();
        if (!$user) {
            return response()->json(
                [
                    'message' => 'User not found',
                ],
                404,
            );
        }

        $user->password = Hash::make($request->password);
        $user->update();

        return response()->json(
            [
                'message' => 'User updated successfully',
                'user' => $user,
            ],
            200,
        );
    }
    public function deleteUser(Request $request)
    {
        $request->validate([
            'account_id' => 'required|max:8',
        ]);

        $user = User::where('account_id', $request->account_id)->first();
        if (!$user) {
            return response()->json(
                [
                    'message' => 'User not found',
                ],
                404,
            );
        }

        $user->delete();

        return response()->json(
            [
                'message' => 'User deleted successfully',
            ],
            200,
        );
    }

    /// Agent
    public function updateAgentPassword(Request $request)
    {
        try {
            //code..

            $request->validate([
                'password' => 'required|string|min:8',
            ]);
            $accountID = auth('sanctum')->user()->account_id;
            $user = User::where('account_id', $accountID)->first();
            if (!$user) {
                return response()->json(
                    [
                        'message' => 'User not found',
                    ],
                    404,
                );
            }

            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
        }
    }

    public function updateUserVerificationStatus(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'is_verified' => 'required|boolean',
        ]);

        try {
            $user = User::findOrFail($request->user_id);

            if ($user->role !== 'user') {
                return response()->json(['message' => 'فقط کاربران عادی قابل تایید هستند.'], 422);
            }

            $user->is_verified = $request->is_verified;
            $user->save();

            return response()->json(['user' => $user->load('userGroup')], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserController@updateUserVerificationStatus' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function getNormalUsersForGrouping(Request $request)
    {
        try {
            $roleType = $request->get('role_type', 'user');
            if (!in_array($roleType, ['user', 'agent'])) {
                return response()->json(['message' => 'نقش نامعتبر است.'], 422);
            }

            $query = User::where('role', $roleType)->with(['userGroup', 'botUser']);

            if ($roleType === 'user' && $request->filled('verification_filter')) {
                if ($request->verification_filter === 'verified') {
                    $query->where('is_verified', true);
                } elseif ($request->verification_filter === 'unverified') {
                    $query->where('is_verified', false);
                }
            }

            if ($request->filled('user_group_id')) {
                $query->where('user_group_id', $request->user_group_id);
            }

            if ($request->filled('exclude_group_id')) {
                $excludeId = $request->exclude_group_id;
                $query->where(function ($q) use ($excludeId) {
                    $q->where('user_group_id', '!=', $excludeId)
                        ->orWhereNull('user_group_id');
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('account_id', 'like', "%{$search}%")
                        ->orWhereHas('botUser', function ($botQuery) use ($search) {
                            $botQuery->where('admin_alias', 'like', "%{$search}%");
                        });
                });
            }

            $users = $query->orderBy('id', 'desc')->get()
                ->map(fn (User $user) => $this->appendBotUserMeta($user));

            return response()->json(['users' => $users], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserController@getNormalUsersForGrouping' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }
}
