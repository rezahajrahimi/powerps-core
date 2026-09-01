<?php

namespace App\Http\Controllers;

use App\Models\UsedTestAccount;
use Illuminate\Http\Request;

class UsedTestAccountController extends Controller
{
    public function newTestAccount($account_id, $test_account_id)
    {
        if (!$this->checkUserHasTestAccount($account_id, $test_account_id)) {
            $this->markTestAccountUsed($account_id, $test_account_id);
            return false;
        }
        return true;
    }

    public function markTestAccountUsed($account_id, $test_account_id): void
    {
        if ($this->checkUserHasTestAccount($account_id, $test_account_id)) {
            return;
        }

        $testAccount = new UsedTestAccount();
        $testAccount->account_id = $account_id;
        $testAccount->test_account_id = $test_account_id;
        $testAccount->save();
    }

    public function checkUserHasTestAccount($account_id, $test_account_id)
    {
        if ($this->getCountOfUsePerUser($test_account_id, $account_id) >= 1 && $this->getCountOfUsePerUser($test_account_id, $account_id) <= 3) {
            return true;
        }
        return false;
    }
    public function getCountOfUsePerUser($test_account_id, $account_id)
    {
        $data = UsedTestAccount::where('test_account_id', $test_account_id)->where('account_id', $account_id)->count();
        return $data;
    }
}
