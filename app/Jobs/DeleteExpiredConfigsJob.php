<?php

namespace App\Jobs;

use App\Http\Controllers\HiddifyPannelController;
use App\Http\Controllers\MarzbanPannelController;
use App\Http\Controllers\SanaeiPannelController;
use App\Models\GroupOperationJob;
use App\Models\Pannel;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteExpiredConfigsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /** @var list<array<string, mixed>> */
    protected array $items;

    protected ?int $jobRecordId;

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(array $items, ?int $jobRecordId = null)
    {
        $this->items = $items;
        $this->jobRecordId = $jobRecordId;
    }

    public function handle(): void
    {
        $jobRecord = $this->jobRecordId
            ? GroupOperationJob::find($this->jobRecordId)
            : null;

        $successItems = [];
        $failedItems = [];
        $processed = 0;

        if ($jobRecord) {
            $jobRecord->update(['status' => 'processing']);
        }

        try {
            foreach ($this->items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $panelId = (int) ($item['panel_id'] ?? 0);
                $uuid = (string) ($item['uuid'] ?? '');
                $name = (string) (($item['remark'] ?? '') !== '' ? $item['remark'] : ($uuid !== '' ? $uuid : 'نامشخص'));

                try {
                    if ($productId > 0) {
                        Product::where('id', $productId)->delete();
                    }

                    $panel = Pannel::find($panelId);
                    if ($panel != null && $uuid !== '') {
                        $this->deletePanelUser($panel, $uuid);
                    }

                    $successItems[] = ['name' => $name];
                } catch (\Throwable $th) {
                    Log::error('DeleteExpiredConfigsJob item failed', [
                        'item' => $item,
                        'error' => $th->getMessage(),
                    ]);
                    $failedItems[] = [
                        'name' => $name,
                        'error' => $th->getMessage(),
                    ];
                }

                $processed++;
                if ($jobRecord) {
                    $jobRecord->update([
                        'processed_configs' => $processed,
                        'success_items' => $successItems,
                        'failed_items' => $failedItems,
                    ]);
                }
            }

            if ($jobRecord) {
                $allFailed = $successItems === [] && $failedItems !== [];
                $jobRecord->update([
                    'status' => $allFailed ? 'failed' : 'completed',
                    'error_message' => $failedItems === []
                        ? null
                        : count($failedItems) . ' مورد با خطا حذف نشد.',
                    'success_items' => $successItems,
                    'failed_items' => $failedItems,
                ]);
            }
        } catch (\Throwable $th) {
            Log::error('DeleteExpiredConfigsJob: ' . $th->getMessage());
            if ($jobRecord) {
                $jobRecord->update([
                    'status' => 'failed',
                    'error_message' => $th->getMessage(),
                    'processed_configs' => $processed,
                    'success_items' => $successItems,
                    'failed_items' => $failedItems,
                ]);
            }
        }
    }

    private function deletePanelUser(Pannel $panel, string $uuid): void
    {
        if ($panel->type === 'hiddify') {
            (new HiddifyPannelController())->deleteUserOfHiddifyPanel($panel->id, $uuid);
        } elseif ($panel->type === 'sanaei') {
            (new SanaeiPannelController())->deleteUser($panel, $uuid);
        } elseif ($panel->isMarzbanCompatible()) {
            MarzbanPannelController::resolve($panel)->deleteUser($panel, $uuid);
        }
    }
}
