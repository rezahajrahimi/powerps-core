<?php

namespace App\Http\Controllers;
use App\Models\ChannelLock;

use Illuminate\Http\Request;

class ChannelLockController extends Controller
{
    public function createNewChannelLock(Request $request)
    {
        try {
            $data = new ChannelLock();
            $data->channel_id = $request->channel_id;
            $data->is_active = $request->is_active;

            $data->save();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function editChannelLock(Request $request)
    {
        try {
            $data = ChannelLock::where('id', $request->id)->first();
            if ($data != null) {
                $data->channel_id = $request->channel_id;
                $data->is_active = $request->is_active;

                $data->update();
                return true;
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return false;
        }
    }
    public function deActiveChannelLockByID($id)
    {
        try {
            $data = ChannelLock::where('id', $id)->first();
            if ($data != null) {
                $data->is_active = false;

                $data->update();
                return true;
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function reActiveChannelLockByID($id)
    {
        try {
            $data = ChannelLock::where('id', $id)->first();
            if ($data != null) {
                $data->is_active = true;

                $data->update();
                return true;
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function deleteChannelLockByID($id)
    {
        try {
            $data = ChannelLock::where('id', $id)->first();
            if ($data != null) {
                $data->delete();
                return true;
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function getAllChannelLock()
    {
        try {
            return ChannelLock::all();
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function getAllActiveChannelLock()
    {
        try {
            return ChannelLock::where('is_active',true)->get();
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
}
