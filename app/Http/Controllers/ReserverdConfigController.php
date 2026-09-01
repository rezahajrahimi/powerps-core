<?php

namespace App\Http\Controllers;

use App\Models\ReserverdConfig;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Http\Request;

class ReserverdConfigController extends Controller
{
    public function add_reserverd_config_to_a_user(Request $request)
    {
        try {
            $reserverdConfig = new ReserverdConfig();
            $reserverdConfig->product_id = $request->product_id;
            $reserverdConfig->user_id = $request->user_id;
            $reserverdConfig->save();
            return true;
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return response()->json(false, 500);
        }
    }
    public function delete_reserverd_config_by_product_id(Request $request)
    {
        try {
            // get reserverd config and checl user_id by user_id in auth ueser
            $reserverdConfig = ReserverdConfig::where('product_id', $request->product_id)->first();
            if ($reserverdConfig->user_id == auth()->user()->id) {
                $reserverdConfig->delete();
                return true;
            }
            return response()->json(false, 401);
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return response()->json(false, 500);
        }
    }
    public function check_a_product_has_reserved_config_by_product_id(Request $request)
    {
        try {
            // get reserverd config and checl user_id by user_id in auth ueser
            $reserverdConfig = ReserverdConfig::where('product_id', $request->product_id)->first();
            // check $reservedConfig is null or not
            if ($reserverdConfig == null) {
                return response()->json(false, 401);
            }
            if ($reserverdConfig->user_id == auth()->user()->id) {
                return true;
            }
            return response()->json(false, 401);
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return response()->json(false, 500);
        }
    }
    public function delete_a_reserved_config_by_product_id_by_admin(Request $request)
    {
        try {
            // get reserverd config and checl user_id by user_id in auth ueser
            $reserverdConfig = ReserverdConfig::where('product_id', $request->product_id)->first();
            // check reques->cashback is true or false
            if ($request->cashback == true || $request->cashback == 1) {
                //
                $product = Product::where('id', $reserverdConfig->product_id)
                    ->with('product_category_and_panel')
                    ->first();

                $accBlCtrl = new AccountBallanceController();
                // check user role
                $user = User::where('id', $reserverdConfig->user_id)->first();
                $productPrice = $product->product_category_and_panel->price;
                $accountID = $user->account_id;
                if ($user->role == 'agent') {
                    $agentProduct = AgentProduct::where('product_categories_id', $product->product_category_and_panel->id)
                        ->where('user_id', $userID)
                        ->first();
                    if ($agentProduct) {
                        $productPrice = $agentProduct->price;
                    }
                }
                $inc = $accBlCtrl->incUserAccuntBalance($accountID, $productPrice, 0);
                $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت حذف بسته رزور برگشت داده شد.", 'add ballance');
            }

            $reserverdConfig->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return response()->json(false, 500);
        }
    }
    public function delete_a_reserved_config_by_product_id_by_user_and_agnet(Request $request)
    {
        try {
            // get reserverd config and checl user_id by user_id in auth ueser
            $userID = auth('sanctum')->user()->id;

            $reserverdConfig = ReserverdConfig::where('product_id', $request->product_id)
                ->where('user_id', $userID)
                ->first();
            if ($reserverdConfig == null) {
                return response()->json(false, 401);
            }
            $agentPermisson = AgentPermisson::where('user_id', $userID)->first();

            if ($agentPermisson != null) {
                $usedProductTerrafic = Product::where('account_id', $accountID)->leftJoin('product_categories', 'products.product_categories_id', '=', 'product_categories.id')->sum('product_categories.volume');
                //convert $usedProductTerrafic from Gb to TB
                if ($usedProductTerrafic != null || $usedProductTerrafic != 0) {
                    $usedProductTerrafic = $usedProductTerrafic / 1000;
                }

                if ($usedProductTerrafic >= $agentPermisson->traffic_limitation_tb) {
                    \Log::info("usedProductTerrafic: {$usedProductTerrafic} > {$agentPermisson->traffic_limitation_tb}");

                    return response()->json('Reached to Max Terrafic Limitation', 401);
                }
            }

            //
            $product = Product::where('id', $reserverdConfig->product_id)
                ->with('product_category_and_panel')
                ->first();

            $accBlCtrl = new AccountBallanceController();

            // check user role
            $productPrice = $product->product_category_and_panel->price;
            $accountID = $user->account_id;
            if (auth('sanctum')->user()->role == 'agent') {
                $agentProduct = AgentProduct::where('product_categories_id', $product->product_category_and_panel->id)
                    ->where('user_id', $userID)
                    ->first();
                if ($agentProduct) {
                    $productPrice = $agentProduct->price;
                }
            }
            $inc = $accBlCtrl->incUserAccuntBalance($accountID, $productPrice, 0);
            $this->addNewBotLog('ballance', "مبلغ  $productPrice را از حساب کاربری بابت حذف بسته رزور برگشت داده شد.", 'add ballance');

            $reserverdConfig->delete();
            return response()->json(true, 200);
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return response()->json(false, 500);
        }
    }
    public function addNewBotLog($type, $message, $event)
    {
        $accountID = auth('sanctum')->user()->account_id;
        $name = auth('sanctum')->user()->name;

        $logCtrl = new LogController();
        $logCtrl->addNewLog($type, $message, $accountID, $name, $event);
        return true;
    }
}
