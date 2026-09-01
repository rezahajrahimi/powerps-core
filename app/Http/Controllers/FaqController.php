<?php

namespace App\Http\Controllers;
use App\Models\Faq;

use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function createNewFac(Request $request)
    {
        try {
            $faq = new Faq();
            $faq->question = $request->question;
            $faq->answer = $request->answer;
            $faq->save();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function updateFac(Request $request)
    {
        try {
            $faq = Faq::where('id', $request->id)->first();
            if ($faq != null) {
                $faq->question = $request->question;
                $faq->answer = $request->answer;
                $faq->update();
                return true;
            } else {
                return response()->json(false, 404);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function getFaqList(){
        try {
            return Faq::all();
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function deleteFacById($id)
    {
        try {
            $faq = Faq::where('id', $id)->first();
            if ($faq != null) {
                $faq->delete();
                return true;
            } else {
                return response()->json(false, 404);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
    public function getFaqById($id)
    {
        try {
            $faq = Faq::where('id', $id)->first();
            if ($faq != null) {
                return $faq;
            } else {
                return response()->json(false, 404);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 401);
        }
    }
}
