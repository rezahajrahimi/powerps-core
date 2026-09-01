<?php

namespace App\Http\Controllers;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // public function getUserOrder($userID)
    // {
    //     $data = Order::where('account_id', $userID)->get();
    //     if ($data != null) {
    //         return $data;
    //     } else {
    //         return null;
    //     }
    // }
    public function addUserOrder($userID, $price, $product_categories_id, $productId)
    {
        $orderNUmber = $transaction = new Order();
        $transaction->account_id = $userID;
        $transaction->price = $price;
        $transaction->product_categories_id = $product_categories_id;
        $transaction->product_id = $productId;
        $transaction->order_number = "28$productId";
        return $transaction->save();
    }
    public function getPoductNumberByOrderNumber($order_number, $account_id)
    {
        $order = Order::where('account_id', $account_id)
            ->where('order_number', $order_number)

            ->first();
        if ($order != null) {
            return $order->product_id;
        } else {
            return false;
        }
    }
    public function getUserOrder($userID)
    {
        $data = Order::where('account_id', $userID)
            ->with('product_category')
            ->get();
        if ($data != null) {
            return $data;
        } else {
            return null;
        }
    }
}
