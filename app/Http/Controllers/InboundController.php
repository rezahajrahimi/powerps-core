<?php

namespace App\Http\Controllers;
use App\Models\Inbound;

use Illuminate\Http\Request;

class InboundController extends Controller
{
    public function addInbound(Request $request)
    {
        try {
            $inbound = new Inbound();
            $inbound->name = $request->name;
            $inbound->data = $request->data;
            $inbound->proxy_id  = $request->proxy_id ;
            $inbound->is_active = true;
            $inbound->save();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function updateInbound(Request $request)
    {
        try {
            $inbound = Inbound::where('id', $request->id)->first();
            $inbound->name = $request->name;
            $inbound->data = $request->data;
            $inbound->data = $request->proxy_id ;
            $inbound->is_active = $request->is_active;
            $inbound->update();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function deleteInbound($id)
    {
        try {
            $inbound = Inbound::where('id', $id)->first();
            $inbound->delete();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function reActiveInbound($id)
    {
        try {
            $inbound = Inbound::where('id', $id)->first();
            $inbound->is_active = true;
            $inbound->update();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function deActiveInbound($id)
    {
        try {
            $inbound = Inbound::where('id', $id)->first();
            $inbound->is_active = false;
            $inbound->update();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
}
