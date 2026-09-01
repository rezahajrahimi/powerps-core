<?php

namespace App\Http\Controllers;

use App\Models\GroupOperationJob;
use App\Models\Pannel;
use Illuminate\Http\Request;

class GroupOperationController extends Controller
{
    public function index(Request $request)
    {
        $jobs = GroupOperationJob::query()
            ->orderByDesc('id')
            ->paginate(10);

        $jobs->getCollection()->transform(function (GroupOperationJob $job) {
            return $this->formatJob($job);
        });

        return response()->json($jobs);
    }

    public function show($id)
    {
        $job = GroupOperationJob::find($id);
        if (! $job) {
            return response()->json(['status' => 'error', 'message' => 'یافت نشد'], 404);
        }

        return response()->json($this->formatJob($job));
    }

    private function formatJob(GroupOperationJob $job): array
    {
        $panel = Pannel::find($job->panel_id);

        return [
            'id' => $job->id,
            'action' => $job->action,
            'action_label' => $job->actionLabel(),
            'panel_id' => $job->panel_id,
            'panel_type' => $panel?->type,
            'panel_location' => $panel?->location,
            'status' => $job->status,
            'total_configs' => $job->total_configs,
            'processed_configs' => $job->processed_configs,
            'success_items' => $job->success_items ?? [],
            'failed_items' => $job->failed_items ?? [],
            'error_message' => $job->error_message,
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];
    }
}
