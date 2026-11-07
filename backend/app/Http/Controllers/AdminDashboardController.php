<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $statuses = config('assets.statuses');
        $summary = Asset::query()
            ->selectRaw('COUNT(*) as total_assets')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_use', [$statuses[1]])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as under_maintenance', [$statuses[2]])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as out_of_service', [$statuses[3]])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as available', [$statuses[0]])
            ->first();

        $locations = Location::query()
            ->withCount('assets')
            ->whereHas('assets')
            ->orderByDesc('assets_count')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn (Location $location): array => [
                'id' => $location->id,
                'name' => $location->name,
                'total' => (int) $location->assets_count,
            ])
            ->values();

        $activity = ActivityLog::query()
            ->with([
                'user:id,name',
                'asset:id,asset_tag,asset_name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'description' => $log->description,
                'asset_tag' => $log->asset?->asset_tag,
                'asset_name' => $log->asset?->asset_name,
                'user_name' => $log->user->name,
                'created_at' => $log->created_at,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_assets' => (int) $summary->total_assets,
                    'in_use' => (int) $summary->in_use,
                    'under_maintenance' => (int) $summary->under_maintenance,
                    'out_of_service' => (int) $summary->out_of_service,
                    'available' => (int) $summary->available,
                ],
                'locations' => $locations,
                'activity' => $activity,
            ],
        ]);
    }
}
