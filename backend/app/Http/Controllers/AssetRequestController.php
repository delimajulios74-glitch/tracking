<?php

namespace App\Http\Controllers;

use App\Enums\AssetRequestStatus;
use App\Http\Requests\UpdateAssetRequestRequest;
use App\Http\Resources\AssetRequestResource;
use App\Models\ActivityLog;
use App\Models\AssetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssetRequestController extends Controller
{
    public function photo(AssetRequest $assetRequest): StreamedResponse|BinaryFileResponse
    {
        abort_unless(
            $assetRequest->photo_path && Storage::disk('public')->exists($assetRequest->photo_path),
            404,
        );

        return Storage::disk('public')->response($assetRequest->photo_path, null, [
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', Rule::in(AssetRequestStatus::values())],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $reports = AssetRequest::query()
            ->with(['asset.category', 'asset.location', 'location', 'reporter'])
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));
        $referenceDescriptions = $reports->getCollection()
            ->map(fn (AssetRequest $report): string => 'Linked end-user report: '.$report->reference_no.'.')
            ->all();
        $links = ActivityLog::query()
            ->with('maintenanceTask.assignee')
            ->where('action', 'end_user_report_linked')
            ->whereIn('description', $referenceDescriptions)
            ->orderByDesc('id')
            ->get()
            ->unique('description')
            ->keyBy('description');

        foreach ($reports->getCollection() as $report) {
            $description = 'Linked end-user report: '.$report->reference_no.'.';
            $report->setRelation('linkedMaintenanceTask', $links->get($description)?->maintenanceTask);
        }

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

    public function update(UpdateAssetRequestRequest $request, AssetRequest $assetRequest): JsonResponse
    {
        $validated = $request->validated();
        $newPhotoPath = null;
        if ($request->hasFile('photo')) {
            $newPhotoPath = $request->file('photo')->store('asset-requests', 'public');
            if ($newPhotoPath === false) {
                throw new RuntimeException('Unable to store the uploaded report photo.');
            }
        }

        try {
            [$report, $oldPhotoPath] = DB::transaction(function () use ($request, $assetRequest, $validated, $newPhotoPath): array {
                $report = AssetRequest::query()->lockForUpdate()->findOrFail($assetRequest->getKey());
                $oldPhotoPath = $report->photo_path;
                if (
                    isset($validated['asset_id'])
                    && (int) $validated['asset_id'] !== (int) $report->asset_id
                    && ActivityLog::query()
                        ->where('action', 'end_user_report_linked')
                        ->where('description', 'Linked end-user report: '.$report->reference_no.'.')
                        ->whereNotNull('maintenance_task_id')
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'asset_id' => 'The asset cannot be changed after the report is linked to a maintenance task.',
                    ]);
                }

                $updates = collect($validated)->only([
                    'asset_id',
                    'location_id',
                    'description',
                    'status',
                ])->all();

                if ($newPhotoPath !== null) {
                    $updates['photo_path'] = $newPhotoPath;
                } elseif ($validated['remove_photo'] ?? false) {
                    $updates['photo_path'] = null;
                }

                $report->update($updates);
                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'asset_id' => $report->asset_id,
                    'action' => 'asset_request_updated',
                    'description' => 'Updated report '.$report->reference_no.'.',
                    'created_at' => now(),
                ]);

                return [$report->load(['asset', 'location', 'reporter']), $oldPhotoPath];
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                $this->deletePhotoFile($newPhotoPath, $assetRequest->id);
            }

            throw $exception;
        }

        if ($oldPhotoPath && $oldPhotoPath !== $report->photo_path) {
            $this->deletePhotoFile($oldPhotoPath, $report->id);
        }

        return response()->json([
            'success' => true,
            'data' => (new AssetRequestResource($report))->resolve(),
        ]);
    }

    public function destroy(Request $request, AssetRequest $assetRequest): Response
    {
        $photoPath = DB::transaction(function () use ($request, $assetRequest): ?string {
            $report = AssetRequest::query()->lockForUpdate()->findOrFail($assetRequest->getKey());
            $photoPath = $report->photo_path;
            $report->delete();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'asset_id' => $report->asset_id,
                'action' => 'asset_request_deleted',
                'description' => 'Deleted report '.$report->reference_no.'.',
                'created_at' => now(),
            ]);

            return $photoPath;
        });

        if ($photoPath) {
            $this->deletePhotoFile($photoPath, $assetRequest->id);
        }

        return response()->noContent();
    }

    private function deletePhotoFile(string $path, int $reportId): void
    {
        $disk = Storage::disk('public');
        if ($disk->exists($path) && ! $disk->delete($path)) {
            Log::error('Unable to remove a damage report photo.', [
                'asset_request_id' => $reportId,
                'photo_path' => $path,
            ]);
        }
    }
}
