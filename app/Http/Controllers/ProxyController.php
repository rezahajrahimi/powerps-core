<?php

namespace App\Http\Controllers;
use App\Models\Proxy;
use App\Models\Inbound;

use Illuminate\Http\Request;

class ProxyController extends Controller
{
    public function getActiveProxiesByPannelID($pannelID){
        $proxies = Proxy::where('pannel_id', $pannelID)->where('is_active', true)
        ->with('inbounds')
        ->get();
        return $proxies;
    }
    public function getProxiesByPannelID($pannelID){
        $proxies = Proxy::where('pannel_id', $pannelID)
        ->with('inbounds')
        ->get();
        return $proxies;
    }
    public function addNewProxy(Request $request)
    {
        try {
            $proxy = new Proxy();
            $proxy->pannel_id = $request->pannel_id;
            $proxy->type = $request->type;
            $proxy->is_active = $request->is_active;
            $proxy->save();
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function updateProxy(Request $request)
    {
        try {
            $proxy = Proxy::find($request->id);
            $proxy->pannel_id = $request->pannel_id;
            $proxy->type = $request->type;
            $proxy->is_active = $request->is_active;
            if ($proxy->update()) {
                return true;
            } else {
                return response()->json(false, 500);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function deleteProxy($id)
    {
        try {
            $proxy = Proxy::find($id);
            if ($proxy->delete()) {
                $inbound = Inbound::where('proxy_id', $id)->get();
                $inbound->each->delete();
                if ($inbound) {
                    $inbound->delete();
                }
                return true;
            } else {
                return response()->json(false, 500);
            }
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function reActiveProxy($id)
    {
        try {
            $proxy = Proxy::find($id);
            $proxy->is_active = true;
            $proxy->update();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
    public function deActiveProxy($id)
    {
        try {
            $proxy = Proxy::find($id);
            $proxy->is_active = false;
            $proxy->update();
            return true;
        } catch (\Throwable $th) {
            return response()->json(false, 500);
        }
    }
}
