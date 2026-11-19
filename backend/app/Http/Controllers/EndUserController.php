<?php

namespace App\Http\Controllers;

use App\Enums\AssetRequestStatus;
use App\Enums\AssetRequestType;
use App\Http\Requests\IndexEndUserEquipmentRequest;
use App\Http\Requests\StoreAssetRequestRequest;
use App\Http\Resources\AssetRequestResource;
use App\Http\Resources\EndUserAssetResource;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EndUserController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $equipmentByStatus = Asset::visibleToEndUser($request->user())
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (Asset $asset): array => [
                'status' => $asset->status,
                'count' => (int) $asset->count,
            ]);

        $reportsByStatus = $request->user()->assetRequests()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (AssetRequest $assetRequest): array => [
                'status' => $assetRequest->status->value,
                'count' => (int) $assetRequest->count,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'equipment_by_status' => $equipmentByStatus,
                'reports_by_status' => $reportsByStatus,
                'latest_reports' => AssetRequestResource::collection(
                    $request->user()->assetRequests()
                        ->with(['asset', 'location'])
                        ->latest()
                        ->limit(5)
                        ->get(),
                )->resolve(),
            ],
        ]);
    }

    public function equipment(IndexEndUserEquipmentRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $equipment = Asset::visibleToEndUser($request->user())
            ->with(['category', 'location'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('asset_name', 'like', "%{$search}%")
                        ->orWhere('asset_tag', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('asset_name')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => EndUserAssetResource::collection($equipment->getCollection())->resolve(),
            'meta' => [
                'current_page' => $equipment->currentPage(),
                'last_page' => $equipment->lastPage(),
                'per_page' => $equipment->perPage(),
                'total' => $equipment->total(),
            ],
        ]);
    }

    public function showEquipment(Request $request, int $asset): JsonResponse
    {
        $equipment = Asset::visibleToEndUser($request->user())
            ->with(['category', 'location'])
            ->findOrFail($asset);

        return response()->json([
            'success' => true,
            'data' => (new EndUserAssetResource($equipment))->resolve(),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->whereHas('assets', fn ($assets) => $assets->visibleToEndUser($request->user()))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function locations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Location::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $reports = $request->user()->assetRequests()
            ->with(['asset', 'location'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => AssetRequestResource::collection($reports->getCollection())->resolve(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    public function showReport(Request $request, int $assetRequest): JsonResponse
    {
        $report = $request->user()->assetRequests()
            ->with(['asset', 'location'])
            ->findOrFail($assetRequest);

        return response()->json([
            'success' => true,
            'data' => (new AssetRequestResource($report))->resolve(),
        ]);
    }

    public function destroyReport(Request $request, int $assetRequest): Response
    {
        $photoPath = DB::transaction(function () use ($request, $assetRequest): ?string {
            $report = $request->user()->assetRequests()
                ->lockForUpdate()
                ->findOrFail($assetRequest);

            abort_unless(
                $report->status === AssetRequestStatus::Pending,
                403,
                'Only pending reports can be deleted.',
            );

            $photoPath = $report->photo_path;
            $report->delete();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'asset_id' => $report->asset_id,
                'action' => 'asset_request_deleted',
                'description' => 'Deleted pending report '.$report->reference_no.'.',
                'created_at' => now(),
            ]);

            return $photoPath;
        });

        if ($photoPath) {
            $disk = Storage::disk('public');
            if ($disk->exists($photoPath) && ! $disk->delete($photoPath)) {
                Log::error('Unable to remove a damage report photo.', [
                    'asset_request_id' => $assetRequest,
                    'photo_path' => $photoPath,
                ]);
            }
        }

        return response()->noContent();
    }

    public function storeReport(StoreAssetRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $asset = Asset::visibleToEndUser($request->user())->findOrFail($validated['asset_id']);
        $photoPath = $request->file('photo')?->store('asset-requests', 'public');

        try {
            $report = DB::transaction(function () use ($validated, $request, $asset, $photoPath): AssetRequest {
                $report = AssetRequest::create([
                    'reference_no' => 'RPT-'.now()->format('Y').'-'.Str::ulid(),
                    'asset_id' => $asset->id,
                    'location_id' => $validated['location_id'],
                    'reported_by' => $request->user()->id,
                    'type' => AssetRequestType::Damage,
                    'description' => $validated['description'],
                    'photo_path' => $photoPath,
                    'status' => AssetRequestStatus::Pending,
                ]);

                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'asset_id' => $asset->id,
                    'action' => 'asset_request_submitted',
                    'description' => 'Submitted report '.$report->reference_no.'.',
                    'created_at' => now(),
                ]);

                return $report->load(['asset', 'location']);
            });
        } catch (Throwable $exception) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Your report was submitted.',
            'data' => (new AssetRequestResource($report))->resolve(),
        ], 201);
    }

    public function reportPhoto(Request $request, int $assetRequest): StreamedResponse|BinaryFileResponse
    {
        $report = $request->user()->assetRequests()->findOrFail($assetRequest);
        abort_unless($report->photo_path && Storage::disk('public')->exists($report->photo_path), 404);

        return Storage::disk('public')->response($report->photo_path, null, [
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
