<?php

namespace App\Jobs;

use App\Models\GroupOperationJob;
use App\Models\Pannel;
use App\Services\BatchPanelOperationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BatchSubscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $action, $listOfConfigs, $panelID, $extra, $jobRecordId;

    public function __construct($action, $listOfConfigs, $panelID, $extra = [], $jobRecordId = null)
    {
        $this->action = $action;
        $this->listOfConfigs = $listOfConfigs;
        $this->panelID = $panelID;
        $this->extra = $extra;
        $this->jobRecordId = $jobRecordId;
    }

    public function handle()
    {
        $success = true;
        $message = '';
        $adminId = env(key: 'TELEGRAM_ADMIN_ID');
        $telegramService = app(\App\Services\TelegramService::class);
        $operationService = app(BatchPanelOperationService::class);
        $jobRecord = $this->jobRecordId
            ? GroupOperationJob::find($this->jobRecordId)
            : null;
        $successItems = [];
        $failedItems = [];

        try {
            $action = $this->action;
            $listOfConfigs = $this->listOfConfigs;
            $panelID = $this->panelID;
            $extra = $this->extra;
            $panel = Pannel::find($panelID);

            if ($jobRecord) {
                $jobRecord->update(['status' => 'processing']);
            }

            if (! isset($listOfConfigs)) {
                $success = false;
                $message = 'لیست پیکربندی‌ها ارسال نشده است.';

                return;
            }

            if (! $operationService->supportsPanel($panel)) {
                $success = false;
                $message = 'این نوع پنل از عملیات گروهی پشتیبانی نمی‌شود.';

                return;
            }

            $actionLabels = GroupOperationJob::actionLabels();

            if (! array_key_exists($action, $actionLabels)) {
                $success = false;
                $message = 'عملیات نامعتبر است.';

                return;
            }

            if (in_array($action, ['inc_days', 'dec_days', 'modify_days'], true) && ! isset($extra['days'])) {
                $success = false;
                $message = 'تعداد روزها ارسال نشده است.';

                return;
            }

            if (in_array($action, ['inc_vol', 'dec_vol', 'modify_vol'], true) && ! isset($extra['vol'])) {
                $success = false;
                $message = 'مقدار حجم ارسال نشده است.';

                return;
            }

            $telegramService->sendMessage($adminId, 'عملیات ' . $actionLabels[$action] . ' شروع شد.');

            $processed = 0;
            foreach ($listOfConfigs as $config) {
                $aa = is_array($config) ? $config : json_decode($config, true);
                $config = (array) $aa;
                $configName = $config['name'] ?? ($config['uuid'] ?? 'نامشخص');

                $result = $operationService->execute($action, $config, $panel, $extra);
                $processed++;

                if (! $result) {
                    $success = false;
                    $message = 'خطا در اجرای عملیات روی پیکربندی: ' . $configName;
                    $failedItems[] = [
                        'name' => $configName,
                        'error' => $message,
                    ];
                    $telegramService->sendMessage($adminId, $message);
                } else {
                    $successItems[] = ['name' => $configName];
                    $telegramService->sendMessage($adminId, 'عملیات ' . $actionLabels[$action] . ' برای ' . $configName . ' با موفقیت انجام شد.');
                }

                if ($jobRecord) {
                    $jobRecord->update([
                        'processed_configs' => $processed,
                        'success_items' => $successItems,
                        'failed_items' => $failedItems,
                    ]);
                }

                if (! $result) {
                    break;
                }
            }
        } catch (\Throwable $th) {
            $success = false;
            $message = 'خطا در اجرای عملیات: ' . $th->getMessage();
            Log::error($message);
        } finally {
            if ($jobRecord) {
                $jobRecord->update([
                    'status' => $success ? 'completed' : 'failed',
                    'error_message' => $success ? null : $message,
                    'success_items' => $successItems,
                    'failed_items' => $failedItems,
                ]);
            }
        }

        try {
            if ($success) {
                $telegramService->sendMessage($adminId, 'عملیات با موفقیت به اتمام رسید.');
            } elseif ($message !== '') {
                $telegramService->sendMessage($adminId, 'خطا: ' . $message);
            }
        } catch (\Throwable $th) {
            Log::error('خطا در ارسال پیام نتیجه به مدیر: ' . $th->getMessage());
        }
    }
}
