<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAssetRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetConditionRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Services\AssetActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    public function index(IndexAssetRequest $request): JsonResponse
    {
        $assets = Asset::query()
            ->with(['category', 'location'])
            ->filter($request->validated())
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => AssetResource::collection($assets->getCollection())->resolve(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function store(StoreAssetRequest $request, AssetActivityLogger $activityLogger): JsonResponse
    {
        $asset = DB::transaction(function () use ($request, $activityLogger): Asset {
            $asset = Asset::create($request->validated());
            $activityLogger->logCreated($asset, $request->user());

            return $asset;
        });
        $asset->load(['category', 'location']);

        return response()->json([
            'success' => true,
            'data' => (new AssetResource($asset))->resolve(),
        ], 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['category', 'location', 'activityLogs.user']);

        return response()->json([
            'success' => true,
            'data' => (new AssetResource($asset))->resolve(),
        ]);
    }

    public function update(UpdateAssetRequest $request, Asset $asset, AssetActivityLogger $activityLogger): JsonResponse
    {
        DB::transaction(function () use ($request, $asset, $activityLogger): void {
            $original = $asset->getRawOriginal();
            $asset->update($request->validated());
            $activityLogger->logUpdated($asset, $request->user(), $original);
        });
        $asset->load(['category', 'location']);

        return response()->json([
            'success' => true,
            'data' => (new AssetResource($asset))->resolve(),
        ]);
    }

    public function updateCondition(
        UpdateAssetConditionRequest $request,
        Asset $asset,
        AssetActivityLogger $activityLogger,
    ): JsonResponse {
        $asset = DB::transaction(function () use ($request, $asset, $activityLogger): Asset {
            $asset = Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if ($asset->status !== 'Under Maintenance') {
                throw ValidationException::withMessages([
                    'asset' => 'Condition can only be updated for assets under maintenance.',
                ]);
            }

            $original = $asset->getRawOriginal();
            $asset->update($request->validated());
            $activityLogger->logUpdated($asset, $request->user(), $original);

            return $asset;
        });
        $asset->load(['category', 'location']);

        return response()->json([
            'success' => true,
            'data' => (new AssetResource($asset))->resolve(),
        ]);
    }

    public function destroy(Asset $asset, AssetActivityLogger $activityLogger): JsonResponse
    {
        DB::transaction(function () use ($asset, $activityLogger): void {
            $activityLogger->logDeleted($asset, request()->user());
            $asset->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Asset deleted successfully.',
        ]);
    }
}
