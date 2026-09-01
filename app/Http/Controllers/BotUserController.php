<?php

namespace App\Http\Controllers;
use App\Models\BotUser;
use App\Models\User;
use App\Models\AdminMessage;
use Illuminate\Support\Facades\Hash;
use App\Services\TelegramService;
use App\Jobs\BatchMessageJob;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BotUserController extends Controller
{
    public function createNewUserBot($account_id, $userName, $firstName, $lastName)
    {
        $logCtrl = new LogController();
        $logCtrl->addNewLog('user', 'کاربر جدید وارد ربات شد.', $account_id, $userName, 'new user');
        $botUser = BotUser::firstOrCreate([
            'account_id' => $account_id,
            'username' => $userName,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        $user = new User;
        $user->name = $userName;
        $user->account_id = $account_id;
        $user->password = Hash::make("12345678");
        $user->role = "user";
        $user->save();
        return $botUser;

    }
    public function hasRegistred($account_id, $userName, $firstName, $lastName)
    {
        $user = BotUser::where('account_id', $account_id)->first();
        if ($user != null) {
            return true;
        } else {
            $this->createNewUserBot($account_id, $userName, $firstName, $lastName);
            return false;
        }
    }
    public function getBotUserList()
    {
        try {
            $data = BotUser::all();
            if ($data != null) {
                return response()->json($data, 200);
            } else {
                return response()->json('No Data', 404);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getBotUserListByPagination()
    {
        try {
            $data = BotUser::with('user.userGroup')->paginate(16, ['*'], 'page');
            return $data;
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function get_last_10_bot_user()
    {
        try {
            return BotUser::orderBy('id', 'desc')->take(10)->get();
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function get_users_by_past_days($days)
    {
        try {
            $startDay = Carbon::now()->subDays($days)->startOfDay();
            $endDay = Carbon::now()->endOfDay();
            $data = BotUser::whereBetween('created_at', [$startDay, $endDay])->orderBy('id', 'desc')->get();
            return $data;
        } catch (\Throwable $th) {
            \Log::debug('get_users_by_past_days' . $th->getMessage());
            return response()->json('get_users_by_past_days error', 500);
        }
    }
    public function get_users_with_zero_configs()
    {
        try {
            $data = BotUser::with('products')->get();
            // get all $data which have zero count of products
            $data = $data->filter(function ($user) {
                return $user->products->count() === 0;
            })->values();
            return $data;
        } catch (\Throwable $th) {
            \Log::debug('get_users_by_past_days' . $th->getMessage());
            return response()->json('get_users_by_past_days error', 500);
        }
    }
    public function get_users_with_zero_ballance()
    {
        try {
            $data = BotUser::with('ballance')->get();
            // get all $data which have zero count of products
            $data = $data->filter(function ($user) {
                if (isset($user->ballance)) {
                    return intval(value: $user->ballance->ballance) === 0;
                }
            })->values();
            return $data;
        } catch (\Throwable $th) {
            \Log::debug('get_users_with_zero_ballance' . $th->getMessage());
            return response()->json('get_users_with_zero_ballance error', 500);
        }
    }

    public function get_agent_role_bot_users()
    {
        try {
            $data = BotUser::with('user')
                ->get();
            $data = $data->filter(function ($user) {
                if (isset($user->user)) {
                    return $user->user->role === 'agent';
                }
            })->values();
            if ($data != null) {
                return $data;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }

    public function search_bot_users(Request $request)
    {
        try {
            $data = BotUser::where('username', 'like', '%' . $request->search . '%')
                ->orWhere('first_name', 'like', '%' . $request->search . '%')
                ->orWhere('last_name', 'like', '%' . $request->search . '%')
                ->orWhere('admin_alias', 'like', '%' . $request->search . '%')
                ->orWhere('account_id', 'like', '%' . $request->search . '%')
                ->get();

            return $data;
        } catch (\Throwable $th) {
            \Log::info("search_bot_users:  $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getBotUserByID($id)
    {
        try {
            $data = BotUser::where('id', $id)
                ->with(['products', 'transaction', 'ballance', 'logs', 'user.userGroup', 'blocked_user'])
                ->first();
            if ($data != null) {
                return response()->json($data, 200);
            } else {
                return response()->json('No Data', 404);
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");

            return response()->json('Server Error', 500);
        }
    }

    public function updateBotUserAdminAlias(Request $request)
    {
        try {
            $validated = $request->validate([
                'bot_user_id' => 'required_without:account_id|integer|exists:bot_users,id',
                'account_id' => 'required_without:bot_user_id|integer|exists:bot_users,account_id',
                'admin_alias' => 'nullable|string|max:100',
            ]);

            $botUser = isset($validated['bot_user_id'])
                ? BotUser::findOrFail($validated['bot_user_id'])
                : BotUser::where('account_id', $validated['account_id'])->firstOrFail();
            $alias = isset($validated['admin_alias'])
                ? trim($validated['admin_alias'])
                : null;
            $botUser->admin_alias = $alias === '' ? null : $alias;
            $botUser->save();

            return response()->json(['bot_user' => $botUser], 200);
        } catch (\Throwable $th) {
            \Log::info("updateBotUserAdminAlias: $th");

            return response()->json('Server Error', 500);
        }
    }
    public function getLast10Users()
    {
        try {
            $data = BotUser::orderBy('id', 'desc')
                ->limit(10)
                ->get();
            if ($data != null) {
                return $data;
            } else {
                return null;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable:  $th");
        }
    }

    public function getUserIDByAccountID($accountID)
    {
        $data = BotUser::where('account_id', $accountID)->first();
        if ($data != null) {
            return $data->id;
        } else {
            return null;
        }
    }
    public function send_Admin_message_to_All_users(Request $request)
    {
        try {
            $message = $request->message;
            $scheduledAt = $request->scheduled_at; // Optional: ISO 8601 date string
            $imagePath = null;

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $directory = public_path('storage/admin_messages');
                if (!file_exists($directory)) {
                    mkdir($directory, 0777, true);
                }
                $file->move($directory, $filename);
                $imagePath = 'storage/admin_messages/' . $filename;
            }

            $userIds = BotUser::pluck('account_id')->toArray();

            if (empty($userIds)) {
                return response()->json(['message' => 'No users found'], 404);
            }

            $adminMessage = AdminMessage::create([
                'message' => $message,
                'image_path' => $imagePath,
                'type' => $imagePath ? 'photo' : 'text',
                'status' => 'pending',
                'total_users' => count($userIds),
                'recipient_ids' => $userIds,
                'scheduled_at' => $scheduledAt ? Carbon::parse($scheduledAt) : null,
            ]);

            $job = new BatchMessageJob('send_to_all', $userIds, $message, [], $adminMessage->id);

            if ($scheduledAt) {
                $delay = Carbon::parse($scheduledAt);
                if ($delay->isFuture()) {
                    $job->delay($delay);
                }
            }

            dispatch($job);

            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::error('send_Admin_message_to_All_users: ' . $th->getMessage());
            return response()->json(['message' => 'Server Error: ' . $th->getMessage()], 500);
        }
    }

    public function send_Admin_message_to_Selected_users(Request $request)
    {
        try {
            $message = $request->message;
            $userIds = $request->user_ids; // Array of account_ids
            if (is_string($userIds)) {
                $userIds = json_decode($userIds, true);
            }
            $scheduledAt = $request->scheduled_at; // Optional
            $imagePath = null;

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $directory = public_path('storage/admin_messages');
                if (!file_exists($directory)) {
                    mkdir($directory, 0777, true);
                }
                $file->move($directory, $filename);
                $imagePath = 'storage/admin_messages/' . $filename;
            }

            if (empty($userIds)) {
                return response()->json(['message' => 'No users selected'], 400);
            }

            $adminMessage = AdminMessage::create([
                'message' => $message,
                'image_path' => $imagePath,
                'type' => $imagePath ? 'photo' : 'text',
                'status' => 'pending',
                'total_users' => count($userIds),
                'recipient_ids' => $userIds,
                'scheduled_at' => $scheduledAt ? Carbon::parse($scheduledAt) : null,
            ]);

            $job = new BatchMessageJob('send_to_selected', $userIds, $message, [], $adminMessage->id);

            if ($scheduledAt) {
                $delay = Carbon::parse($scheduledAt);
                if ($delay->isFuture()) {
                    $job->delay($delay);
                }
            }

            dispatch($job);

            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::error('send_Admin_message_to_Selected_users: ' . $th->getMessage());
            return response()->json(['message' => 'Server Error: ' . $th->getMessage()], 500);
        }
    }

    public function send_admin_message_to_all_users_without_configs(Request $request)
    {
        try {
            $data = BotUser::with('products')->get();
            // get all $data which have zero count of products
            $userIds = $data->filter(function ($user) {
                return $user->products->count() === 0;
            })->pluck('account_id')->toArray();

            if (empty($userIds)) {
                return response()->json(['message' => 'No users found'], 404);
            }

            $message = $request->message;
            $scheduledAt = $request->scheduled_at;
            $imagePath = null;

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $directory = public_path('storage/admin_messages');
                if (!file_exists($directory)) {
                    mkdir($directory, 0777, true);
                }
                $file->move($directory, $filename);
                $imagePath = 'storage/admin_messages/' . $filename;
            }

            $adminMessage = AdminMessage::create([
                'message' => $message,
                'image_path' => $imagePath,
                'type' => $imagePath ? 'photo' : 'text',
                'status' => 'pending',
                'total_users' => count($userIds),
                'recipient_ids' => $userIds,
                'scheduled_at' => $scheduledAt ? Carbon::parse($scheduledAt) : null,
            ]);

            $job = new BatchMessageJob('send_to_no_configs', $userIds, $message, [], $adminMessage->id);

            if ($scheduledAt) {
                $delay = Carbon::parse($scheduledAt);
                if ($delay->isFuture()) {
                    $job->delay($delay);
                }
            }

            dispatch($job);

            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::error('send_admin_message_to_all_users_without_configs: ' . $th->getMessage());
            return response()->json(['message' => 'Server Error: ' . $th->getMessage()], 500);
        }
    }

    public function get_admin_messages()
    {
        try {
            $paginated = AdminMessage::orderBy('id', 'desc')->paginate(20);

            // Transform each item to include user details for sent and failed lists
            $items = $paginated->items();
            $transformed = [];
            foreach ($items as $msg) {
                $arr = $msg->toArray();

                // Sent users detail
                $sentDetails = [];
                if (!empty($msg->sent_ids) && is_array($msg->sent_ids)) {
                    $users = \App\Models\BotUser::whereIn('account_id', $msg->sent_ids)
                        ->get(['account_id', 'username', 'first_name', 'last_name'])
                        ->keyBy('account_id');

                    foreach ($msg->sent_ids as $id) {
                        if (isset($users[$id])) {
                            $u = $users[$id];
                            $sentDetails[] = [
                                'account_id' => $u->account_id,
                                'username' => $u->username,
                                'first_name' => $u->first_name,
                                'last_name' => $u->last_name,
                            ];
                        } else {
                            $sentDetails[] = ['account_id' => $id];
                        }
                    }
                }

                // Failed users detail
                $failedDetails = [];
                if (!empty($msg->failed_ids) && is_array($msg->failed_ids)) {
                    $failedIds = array_map(function ($f) {
                        return $f['user_id'] ?? null;
                    }, $msg->failed_ids);

                    $users = \App\Models\BotUser::whereIn('account_id', $failedIds)
                        ->get(['account_id', 'username', 'first_name', 'last_name'])
                        ->keyBy('account_id');

                    foreach ($msg->failed_ids as $f) {
                        $uid = $f['user_id'] ?? null;
                        $err = $f['error'] ?? null;
                        if ($uid && isset($users[$uid])) {
                            $u = $users[$uid];
                            $failedDetails[] = [
                                'account_id' => $u->account_id,
                                'username' => $u->username,
                                'first_name' => $u->first_name,
                                'last_name' => $u->last_name,
                                'error' => $err,
                            ];
                        } else {
                            $failedDetails[] = ['account_id' => $uid, 'error' => $err];
                        }
                    }
                }

                $arr['sent_users_detail'] = $sentDetails;
                $arr['failed_users_detail'] = $failedDetails;

                // Recipient details (if stored)
                $recipientDetails = [];
                if (!empty($msg->recipient_ids) && is_array($msg->recipient_ids)) {
                    $recUsers = \App\Models\BotUser::whereIn('account_id', $msg->recipient_ids)
                        ->get(['account_id', 'username', 'first_name', 'last_name'])
                        ->keyBy('account_id');

                    foreach ($msg->recipient_ids as $id) {
                        $status = 'pending';
                        // check failed
                        foreach ($failedDetails as $f) {
                            if (($f['user_id'] ?? null) == $id) {
                                $status = 'failed';
                                break;
                            }
                        }
                        // check sent
                        if ($status !== 'failed' && in_array($id, $msg->sent_ids ?? [])) {
                            $status = 'sent';
                        }

                        if (isset($recUsers[$id])) {
                            $u = $recUsers[$id];
                            $recipientDetails[] = [
                                'account_id' => $u->account_id,
                                'username' => $u->username,
                                'first_name' => $u->first_name,
                                'last_name' => $u->last_name,
                                'status' => $status,
                            ];
                        } else {
                            $recipientDetails[] = ['account_id' => $id, 'status' => $status];
                        }
                    }
                }

                $arr['recipient_details'] = $recipientDetails;

                $transformed[] = $arr;
            }

            $paginatedArray = $paginated->toArray();
            $paginatedArray['data'] = $transformed;

            return response()->json($paginatedArray);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Server Error'], 500);
        }
    }

    public function delete_admin_message($id)
    {
        try {
            $msg = AdminMessage::find($id);
            if ($msg) {
                $msg->delete();
                return response()->json(true, 200);
            }
            return response()->json(false, 404);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Server Error'], 500);
        }
    }

}
