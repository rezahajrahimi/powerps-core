<?php

namespace App\Http\Controllers;

use App\Models\BlockedUser;
use Illuminate\Http\Request;

class BlockedUserController extends Controller
{
    public function __construct()
    {
        $this->blockedUser = new BlockedUser();
    }
    public function getBlockedUserList()
    {
        return $this->blockedUser->getBlockedUserList();
    }
    public function addBlockedUser(Request $request)
    {
        // check if not exist add new one
        $blockedUser = $this->blockedUser->where('account_id', $request->accountId)->first();
        if (!$blockedUser) {
            $this->blockedUser->addBlockedUser($request->accountId, $request->reason);
        }
    }
    public function removeBlockedUser(Request $request)
    {
        $blockedUser = $this->blockedUser->where('account_id', $request->accountId)->first();
        if ($blockedUser) {
            $this->blockedUser->removeBlockedUser($request->accountId);
        }
    }
    public function getBlockedUser($account_id)
    {
        return $this->blockedUser->getBlockedUser($account_id);
    }
    public function isBlocked($account_id)
    {
        return $this->blockedUser->isBlocked($account_id);
    }
    public function getBlockedUserCount()
    {
        return $this->blockedUser->getBlockedUserCount();
    }
}
