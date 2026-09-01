<?php

namespace App\Jobs;

use App\Http\Controllers\CustomTextController;
use App\Http\Controllers\HiddifyPannelController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\SanaeiPannelController;
use App\Http\Controllers\SubscriptionProcessController;
use App\Models\BotUser;
use App\Models\Pannel;
use App\Models\Product;
use App\Models\UserState;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Verta;

class ProcessRemark implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chatId;
    protected $productId;
    protected $newName;

    /**
     * Create a new job instance.
     */
    public function __construct($chatId, $productId, $newName)
    {
        $this->chatId = $chatId;
        $this->productId = $productId;
        $this->newName = $newName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $customTextCtrl = new CustomTextController();
        $telegramService = new TelegramService();
        $logCtrl = new LogController();

        // Fetch user for logging
        $botUser = BotUser::where('account_id', $this->chatId)->first();
        $username = $botUser ? $botUser->username : 'Unknown';

        try {
            $product = Product::where('id', $this->productId)
                ->with('product_category_and_panel')
                ->first();

            if ($product == null) {
                $this->clearAwaitingReply($this->chatId, $customTextCtrl->getText('error.server_error'), $telegramService);
                return;
            }

            $pannel = Pannel::find($product->product_category_and_panel->pannel_id);
            if ($pannel === null || ! $pannel->supportsRemarkRename()) {
                $this->clearAwaitingReply(
                    $this->chatId,
                    'تغییر نام بسته فقط برای پنل‌های Hiddify و Sanaei امکان‌پذیر است.',
                    $telegramService
                );

                return;
            }

            if ($pannel->type == 'hiddify') {
                $hiddifcCntrl = new HiddifyPannelController();
                $uuid = $hiddifcCntrl->extractUUID($product->subscription_link);
                $req = new Request();
                $req->pannelID = $pannel->id;
                $req->name = $this->newName;
                $req->uuid = $uuid;
                $req->comment = "تغییر نام بسته در " . Verta::now();

                $updateRemark = $hiddifcCntrl->updateUserNameOfHiddifyPanelApi($req);
                if ($updateRemark !== false) {
                    $product->remark = $this->newName;
                    $product->update();

                    $logCtrl->addNewLog('subscription', 'تغییر نام بسته با موفقیت انجام شد.', $this->chatId, $username, 'show');
                    $this->clearAwaitingReply($this->chatId, $customTextCtrl->getText('action.remark.success'), $telegramService);
                    return;
                }
            } elseif ($pannel->type == 'sanaei') {
                \Log::info("ProcessRemark: Sanaei panel detected for product " . $product->id);
                $sn = new SanaeiPannelController();
                $configs = json_decode($product->configs ?? '{}', true);
                $uuid = $configs['uuid'] ?? null;

                if ($uuid) {
                    \Log::info("ProcessRemark: Updating Sanaei client $uuid to new email: " . $this->newName);
                    $ok = $sn->updateClientEmail($pannel->id, $uuid, $this->newName);
                    if ($ok) {
                        \Log::info("ProcessRemark: Sanaei panel update success. Updating database remark.");
                        $product->remark = $this->newName;
                        $product->update();

                        $logCtrl->addNewLog('subscription', 'تغییر نام بسته با موفقیت انجام شد.', $this->chatId, $username, 'show');
                        $this->clearAwaitingReply($this->chatId, $customTextCtrl->getText('action.remark.success'), $telegramService);
                        return;
                    } else {
                        \Log::error("ProcessRemark: Sanaei panel update failed for client $uuid");
                    }
                } else {
                    \Log::warning("ProcessRemark: No UUID found in configs for product " . $product->id);
                }
            }

            $this->clearAwaitingReply($this->chatId, $customTextCtrl->getText('error.server_error'), $telegramService);

        } catch (\Throwable $th) {
            \Log::error("خطا در تغییر نام بسته (Job): " . $th->getMessage());
            $this->clearAwaitingReply($this->chatId, $customTextCtrl->getText('error.server_error'), $telegramService);
        }
    }

    private function clearAwaitingReply(string $chatId, string|array $text, TelegramService $telegramService): void
    {
        try {
            $generalCntrl = new \App\Http\Controllers\GeneralController();
            if (is_array($text)) {
                $text = $telegramService->formatText($text);
            }
            Cache::forget("awaiting_reply_{$chatId}");

            // delete last user state where chat_id == $chatId
            $user_state = UserState::where('chat_id', $chatId)->latest()->first();
            if ($user_state != null) {
                $user_state->delete();
            }

            $generalCntrl->return_main_menu_items($chatId, $text);
        } catch (\Throwable $th) {
            \Log::error("خطا در پاک کردن حالت کاربر (Job): " . $th->getMessage());
        }
    }
}
