<?php

namespace App\Http\Controllers;
use App\Models\Support;
use App\Models\SupportCategory;

use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function getSupporstList()
    {
        try {
            return Support::all();
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(false, 401);
        }
    }
    public function getSupportById($id)
    {
        try {
            return Support::where('id', $id)->first();
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(false, 401);
        }
    }
    public function createNewSupport(Request $request)
    {
        try {
        $support = new Support();
        $support->question = $request->question;
        $support->answer = $request->answer;
        $support->response_type = $request->response_type;
        $support->save();
        return true;
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(false, 401);
        }
    }
    public function updateSupportById(Request $request)
    {
        try {
            $support = Support::where('id', $request->id)->first();
            if ($support != null) {
                $support->question = $request->question;
                $support->answer = $request->answer;
                $support->response_type = $request->response_type;
                $support->update();
                return true;
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(false, 401);
        }
    }
    public function deleteSupportById($id)
    {
        try {
            $support = Support::where('id', $id)->first();
            if ($support != null) {
                $support->delete();
                return true;
            } else {
                return response()->json(false, 401);
            }
        } catch (\Throwable $th) {
            \Log::info($th);
            return response()->json(false, 401);
        }
    }
}
