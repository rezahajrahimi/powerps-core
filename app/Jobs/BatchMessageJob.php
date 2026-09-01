<?php

namespace App\Jobs;

use App\Http\Controllers\HiddifyPannelController;
use App\Models\Product;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use App\Models\AdminMessage;
use App\Models\MarketingCampaign;


class BatchMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $action, $usersID, $message, $extra, $adminMessageId, $marketingCampaignId;



    /**
     * Create a new job instance.
     */
    public function __construct($action, $usersID, $message, $extra = [], $adminMessageId = null, $marketingCampaignId = null)
    {
        $this->action = $action;
        $this->usersID = $usersID;
        $this->message = $message;
        $this->extra = $extra;
        $this->adminMessageId = $adminMessageId;
        $this->marketingCampaignId = $marketingCampaignId;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $adminMessage = null;
        $marketingCampaign = null;
        if ($this->adminMessageId) {
            $adminMessage = AdminMessage::find($this->adminMessageId);
            if ($adminMessage) {
                $adminMessage->update(['status' => 'processing']);
            }
        }
        if ($this->marketingCampaignId) {
            $marketingCampaign = MarketingCampaign::find($this->marketingCampaignId);
            if ($marketingCampaign) {
                $marketingCampaign->update(['status' => 'processing']);
            }
        }

        try {
            Log::info("Handling batch message with action: {$this->action}");
            $telegramService = App::make(\App\Services\TelegramService::class);
            $ctaButtons = $this->extra['cta_buttons'] ?? [];

            $sentCount = 0;
            $sentIds = [];
            $failedIds = [];

            foreach ($this->usersID as $userId) {
                $response = null;
                $imagePath = $adminMessage?->image_path ?? ($this->extra['image_path'] ?? null);

                if ($imagePath) {
                    $localPath = public_path($imagePath);
                    Log::info("Attempting to send photo from: $localPath for user: $userId");
                    if (file_exists($localPath)) {
                        $options = [];
                        if ($ctaButtons !== []) {
                            $options['reply_markup'] = json_encode([
                                'inline_keyboard' => $telegramService->formatInlineKeyboardButtons($ctaButtons),
                            ]);
                        }
                        $response = $telegramService->sendPhotoFile($userId, $localPath, $this->message, $options);
                    } else {
                        Log::error("Photo file not found at: $localPath");
                        if ($ctaButtons !== []) {
                            $response = $telegramService->sendMessageWithInlineKeyboard($userId, $this->message, $ctaButtons);
                        } else {
                            $response = $telegramService->sendMessage($userId, $this->message, $this->extra);
                        }
                    }
                } elseif ($ctaButtons !== []) {
                    $response = $telegramService->sendMessageWithInlineKeyboard($userId, $this->message, $ctaButtons);
                } else {
                    $response = $telegramService->sendMessage($userId, $this->message, $this->extra);
                    if (!($response['ok'] ?? false)) {
                        $response = $telegramService->sendPlainMessage($userId, $this->message, $this->extra);
                    }
                }

                if (isset($response['ok']) && $response['ok']) {
                    $sentCount++;
                    $sentIds[] = $userId;
                    Log::info("Marked sent: $userId (sentCount=$sentCount)");
                } else {
                    $err = $response['description'] ?? 'Unknown error';
                    $failedIds[] = [
                        'user_id' => $userId,
                        'error' => $err
                    ];
                    Log::info("Marked failed: $userId (error=$err)");
                    Log::error("Failed to send message to $userId: " . json_encode($response));
                }

                if ($adminMessage && ($sentCount + count($failedIds)) % 5 == 0) {
                    Log::info("Partial update for AdminMessage {$adminMessage->id}: sent_count=$sentCount, sent_ids_count=" . count($sentIds) . ", failed_ids_count=" . count($failedIds));
                    $adminMessage->update([
                        'sent_users' => $sentCount,
                        'sent_ids' => $sentIds,
                        'failed_ids' => $failedIds,
                    ]);
                }

                // Small delay to avoid hitting Telegram rate limits
                usleep(50000); // 0.05 seconds
            }

            if ($adminMessage) {
                Log::info("Final update for AdminMessage {$adminMessage->id}: sent_count=$sentCount, sent_ids_count=" . count($sentIds) . ", failed_ids_count=" . count($failedIds));
                $adminMessage->update([
                    'status' => 'completed',
                    'sent_users' => $sentCount,
                    'sent_ids' => $sentIds,
                    'failed_ids' => $failedIds,
                ]);
            }

            if ($marketingCampaign) {
                $marketingCampaign->update([
                    'status' => 'completed',
                    'sent_users' => $sentCount,
                    'sent_ids' => $sentIds,
                    'failed_ids' => $failedIds,
                ]);
            }

            Log::info("Batch message sent successfully to users: " . implode(', ', $this->usersID));

        } catch (\Exception $e) {
            if ($adminMessage) {
                $adminMessage->update(['status' => 'failed']);
            }
            if ($marketingCampaign) {
                $marketingCampaign->update(['status' => 'failed']);
            }
            Log::error("Error handling batch message: " . $e->getMessage());
        }
    }
}
