<?php

namespace App\Http\Controllers;

use App\Models\TestAccount;
use App\Models\Pannel;
use App\Models\ProductCategory;
use App\Models\UsedTestAccount;
use Illuminate\Http\Request;

class TestAccountController extends Controller
{
    public const CATEGORY_NAME = 'اکانت آزمایشی';

    public function getTestAccountDetails()
    {
        try {
            $data = TestAccount::first();
            if ($data == null) {
                $pannel = Pannel::first();
                if ($pannel == null) {
                    \Log::error('getTestAccountDetails: no panel available');

                    return null;
                }

                $data = new TestAccount();
                $data->pannel_id = $pannel->id;
                $data->expire_day = 30;
                $data->volume = 0.5;
                $data->save();
            }

            $this->ensureTestProductCategory($data);

            return $data;
        } catch (\Throwable $th) {
            \Log::error('getTestAccountDetails: ' . $th->getMessage());

            return null;
        }
    }

    public function updateTestAccountDetails(Request $request)
    {
        try {
            $testAccount = TestAccount::first();
            if ($testAccount == null) {
                $testAccount = $this->getTestAccountDetails();
                if ($testAccount == null) {
                    return response()->json(false, 500);
                }
            }

            $testAccount->pannel_id = $request->pannel_id;
            $testAccount->expire_day = $request->expire_day;
            $testAccount->volume = $request->volume;
            $testAccount->save();

            $this->ensureTestProductCategory($testAccount);

            return $testAccount;
        } catch (\Throwable $th) {
            \Log::error('updateTestAccountDetails: ' . $th->getMessage());

            return response()->json(false, 500);
        }
    }

    /**
     * Ensure ProductCategory named «اکانت آزمایشی» exists and matches TestAccount settings.
     */
    public function ensureTestProductCategory(TestAccount $testAccount): ?ProductCategory
    {
        $category = ProductCategory::where('category_name', self::CATEGORY_NAME)->first();
        if ($category == null) {
            $category = new ProductCategory();
            $category->category_name = self::CATEGORY_NAME;
            $category->price = 0;
            $category->price_in_dollar = 0;
            $category->rechargable = false;
            $category->show_subscription_link = true;
            $category->show_pannel_link = true;
            $category->send_config_to_user = true;
        }

        $category->pannel_id = $testAccount->pannel_id;
        $category->expire_day = $testAccount->expire_day;
        $category->volume = $testAccount->volume;
        $category->is_active = true;
        $category->save();

        return $category;
    }

    public function getTestUsers()
    {
        try {
            $testUsers = UsedTestAccount::with('user')->get();

            return response()->json($testUsers);
        } catch (\Throwable $th) {
            \Log::error('getTestUsers: ' . $th->getMessage());

            return response()->json([], 500);
        }
    }

    public function deleteTestUser(Request $request)
    {
        try {
            $testUser = UsedTestAccount::find($request->id);
            if ($testUser) {
                $testUser->delete();

                return response()->json(true);
            }

            return response()->json(false, 404);
        } catch (\Throwable $th) {
            \Log::error('deleteTestUser: ' . $th->getMessage());

            return response()->json(false, 500);
        }
    }

    public function clearTestUsers()
    {
        try {
            UsedTestAccount::truncate();

            return response()->json(true);
        } catch (\Throwable $th) {
            \Log::error('clearTestUsers: ' . $th->getMessage());

            return response()->json(false, 500);
        }
    }
}
