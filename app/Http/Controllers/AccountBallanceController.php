<?php
namespace App\Http\Controllers;

use App\Http\Controllers\AgentPermissonController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\TransactionSettingController;
use App\Models\AccountBallance;
use App\Models\AgentPermisson;
use App\Models\BotUser;
use App\Models\User;
use Illuminate\Http\Request;

class AccountBallanceController extends Controller
{
    private function canAgentChargeMinusBalance(?AgentPermisson $agentPr, float $currentBalance, float $chargeAmount): bool
    {
        if ($agentPr === null) {
            return false;
        }

        if ($agentPr->minus_ballance !== 1 && $agentPr->minus_ballance !== true) {
            return false;
        }

        $limit = $agentPr->minus_ballance_limit;
        if ($limit === null || (float) $limit <= 0) {
            return true;
        }

        return ($currentBalance - $chargeAmount) >= (-1 * (float) $limit);
    }

    public function checkUserHasBalance($userID, $price, $parice_in_dollar)
    {
        try {
            // for test account
            if ($price == 0 && $parice_in_dollar == 0) {
                return true;
            }
            // get user
            $user = User::where('account_id', $userID)->first();
            if ($user == null) {
                return false;
            }
            // check user is admin
            if ($user->role == 'admin') {
                return true;
            }
            // check agent
            if ($user->role == 'agent') {
                $agentPremissionCntrl = new AgentPermissonController();
                $agentPr = $agentPremissionCntrl->getUserPremissionByAgentID($user->id);
                if ($agentPr != null) {
                    if ($agentPr->minus_ballance === 1 || $agentPr->minus_ballance === true) {
                        $data = AccountBallance::where('account_id', $userID)->first();
                        $currentBalance = $data ? (float) $data->ballance : 0;
                        $currentDollarBalance = $data ? (float) $data->account_ballance_in_dollar : 0;

                        if ($currentBalance >= $price) {
                            return true;
                        }

                        if ($currentDollarBalance >= $parice_in_dollar) {
                            if ($this->checkDollarPay() == true || $this->checkDollarPay() == 1 && $parice_in_dollar > 0) {
                                return true;
                            }
                        }

                        return $this->canAgentChargeMinusBalance($agentPr, $currentBalance, (float) $price);
                    }
                }
            }

            // common product categorey check
            $data = AccountBallance::where('account_id', $userID)->first();

            if ($data != null) {
                if ($data->ballance >= $price) {
                    return true;
                } elseif ($data->account_ballance_in_dollar >= $parice_in_dollar) {
                    if ($this->checkDollarPay() == true || $this->checkDollarPay() == 1 && $parice_in_dollar > 0) {
                        return true;
                    }
                    return false;
                }
                return false;
            } else {
                $newAcc = new AccountBallance();
                $newAcc->account_id = $userID;
                $newAcc->ballance = 0;
                $newAcc->account_ballance_in_dollar = 0;
                $newAcc->save();
                return false;
            }
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return false;
        }
    }
    public function getUserAccuntBalance($userID)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            return $data->ballance;
        } else {
            $newAcc = new AccountBallance();
            $newAcc->account_id = $userID;
            $newAcc->ballance = 0;
            $newAcc->account_ballance_in_dollar = 0;
            $newAcc->save();

            return 0;
        }
    }
    public function getUserAccuntBalanceInDollar($userID)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            return $data->account_ballance_in_dollar;
        } else {
            $newAcc = new AccountBallance();
            $newAcc->account_id = $userID;
            $newAcc->ballance = 0;
            $newAcc->account_ballance_in_dollar = 0;
            $newAcc->save();

            return 0;
        }
    }
    public function incUserAccuntBalance($userID, $ballance)
    {
        try {
            $data = AccountBallance::where('account_id', $userID)->first();
            if ($data != null) {
                $data->ballance += $ballance;

                $data->update();
                return true;
            } else {
                $newAcc = new AccountBallance();
                $newAcc->account_id = $userID;
                $newAcc->ballance = $ballance;
                $newAcc->account_ballance_in_dollar = 0;
                $newAcc->save();

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return false;
        }
    }
    public function increaseUserAccuntBalanceByUserID(Request $request)
    {
        try {
            $user = BotUser::where('id', $request->userID)->first();

            if ($user != null) {
                $userAccountID = $user->account_id;
                $ballance = $request->ballance;
                $type = $request->type;

                if ($type == 'toman') {
                    $this->incUserAccuntBalance($userAccountID, $ballance);

                    $logCtrl = new LogController();
                    $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' تومان افزایش یافت', $userAccountID, '', 'edit');
                    return $this->getUserAccuntBalance($userAccountID);
                } else {
                    $this->incUserAccuntBalanceInDollar($userAccountID, $ballance);
                    $logCtrl = new LogController();

                    $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' دلار افزایش یافت', $userAccountID, '', 'edit');
                    return $this->getUserAccuntBalanceInDollar($userAccountID);
                }
            }
            return response()->json(null, 404);
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return response()->json(null, 500);
        }
    }
    public function decreaseUserAccuntBalanceByUserID(Request $request)
    {
        try {
            $user = BotUser::where('id', $request->userID)->first();
            $is_admin = false;
            $is_agent = false;
            $minus_ballance_permission = false;
            $agent_permission = null;
            $minus_ballance_permission = $request->is_request_by_admin ?? false;
            $isReqByAdmin = $request->is_request_by_admin ?? false;
            if ($user == null) {
                $user = BotUser::where('account_id', $request->userID)->first();
                if ($user == null) {
                    return false;
                }
            }
            $user_role = User::where('account_id', $user->account_id)->first();
            if ($user_role != null) {
                if ($user_role->role == 'admin') {
                    $is_admin = true;
                }
                if ($user_role->role == 'agent') {
                    $is_agent = true;
                    $agent_permission = AgentPermisson::where('user_id', $user_role->id)->first();
                    if (isset($agent_permission)) {
                        if ($agent_permission->minus_ballance == 1 || $agent_permission->minus_ballance == true) {
                            $minus_ballance_permission = true;
                        }
                    }
                }
            } else {
                \Log::info("user_role $user_role->id");
                return false;
            }

            $userAccountID = $user->account_id;
            $ballance = $request->ballance;

            $type = $request->type;
            $accBallance = AccountBallance::where('account_id', $userAccountID)->first();
            if (!isset($accBallance)) {
                $newAcc = new AccountBallance();
                $newAcc->account_id = $request->userID;
                $newAcc->ballance = 0;
                $newAcc->account_ballance_in_dollar = 0;
                $newAcc->save();
                if ($is_admin) {
                    return true;
                }
                return false;
            }

            if ($type == 'toman') {
                if ($ballance <= $accBallance->ballance) {
                    $accBallance->ballance -= $ballance;
                    $accBallance->update();
                    $logCtrl = new LogController();
                    $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' تومان کاهش یافت', $userAccountID, '', 'edit');
                    $res = $accBallance->ballance;
                    if ($res == 0) {
                        return true;
                    }
                    return $res;
                } else {
                    // get auth user role for checking this requerst sent by admin
                    if ($is_admin || $minus_ballance_permission || $isReqByAdmin) {
                        if ($minus_ballance_permission && ! $is_admin && ! $isReqByAdmin) {
                            if (! $this->canAgentChargeMinusBalance($agent_permission ?? null, (float) $accBallance->ballance, (float) $ballance)) {
                                return false;
                            }
                        }
                        $accBallance->ballance -= $ballance;
                        $accBallance->update();
                        $logCtrl = new LogController();
                        $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' تومان کاهش یافت', $userAccountID, '', 'edit');
                        $res = $accBallance->ballance;
                        if ($res == 0) {
                            return true;
                        }
                        return $res;
                    }
                    \Log::info("message 555555555555");

                }
            } else {
                \Log::info("type is dollar");
                \Log::info("accBallance->account_ballance_in_dollar $accBallance->account_ballance_in_dollar");
                \Log::info("ballance $ballance");
                \Log::info("is_admin $is_admin");
                \Log::info("minus_ballance_permission $minus_ballance_permission");
                \Log::info("isReqByAdmin $isReqByAdmin");
                $ballance = doubleval($ballance);
                $currentUserDollarBalance = doubleval($accBallance->account_ballance_in_dollar);
                \Log::info("currentUserDollarBalance $currentUserDollarBalance");
                \Log::info("ballance $ballance");
                if ($ballance <= $currentUserDollarBalance) {
                    \Log::info("ballance is less than currentUserDollarBalance");
                    $accBallance->account_ballance_in_dollar -= doubleval($ballance);
                    $accBallance->update();
                    $logCtrl = new LogController();
                    $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' دلار کاهش یافت', $userAccountID, '', 'edit');
                    $res = $accBallance->account_ballance_in_dollar;
                    if ($res == 0) {
                        return true;
                    }
                    return $res;
                } else {
                    if ($is_admin || $minus_ballance_permission) {
                        if ($minus_ballance_permission && ! $is_admin) {
                            if (! $this->canAgentChargeMinusBalance($agent_permission ?? null, $currentUserDollarBalance, $ballance)) {
                                return false;
                            }
                        }
                        $accBallance->account_ballance_in_dollar -= doubleval($ballance);
                        $accBallance->update();
                        $logCtrl = new LogController();
                        $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' دلار کاهش یافت', $userAccountID, '', 'edit');
                        $res = $accBallance->account_ballance_in_dollar;
                        if ($res == 0) {
                            return true;
                        }
                        return $res;
                    }
                    \Log::info("message 233333333333");

                    return false;
                }
            }
            \Log::info("message 4444444444444444");

            return false;
        } catch (\Throwable $th) {
            \Log::info("message 2222222222222222222");

            \Log::info("message $th");
            return response()->json(null, 500);
        }
    }
    public function incUserAccuntBalanceInDollar($userID, $ballance)
    {
        try {
            $data = AccountBallance::where('account_id', $userID)->first();
            if ($data != null) {
                $data->account_ballance_in_dollar += $ballance;

                $data->update();
                return true;
            } else {
                $newAcc = new AccountBallance();
                $newAcc->account_id = $userID;
                $newAcc->account_ballance_in_dollar = $ballance;
                $newAcc->ballance = 0;
                $newAcc->save();

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return false;
        }
    }
    public function decUserAccuntBalance($userID, $ballance, $parice_in_dollar)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            if ($data->ballance >= $ballance) {
                $data->ballance -= $ballance;
                $data->update();
                return true;
            } elseif ($data->account_ballance_in_dollar >= $parice_in_dollar) {
                $data->account_ballance_in_dollar -= doubleval($parice_in_dollar);
                $data->update();
                return true;
            } else {
                $user = User::where('account_id', $userID)->first();
                if ($user != null && $user->role == 'agent') {
                    $agentPr = AgentPermisson::where('user_id', $user->id)->first();
                    if ($agentPr != null && ($agentPr->minus_ballance == 1 || $agentPr->minus_ballance == true)) {
                        if ($this->canAgentChargeMinusBalance($agentPr, (float) $data->ballance, (float) $ballance)) {
                            $data->ballance -= $ballance;
                            $data->update();
                            return true;
                        }

                        return false;
                    }
                }
                return false;
            }
        } else {
            return false;
        }
    }
    public function setNewAccountBallance(Request $request)
    {
        try {
            $data = AccountBallance::where('account_id', $request->userID)->first();
            $logCtrl = new LogController();

            if ($data != null) {
                $data->ballance = $request->ballance;

                $data->update();

                $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر بصورت دستی به ' . $request->ballance . ' تومان تغییر کرد', $request->userID, '', 'edit');

                return true;
            } else {
                $newAcc = new AccountBallance();
                $newAcc->account_id = $request->userID;
                $newAcc->ballance = $request->ballance;
                $newAcc->save();
                $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر بصورت دستی به ' . $request->ballance . ' تومان تغییر کرد', $request->userID, '', 'edit');

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function setNewDollarAccountBallance(Request $request)
    {
        try {
            $data = AccountBallance::where('account_id', $request->userID)->first();
            $logCtrl = new LogController();

            if ($data != null) {
                $data->account_ballance_in_dollar = $request->ballance;

                $data->update();

                $logCtrl = new LogController();
                $logCtrl->addNewLog('ballance', 'میزان موجودی دلاری کاربر بصورت دستی به ' . $request->ballance . ' دلار تغییر کرد', $request->userID, '', 'edit');

                return true;
            } else {
                $newAcc = new AccountBallance();
                $newAcc->account_id = $request->userID;
                $newAcc->account_ballance_in_dollar = $request->ballance;
                $newAcc->save();
                $logCtrl->addNewLog('ballance', 'میزان موجودی دلاری کاربر بصورت دستی به ' . $request->ballance . ' دلار تغییر کرد', $request->userID, '', 'edit');

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    /// Agent Functions
    public function getLoggedUserBallancce($account_id = null)
    {
        try {
            $userId = null;
            if ($account_id == null) {
                $userId = auth('sanctum')->user()->account_id;
            } else {
                $userId = $account_id;
            }
            $data = AccountBallance::where('account_id', $userId)->first();
            if (!$data) {
                $newAcc = new AccountBallance();
                $newAcc->account_id = $userId;
                $newAcc->account_ballance_in_dollar = 0;
                $newAcc->ballance = 0;
                $newAcc->save();
                return $newAcc;
            }
            return $data;
        } catch (\Throwable $th) {
            \Log::info("$th");
            return response()->json(false, 500);
        }
    }
    /// check  dollarPay is valid or not
    public function checkDollarPay()
    {
        $paymnetSettingCntrl = new PaymentSettingController();
        $dollarTransaction = $paymnetSettingCntrl->getPaymentSettingStatusByKey('usd_transaction');

        if ($dollarTransaction == 1 || $dollarTransaction == true) {
            \Log::info("dollar transaction is true");
            return true;
        } else {
            \Log::info("dollar transaction is false");
            return false;
        }


    }
}
