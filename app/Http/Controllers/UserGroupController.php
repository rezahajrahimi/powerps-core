<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupPaymentMethod;
use App\Models\UserGroupVerificationPaymentMethod;
use App\Models\GlobalVerificationPaymentMethod;
use App\Services\PaymentAccessService;
use Illuminate\Http\Request;

class UserGroupController extends Controller
{
    private function paymentAccessService(): PaymentAccessService
    {
        return app(PaymentAccessService::class);
    }

    public function index(Request $request)
    {
        try {
            $query = UserGroup::with(['paymentMethods', 'verificationPaymentMethods'])->withCount('users');

            if ($request->filled('role_type')) {
                $query->where('role_type', $request->role_type);
                if ($request->role_type === 'user') {
                    $query->where('is_default', false);
                }
            }

            if ($request->role_type === 'user') {
                $query->withCount([
                    'users as verified_users_count' => function ($q) {
                        $q->where('is_verified', true);
                    },
                    'users as unverified_users_count' => function ($q) {
                        $q->where('is_verified', false);
                    },
                ]);
            }

            $response = [
                'groups' => $query->orderBy('id')->get(),
                'payment_keys' => UserGroup::PAYMENT_KEY_LABELS,
            ];

            if ($request->role_type === 'user') {
                $this->paymentAccessService()->syncGlobalVerificationPayments();
                $response['verification_stats'] = [
                    'verified' => User::where('role', 'user')->where('is_verified', true)->whereNull('user_group_id')->count(),
                    'unverified' => User::where('role', 'user')->where('is_verified', false)->whereNull('user_group_id')->count(),
                    'without_group' => User::where('role', 'user')->whereNull('user_group_id')->count(),
                ];
                $response['global_verification_payments'] = GlobalVerificationPaymentMethod::orderBy('is_verified')
                    ->orderBy('payment_key')
                    ->get();
            }

            return response()->json($response, 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@index' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'role_type' => 'required|in:user,agent',
        ]);

        try {
            $group = UserGroup::create([
                'name' => $request->name,
                'role_type' => $request->role_type,
                'is_default' => false,
            ]);

            foreach (UserGroup::PAYMENT_KEYS as $key) {
                UserGroupPaymentMethod::create([
                    'user_group_id' => $group->id,
                    'payment_key' => $key,
                    'is_enabled' => true,
                ]);
            }

            return response()->json(['group' => $group->load('paymentMethods')], 201);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@store' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        try {
            $group = UserGroup::findOrFail($id);
            $group->name = $request->name;
            $group->save();

            return response()->json(['group' => $group->load('paymentMethods')], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@update' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $group = UserGroup::findOrFail($id);

            if ($group->is_default) {
                return response()->json(['message' => 'گروه پیش‌فرض قابل حذف نیست.'], 422);
            }

            if ($group->role_type === 'agent') {
                $defaultGroup = UserGroup::where('role_type', 'agent')
                    ->where('is_default', true)
                    ->first();

                if ($defaultGroup) {
                    User::where('user_group_id', $group->id)->update(['user_group_id' => $defaultGroup->id]);
                } else {
                    User::where('user_group_id', $group->id)->update(['user_group_id' => null]);
                }
            } else {
                User::where('user_group_id', $group->id)->update(['user_group_id' => null]);
            }

            $group->delete();

            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@destroy' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function updatePaymentMethods(Request $request, $id)
    {
        $request->validate([
            'payment_methods' => 'required|array',
            'payment_methods.*.payment_key' => 'required|string|in:' . implode(',', UserGroup::PAYMENT_KEYS),
            'payment_methods.*.is_enabled' => 'required|boolean',
        ]);

        try {
            $group = UserGroup::findOrFail($id);

            foreach ($request->payment_methods as $item) {
                UserGroupPaymentMethod::updateOrCreate(
                    [
                        'user_group_id' => $group->id,
                        'payment_key' => $item['payment_key'],
                    ],
                    [
                        'is_enabled' => $item['is_enabled'],
                    ]
                );
            }

            return response()->json(['group' => $group->load('paymentMethods')], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@updatePaymentMethods' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function assignUserToGroup(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'user_group_id' => 'nullable|integer|exists:user_groups,id',
        ]);

        try {
            $user = User::findOrFail($request->user_id);

            if (!in_array($user->role, ['user', 'agent'])) {
                return response()->json(['message' => 'فقط کاربران عادی و نمایندگان قابل دسته‌بندی هستند.'], 422);
            }

            if ($request->user_group_id) {
                $group = UserGroup::findOrFail($request->user_group_id);
                if ($group->role_type !== $user->role) {
                    return response()->json(['message' => 'نوع گروه با نقش کاربر مطابقت ندارد.'], 422);
                }
                $user->user_group_id = $group->id;
            } else {
                $this->paymentAccessService()->assignDefaultGroup($user);
                return response()->json(['user' => $user->fresh()->load('userGroup')], 200);
            }

            $user->save();

            return response()->json(['user' => $user->load('userGroup')], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@assignUserToGroup' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function seedDefaults()
    {
        try {
            $this->paymentAccessService()->syncDefaultGroups();

            $legacyUserDefault = UserGroup::where('role_type', 'user')->where('is_default', true)->first();
            if ($legacyUserDefault) {
                User::where('user_group_id', $legacyUserDefault->id)->update(['user_group_id' => null]);
                $legacyUserDefault->delete();
            }

            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@seedDefaults' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function updateGlobalVerificationPaymentMethods(Request $request)
    {
        $request->validate([
            'is_verified' => 'required|boolean',
            'payment_methods' => 'required|array',
            'payment_methods.*.payment_key' => 'required|string|in:' . implode(',', UserGroup::PAYMENT_KEYS),
            'payment_methods.*.is_enabled' => 'required|boolean',
        ]);

        try {
            foreach ($request->payment_methods as $item) {
                GlobalVerificationPaymentMethod::updateOrCreate(
                    [
                        'is_verified' => $request->is_verified,
                        'payment_key' => $item['payment_key'],
                    ],
                    [
                        'is_enabled' => $item['is_enabled'],
                    ]
                );
            }

            return response()->json([
                'global_verification_payments' => GlobalVerificationPaymentMethod::orderBy('is_verified')
                    ->orderBy('payment_key')
                    ->get(),
            ], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@updateGlobalVerificationPaymentMethods' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function getGroupUsers($id)
    {
        try {
            $group = UserGroup::findOrFail($id);
            $users = User::where('user_group_id', $group->id)
                ->where('role', $group->role_type)
                ->with(['userGroup', 'botUser'])
                ->orderBy('id', 'desc')
                ->get()
                ->map(function (User $user) {
                    $user->bot_user_id = $user->botUser?->id;
                    $user->admin_alias = $user->botUser?->admin_alias;

                    return $user;
                });

            return response()->json(['users' => $users, 'group' => $group], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@getGroupUsers' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function addUsersToGroup(Request $request)
    {
        $request->validate([
            'user_group_id' => 'required|integer|exists:user_groups,id',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        try {
            $group = UserGroup::findOrFail($request->user_group_id);
            $users = User::whereIn('id', $request->user_ids)->get();
            $added = [];

            foreach ($users as $user) {
                if ($user->role !== $group->role_type) {
                    continue;
                }
                $user->user_group_id = $group->id;
                $user->save();
                $added[] = $user->load('userGroup');
            }

            return response()->json(['users' => $added], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@addUsersToGroup' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function updateVerificationPaymentMethods(Request $request, $id)
    {
        $request->validate([
            'is_verified' => 'required|boolean',
            'payment_methods' => 'required|array',
            'payment_methods.*.payment_key' => 'required|string|in:' . implode(',', UserGroup::PAYMENT_KEYS),
            'payment_methods.*.is_enabled' => 'required|boolean',
        ]);

        try {
            $group = UserGroup::findOrFail($id);

            if ($group->role_type !== 'user') {
                return response()->json(['message' => 'تنظیم تایید فقط برای گروه‌های کاربران عادی است.'], 422);
            }

            foreach ($request->payment_methods as $item) {
                UserGroupVerificationPaymentMethod::updateOrCreate(
                    [
                        'user_group_id' => $group->id,
                        'is_verified' => $request->is_verified,
                        'payment_key' => $item['payment_key'],
                    ],
                    [
                        'is_enabled' => $item['is_enabled'],
                    ]
                );
            }

            return response()->json([
                'group' => $group->load(['paymentMethods', 'verificationPaymentMethods']),
            ], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@updateVerificationPaymentMethods' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function clearVerificationPaymentMethods(Request $request, $id)
    {
        $request->validate([
            'is_verified' => 'required|boolean',
        ]);

        try {
            $group = UserGroup::findOrFail($id);

            UserGroupVerificationPaymentMethod::where('user_group_id', $group->id)
                ->where('is_verified', $request->is_verified)
                ->delete();

            return response()->json([
                'group' => $group->load(['paymentMethods', 'verificationPaymentMethods']),
            ], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@clearVerificationPaymentMethods' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }

    public function removeUserFromGroup(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $user = User::findOrFail($request->user_id);

            if (!in_array($user->role, ['user', 'agent'])) {
                return response()->json(['message' => 'کاربر قابل حذف از گروه نیست.'], 422);
            }

            $this->paymentAccessService()->assignDefaultGroup($user);

            return response()->json(['user' => $user->load('userGroup')], 200);
        } catch (\Throwable $th) {
            \Log::error(['UserGroupController@removeUserFromGroup' => $th->getMessage()]);
            return response()->json(null, 500);
        }
    }
}
