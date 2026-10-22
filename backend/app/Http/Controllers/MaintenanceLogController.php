<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexMaintenanceLogRequest;
use App\Http\Requests\StoreMaintenanceLogRequest;
use App\Http\Requests\UpdateMaintenanceLogRequest;
use App\Http\Resources\MaintenanceLogResource;
use App\Models\Asset;
use App\Models\MaintenanceLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MaintenanceLogController extends Controller
{
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => config('assets.maintenance_types'),
        ]);
    }

    public function index(IndexMaintenanceLogRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $logs = $this->filteredQuery($filters)
            ->with('asset')
            ->orderBy('date', $filters['sort_direction'] ?? 'desc')
            ->orderBy('id', $filters['sort_direction'] ?? 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => MaintenanceLogResource::collection($logs->getCollection())->resolve(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function assetIndex(IndexMaintenanceLogRequest $request, Asset $asset): JsonResponse
    {
        $filters = $request->validated();
        $query = $asset->maintenanceLogs()
            ->with('asset')
            ->when(isset($filters['asset_id']), fn (Builder $query) => $query->where('asset_id', $filters['asset_id']))
            ->when(isset($filters['q']), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    foreach (['description_of_work', 'parts_replaced', 'performed_by'] as $column) {
                        $query->orWhere($column, 'like', '%'.$filters['q'].'%');
                    }
                });
            })
            ->when(isset($filters['maintenance_type']), fn (Builder $query) => $query->where(
                'maintenance_type',
                $filters['maintenance_type'],
            ))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate(
                'date',
                '>=',
                $filters['date_from'],
            ))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate(
                'date',
                '<=',
                $filters['date_to'],
            ))
            ->when(isset($filters['cost_min']), fn (Builder $query) => $query->where('cost', '>=', $filters['cost_min']))
            ->when(isset($filters['cost_max']), fn (Builder $query) => $query->where('cost', '<=', $filters['cost_max']));

        $totalCost = (clone $query)->sum('cost');
        $logs = $query
            ->orderBy('date', $filters['sort_direction'] ?? 'desc')
            ->orderBy('id', $filters['sort_direction'] ?? 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => MaintenanceLogResource::collection($logs->getCollection())->resolve(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'maintenance_cost_total' => number_format((float) $totalCost, 2, '.', ''),
            ],
        ]);
    }

    public function store(
        StoreMaintenanceLogRequest $request,
        Asset $asset,
    ): JsonResponse {
        $log = DB::transaction(function () use ($request, $asset): MaintenanceLog {
            $log = $asset->maintenanceLogs()->create($request->validated());
            $this->recordActivity($log, $request->user(), 'maintenance_logged', $this->summary($log));

            return $log;
        });
        $log->load('asset');

        return response()->json([
            'success' => true,
            'data' => (new MaintenanceLogResource($log))->resolve(),
        ], 201);
    }

    public function show(MaintenanceLog $maintenanceLog): JsonResponse
    {
        $maintenanceLog->load('asset');

        return response()->json([
            'success' => true,
            'data' => (new MaintenanceLogResource($maintenanceLog))->resolve(),
        ]);
    }

    public function update(
        UpdateMaintenanceLogRequest $request,
        MaintenanceLog $maintenanceLog,
    ): JsonResponse {
        $log = DB::transaction(function () use ($request, $maintenanceLog): MaintenanceLog {
            $maintenanceLog = MaintenanceLog::query()
                ->whereKey($maintenanceLog->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $original = $maintenanceLog->getRawOriginal();
            $maintenanceLog->update($request->validated());

            $changes = [];
            foreach (array_keys($maintenanceLog->getChanges()) as $field) {
                if ($field === 'updated_at') {
                    continue;
                }

                $oldValue = $original[$field] ?? null;
                $newValue = $maintenanceLog->getRawOriginal($field);
                $changes[] = sprintf('%s: "%s" -> "%s"', str_replace('_', ' ', ucfirst($field)), $oldValue ?? '—', $newValue ?? '—');
            }

            $description = 'Updated '.$this->summary($maintenanceLog);
            if ($changes !== []) {
                $description .= ' Changes: '.implode('; ', $changes).'.';
            } else {
                $description .= ' No field values changed.';
            }

            $this->recordActivity($maintenanceLog, $request->user(), 'maintenance_updated', $description);

            return $maintenanceLog;
        });
        $log->load('asset');

        return response()->json([
            'success' => true,
            'data' => (new MaintenanceLogResource($log))->resolve(),
        ]);
    }

    public function destroy(MaintenanceLog $maintenanceLog): JsonResponse
    {
        DB::transaction(function () use ($maintenanceLog): void {
            $user = request()->user();
            $description = 'Deleted '.$this->summary($maintenanceLog);
            $this->recordActivity($maintenanceLog, $user, 'maintenance_deleted', $description);
            $maintenanceLog->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Maintenance log deleted successfully.',
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        return MaintenanceLog::query()
            ->when(isset($filters['asset_id']), fn (Builder $query) => $query->where('asset_id', $filters['asset_id']))
            ->when(isset($filters['maintenance_type']), fn (Builder $query) => $query->where(
                'maintenance_type',
                $filters['maintenance_type'],
            ))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate(
                'date',
                '>=',
                $filters['date_from'],
            ))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate(
                'date',
                '<=',
                $filters['date_to'],
            ))
            ->when(isset($filters['cost_min']), fn (Builder $query) => $query->where('cost', '>=', $filters['cost_min']))
            ->when(isset($filters['cost_max']), fn (Builder $query) => $query->where('cost', '<=', $filters['cost_max']))
            ->when(isset($filters['q']), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    foreach (['description_of_work', 'parts_replaced', 'performed_by'] as $column) {
                        $query->orWhere($column, 'like', '%'.$filters['q'].'%');
                    }
                    $query->orWhereHas('asset', function (Builder $assetQuery) use ($filters): void {
                        $assetQuery->where('asset_tag', 'like', '%'.$filters['q'].'%')
                            ->orWhere('asset_name', 'like', '%'.$filters['q'].'%');
                    });
                });
            });
    }

    private function summary(MaintenanceLog $log): string
    {
        return sprintf(
            'maintenance log (%s, %s, performed by %s).',
            $log->maintenance_type,
            $log->date->toDateString(),
            $log->performed_by,
        );
    }

    private function recordActivity(
        MaintenanceLog $log,
        mixed $user,
        string $action,
        string $description,
    ): void {
        $log->asset->activityLogs()->create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
