<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetSummaryReportRequest;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;

class AssetSummaryReportController extends Controller
{
    public function __invoke(AssetSummaryReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $statuses = config('assets.statuses');
        $dateRange = [$validated['date_from'], $validated['date_to']];

        $summary = Asset::query()
            ->whereDate('purchase_date', '>=', $dateRange[0])
            ->whereDate('purchase_date', '<=', $dateRange[1])
            ->selectRaw('COUNT(*) as total_assets')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_use', [$statuses[1]])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as under_maintenance', [$statuses[2]])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as out_of_service', [$statuses[3]])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as available', [$statuses[0]])
            ->first();

        $categories = Asset::query()
            ->join('categories', 'categories.id', '=', 'assets.category_id')
            ->whereDate('purchase_date', '>=', $dateRange[0])
            ->whereDate('purchase_date', '<=', $dateRange[1])
            ->select('assets.category_id', 'categories.name as category_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_use', [$statuses[1]])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as under_maintenance', [$statuses[2]])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as out_of_service', [$statuses[3]])
            ->groupBy('assets.category_id', 'categories.name')
            ->orderBy('categories.name')
            ->get()
            ->map(fn (Asset $asset): array => [
                'category' => $asset->category_name,
                'total' => (int) $asset->total,
                'in_use' => (int) $asset->in_use,
                'under_maintenance' => (int) $asset->under_maintenance,
                'out_of_service' => (int) $asset->out_of_service,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'summary' => [
                    'total_assets' => (int) $summary->total_assets,
                    'in_use' => (int) $summary->in_use,
                    'under_maintenance' => (int) $summary->under_maintenance,
                    'out_of_service' => (int) $summary->out_of_service,
                    'available' => (int) $summary->available,
                ],
                'categories' => $categories,
            ],
        ]);
    }
}
