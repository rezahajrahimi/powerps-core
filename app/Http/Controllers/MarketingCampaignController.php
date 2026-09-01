<?php

namespace App\Http\Controllers;

use App\Jobs\BatchMessageJob;
use App\Models\MarketingCampaign;
use App\Services\LicenseFeatureService;
use App\Services\MarketingSegmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MarketingCampaignController extends Controller
{
    public function __construct(private LicenseFeatureService $license) {}

    public function index()
    {
        if (! $this->license->isGold()) {
            return $this->license->goldRequiredResponse();
        }

        return response()->json(MarketingCampaign::orderByDesc('id')->get());
    }

    public function previewRecipients(Request $request, MarketingSegmentService $segmentService)
    {
        if (! $this->license->isGold()) {
            return $this->license->goldRequiredResponse();
        }

        $request->validate([
            'segment_type' => 'required|string',
            'segment_params' => 'nullable|array',
        ]);

        $recipients = $segmentService->resolveRecipients(
            $request->segment_type,
            $request->segment_params
        );

        return response()->json([
            'count' => count($recipients),
            'sample' => array_slice($recipients, 0, 10),
        ]);
    }

    public function store(Request $request, MarketingSegmentService $segmentService)
    {
        if (! $this->license->isGold()) {
            return $this->license->goldRequiredResponse();
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'segment_type' => 'required|string',
            'segment_params' => 'nullable|array',
            'message' => 'required|string',
            'cta_type' => 'nullable|string',
            'cta_payload' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $directory = public_path('storage/admin_messages');
            if (! file_exists($directory)) {
                mkdir($directory, 0777, true);
            }
            $file->move($directory, $filename);
            $imagePath = 'storage/admin_messages/' . $filename;
        }

        $recipients = $segmentService->resolveRecipients(
            $data['segment_type'],
            $data['segment_params'] ?? []
        );

        if ($recipients === []) {
            return response()->json(['message' => 'هیچ گیرنده‌ای برای این سگمنت یافت نشد.'], 422);
        }

        $scheduledAt = isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;
        $campaign = MarketingCampaign::create([
            'name' => $data['name'],
            'segment_type' => $data['segment_type'],
            'segment_params' => $data['segment_params'] ?? null,
            'message' => $data['message'],
            'image_path' => $imagePath,
            'cta_type' => $data['cta_type'] ?? null,
            'cta_payload' => $data['cta_payload'] ?? null,
            'scheduled_at' => $scheduledAt,
            'status' => $scheduledAt && $scheduledAt->isFuture() ? 'scheduled' : 'processing',
            'total_users' => count($recipients),
            'recipient_ids' => $recipients,
        ]);

        $ctaButtons = $segmentService->buildCtaButtons($campaign->cta_type, $campaign->cta_payload);
        $job = new BatchMessageJob(
            'marketing_campaign',
            $recipients,
            $data['message'],
            ['cta_buttons' => $ctaButtons, 'image_path' => $imagePath],
            null,
            $campaign->id
        );

        if ($scheduledAt && $scheduledAt->isFuture()) {
            $job->delay($scheduledAt);
        }

        dispatch($job);

        return response()->json($campaign, 201);
    }

    public function destroy(int $id)
    {
        if (! $this->license->isGold()) {
            return $this->license->goldRequiredResponse();
        }

        MarketingCampaign::findOrFail($id)->delete();

        return response()->json(true);
    }
}
