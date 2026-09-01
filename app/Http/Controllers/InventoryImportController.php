<?php

namespace App\Http\Controllers;

use App\Models\Pannel;
use App\Services\InventoryImportService;
use App\Services\InventoryStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryImportController extends Controller
{
    public function import(Request $request, InventoryImportService $importService)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
            'pannel_id' => 'required|integer|exists:pannels,id',
        ]);

        try {
            $result = $importService->import($request->file('file'), (int) $request->pannel_id);

            return response()->json([
                'success' => true,
                'message' => 'فایل با موفقیت پردازش شد.',
                'data' => $result,
            ]);
        } catch (\Throwable $th) {
            \Log::error('Inventory import failed: ' . $th->getMessage());

            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 422);
        }
    }

    public function downloadTemplate(InventoryImportService $importService): StreamedResponse
    {
        $content = $importService->buildTemplateCsv();

        return Response::streamDownload(
            static function () use ($content) {
                echo $content;
            },
            'inventory-import-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function getInventoryPanels()
    {
        $panels = Pannel::query()
            ->where('type', Pannel::TYPE_INVENTORY)
            ->orderBy('id')
            ->get(['id', 'location', 'type', 'capacity']);

        return response()->json($panels);
    }

    public function getInventoryStock(Request $request, InventoryStockService $stockService)
    {
        $request->validate([
            'pannel_id' => 'required|integer|exists:pannels,id',
            'status' => 'nullable|in:all,active,sold',
            'sort' => 'nullable|in:created_at_desc,created_at_asc,updated_at_desc,updated_at_asc,category_asc,category_desc,price_asc,price_desc,status',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        try {
            $panelId = (int) $request->pannel_id;
            $status = $request->input('status', 'all');
            $sort = $request->input('sort', 'created_at_desc');
            $search = $request->input('search');
            $perPage = (int) $request->input('per_page', 20);

            $summary = $stockService->getSummary($panelId);
            $paginator = $stockService->listStock($panelId, $status, $sort, $search, $perPage);

            return response()->json([
                'summary' => $summary,
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 422);
        }
    }

    public function updateInventoryStockItem(Request $request, InventoryStockService $stockService)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'pannel_id' => 'required|integer|exists:pannels,id',
            'configs' => 'required|string',
            'subscription_link' => 'nullable|string',
            'panel_link' => 'nullable|string',
        ]);

        try {
            $product = $stockService->updateStockItem(
                (int) $request->product_id,
                (int) $request->pannel_id,
                (string) $request->configs,
                (string) ($request->subscription_link ?? ''),
                (string) ($request->panel_link ?? ''),
            );

            return response()->json([
                'success' => true,
                'message' => 'کانفیگ با موفقیت ویرایش شد.',
                'data' => $product,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 422);
        }
    }

    public function deleteInventoryStockItem(Request $request, InventoryStockService $stockService, int $id)
    {
        $request->validate([
            'pannel_id' => 'required|integer|exists:pannels,id',
        ]);

        try {
            $stockService->deleteStockItem($id, (int) $request->pannel_id);

            return response()->json([
                'success' => true,
                'message' => 'کانفیگ با موفقیت حذف شد.',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 422);
        }
    }
}
