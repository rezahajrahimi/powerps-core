<?php
namespace App\Http\Controllers;

use App\Models\AgentPermisson;
use Illuminate\Http\Request;

class AgentPermissonController extends Controller
{
    public function getUserPremission()
    {
        try {
            $userId = auth()->user()->id;

            $data = AgentPermisson::where('user_id', $userId)->first();
            if(isset($data)) {
                return $data;
            }
            return null;
        } catch (\Throwable $th) {
            return null;
        }
    }
    public function getUserPremissionByAgentID($userId)
    {
        $data = AgentPermisson::where('user_id', $userId)->first();
        if ($data != null) {
            return $data;
        }
        return null;
    }

    public function createANewAgentPermisson(Request $request)
    {
        try {
            $agentPermisson                        = new AgentPermisson();
            $agentPermisson->user_id               = $request->user_id;
            $agentPermisson->minus_ballance        = $request->minus_ballance == 'false' || $request->minus_ballance == false || $request->minus_ballance == 0 ? 0 : 1;
            $agentPermisson->minus_ballance_limit  = $this->resolveMinusBallanceLimit($request->minus_ballance_limit);
            $agentPermisson->create_products       = $request->create_products == 'false' || $request->create_products == false || $request->create_products == 0 ? 0 : 1;
            $agentPermisson->delete_products       = $request->delete_products == 'false' || $request->delete_products == false || $request->delete_products == 0 ? 0 : 1;
            $agentPermisson->traffic_limitation_tb = $request->traffic_limitation_tb ? $request->traffic_limitation_tb : 10;
            $agentPermisson->product_limitation    = $request->product_limitation ? $request->product_limitation : 1000;
            $agentPermisson->save();
            return response()->json($agentPermisson, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    public function updateAgentPremisson(Request $request)
    {
        try {
            $agentPermisson = AgentPermisson::where('user_id', $request->user_id)->first();
            if ($agentPermisson == null) {
                return $this->createANewAgentPermisson($request);
            }
            $agentPermisson->minus_ballance        = $request->minus_ballance == 'false' || $request->minus_ballance == 0 ? 0 : 1;
            $agentPermisson->minus_ballance_limit  = $this->resolveMinusBallanceLimit($request->minus_ballance_limit);
            $agentPermisson->create_products       = $request->create_products == 'false' || $request->create_products == 0 ? 0 : 1;
            $agentPermisson->delete_products       = $request->delete_products == 'false' || $request->delete_products == 0 ? 0 : 1;
            $agentPermisson->traffic_limitation_tb = $request->traffic_limitation_tb ? $request->traffic_limitation_tb : 10;
            $agentPermisson->product_limitation    = $request->product_limitation ? $request->product_limitation : 1000;

            $agentPermisson->update();
            return response()->json($agentPermisson, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
    private function resolveMinusBallanceLimit($value): ?float
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        $limit = (float) $value;

        return $limit > 0 ? $limit : null;
    }

    public function deleteAgentPremisson($userID)
    {
        try {
            $agentPermisson = AgentPermisson::where('user_id', $userID)->first();
            if ($agentPermisson == null) {
                return response()->json(false, 404);
            }
            $agentPermisson->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("throw $th");
            return response()->json(false, 500);
        }
    }
}
