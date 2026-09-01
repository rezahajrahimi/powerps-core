<?php

namespace App\Http\Controllers;
use App\Models\Log;

use Illuminate\Http\Request;

class LogController extends Controller
{
    public function addNewLog($type, $message, $account_id, $username, $event)
    {
        Log::create([
            'type' => $type,
            'message' => $message,
            'account_id' => $account_id,
            'username' => $username,
            'event' => $event,
        ]);
        return true;
    }
    public function getAllLogs($count = 400)
    {
        try {
            return Log::take($count)->orderBy('id', 'desc')->get();
        } catch (\Throwable $th) {
            return response()->json('server error', 500);
        }
    }

    public function getAllLogsOfLoggedAgent($count = 100)
    {
        try {
            $account_id = auth('sanctum')->user()->account_id;

            return Log::take($count)->where('account_id', $account_id)->orderBy('id', 'desc')->get();
        } catch (\Throwable $th) {
            return response()->json('server error', 500);
        }
    }
}
