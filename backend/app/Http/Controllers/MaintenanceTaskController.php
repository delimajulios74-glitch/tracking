<?php

namespace App\Http\Controllers;

use App\Enums\AssetRequestStatus;
use App\Http\Requests\ApproveMaintenanceTaskRequest;
use App\Http\Requests\IndexMaintenanceTaskRequest;
use App\Http\Requests\IndexPendingMaintenanceTasksRequest;
use App\Http\Requests\RejectMaintenanceTaskRequest;
use App\Http\Requests\StoreMaintenanceTaskRequest;
use App\Http\Requests\SubmitMaintenanceTaskRequest;
use App\Http\Requests\UpdateMaintenanceTaskRequest;
use App\Http\Resources\MaintenanceTaskResource;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\MaintenanceTask;
use App\Models\User;
use App\Notifications\MaintenanceTaskApproved;
use App\Notifications\MaintenanceTaskAssigned;
use App\Notifications\MaintenanceTaskRejected;
use App\Notifications\MaintenanceTaskSubmitted;
use App\Notifications\MaintenanceTaskUpdated;
use App\Services\AssetActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceTaskController extends Controller
{
    public function index(IndexMaintenanceTaskRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $user = $request->user();
        $tasks = MaintenanceTask::query()
            ->with(['asset.category', 'asset.location', 'assignee', 'assigner'])
            ->when(
                in_array($user->role, config('maintenance_tasks.permissions.view_assigned'), true),
                fn (Builder $query) => $query->where('assigned_to', $user->id),
            )
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['priority']), fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when(
                isset($filters['assigned_to'])
                    && in_array($user->role, config('maintenance_tasks.permissions.view_all'), true),
                fn (Builder $query) => $query->where('assigned_to', $filters['assigned_to']),
            )
            ->when(isset($filters['asset_id']), fn (Builder $query) => $query->where('asset_id', $filters['asset_id']))
            ->when(isset($filters['due_date_from']), fn (Builder $query) => $query->whereDate(
                'due_date',
                '>=',
                $filters['due_date_from'],
            ))
            ->when(isset($filters['due_date_to']), fn (Builder $query) => $query->whereDate(
                'due_date',
                '<=',
                $filters['due_date_to'],
            ))
            ->when(isset($filters['q']), function (Builder $query) use ($filters): void {
                $search = '%'.$filters['q'].'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereRaw('LOWER(title) LIKE LOWER(?)', [$search])
                        ->orWhereHas('asset', function (Builder $assetQuery) use ($search): void {
                            $assetQuery->whereRaw('LOWER(asset_tag) LIKE LOWER(?)', [$search])
                                ->orWhereRaw('LOWER(asset_name) LIKE LOWER(?)', [$search]);
                        });
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));
        $this->attachLinkedReports($tasks->getCollection());

        return response()->json([
            'success' => true,
            'data' => MaintenanceTaskResource::collection($tasks->getCollection())->resolve(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function store(
        StoreMaintenanceTaskRequest $request,
        AssetActivityLogger $assetActivityLogger,
    ): JsonResponse {
        $task = DB::transaction(function () use ($request, $assetActivityLogger): MaintenanceTask {
            $data = $request->validated();
            $reportReference = $data['report_reference_no'] ?? null;
            unset($data['report_reference_no']);
            $asset = Asset::query()
                ->whereKey($data['asset_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureAssetCanBeMaintained($asset);
            $report = null;
            if ($reportReference) {
                $report = AssetRequest::query()
                    ->where('reference_no', $reportReference)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($report->asset_id !== $asset->id) {
                    throw ValidationException::withMessages([
                        'report_reference_no' => 'The selected report belongs to a different asset.',
                    ]);
                }

                if ($this->reportHasActiveTask($reportReference)) {
                    throw ValidationException::withMessages([
                        'report_reference_no' => 'This report already has an active maintenance task.',
                    ]);
                }
            }
            $assignee = User::query()
                ->whereKey($data['assigned_to'])
                ->where('role', config('maintenance_tasks.roles.maintenance'))
                ->where('is_active', true)
                ->firstOrFail();
            $previousAssetStatus = $asset->status === 'Under Maintenance'
                ? null
                : $asset->status;

            $task = MaintenanceTask::create([
                ...$data,
                'assigned_by' => $request->user()->id,
                'previous_asset_status' => $previousAssetStatus,
            ]);
            $this->recordTaskActivity(
                $task,
                $request->user(),
                'task_assigned',
                sprintf(
                    'Task assigned from "None" to "%s". Title: "%s". Status: "None" -> "%s".',
                    $assignee->name,
                    $task->title,
                    $task->status,
                ),
            );
            if ($report) {
                $this->recordTaskActivity(
                    $task,
                    $request->user(),
                    'end_user_report_linked',
                    $this->reportLinkDescription($report->reference_no),
                );
                $report->update(['status' => AssetRequestStatus::InReview]);
            }

            if ($previousAssetStatus !== null) {
                $original = $asset->getRawOriginal();
                $asset->update(['status' => 'Under Maintenance']);
                $assetActivityLogger->logUpdated($asset, $request->user(), $original, $task);
            }

            $this->notifyAssigneeOnce($task, $assignee);

            return $task;
        });
        $this->attachLinkedReports(new Collection([$task]));
        $task->load(['asset.category', 'asset.location', 'assignee', 'assigner']);

        return response()->json([
            'success' => true,
            'data' => (new MaintenanceTaskResource($task))->resolve(),
        ], 201);
    }

    public function show(MaintenanceTask $maintenanceTask): JsonResponse
    {
        $this->authorize('view', $maintenanceTask);
        $maintenanceTask->load([
            'asset.category',
            'asset.location',
            'assignee',
            'assigner',
            'activityLogs.user',
        ]);
        $this->attachLinkedReports(new Collection([$maintenanceTask]));

        return response()->json([
            'success' => true,
            'data' => (new MaintenanceTaskResource($maintenanceTask))->resolve(),
        ]);
    }

    public function submit(
        SubmitMaintenanceTaskRequest $request,
        MaintenanceTask $maintenanceTask,
    ): JsonResponse {
        $task = DB::transaction(function () use ($request, $maintenanceTask): MaintenanceTask {
            $task = MaintenanceTask::query()
                ->whereKey($maintenanceTask->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorize('submit', $task);

            if (! $task->canTransitionTo(MaintenanceTask::STATUS_PENDING_APPROVAL)) {
                throw ValidationException::withMessages([
                    'status' => 'Only an in-progress task can be submitted for approval.',
                ]);
            }

            $previousStatus = $task->status;
            $task->update([
                ...$request->validated(),
                'status' => MaintenanceTask::STATUS_PENDING_APPROVAL,
                'submitted_at' => now(),
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
            ]);
            $this->syncLinkedReportStatus($task, $task->status);
            $this->recordTaskActivity(
                $task,
                $request->user(),
                'task_submitted_for_approval',
                sprintf(
                    'Status changed from "%s" to "%s". Submitted by %s for admin approval.',
                    $previousStatus,
                    $task->status,
                    $request->user()->name,
                ),
            );

            $admins = User::query()
                ->where('role', config('maintenance_tasks.roles.admin'))
                ->where('is_active', true)
                ->get();
            foreach ($admins as $admin) {
                $admin->notify(new MaintenanceTaskSubmitted($task));
            }

            return $task;
        });
        $task->load(['asset.category', 'asset.location', 'assignee', 'assigner']);

        return response()->json([
            'success' => true,
            'data' => (new MaintenanceTaskResource($task))->resolve(),
        ]);
    }

    public function approve(
        ApproveMaintenanceTaskRequest $request,
        MaintenanceTask $maintenanceTask,
        AssetActivityLogger $assetActivityLogger,
    ): JsonResponse {
        $result = DB::transaction(function () use ($request, $maintenanceTask, $assetActivityLogger): array {
            $task = MaintenanceTask::query()
                ->whereKey($maintenanceTask->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorize('approve', $task);

            if (
                $task->status !== MaintenanceTask::STATUS_PENDING_APPROVAL
                || ! $task->canTransitionTo(MaintenanceTask::STATUS_APPROVED)
                || $task->submitted_at === null
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Only a submitted task can be approved.',
                ]);
            }

            $task->update([
                'status' => MaintenanceTask::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'completed_at' => $task->submitted_at,
            ]);

            $workDetails = array_filter([
                trim((string) $task->maintenance_performed),
                trim((string) $task->completion_notes),
            ], fn (string $value): bool => $value !== '');
            $log = $task->maintenanceLog()->create([
                'asset_id' => $task->asset_id,
                'maintenance_type' => 'Repair',
                'date' => $task->submitted_at->toDateString(),
                'performed_by' => $task->assignee->name,
                'description_of_work' => 'Task: '.$task->title."\n\n".implode("\n\n", $workDetails),
                'parts_replaced' => $task->parts_replaced,
                'cost' => null,
                'remarks' => $task->remarks,
            ]);

            $asset = Asset::query()
                ->whereKey($task->asset_id)
                ->lockForUpdate()
                ->firstOrFail();
            $original = $asset->getRawOriginal();
            $updates = [];
            if ($task->updated_condition !== null) {
                $updates['condition'] = $task->updated_condition;
            }
            if ($task->updated_asset_status !== null) {
                $updates['status'] = $task->updated_asset_status;
            } elseif ($asset->status === 'Under Maintenance') {
                $updates['status'] = $task->previous_asset_status ?? 'Available';
            }

            if ($updates !== []) {
                $asset->update($updates);
                $assetActivityLogger->logUpdated($asset, $request->user(), $original, $task);
            }

            $this->syncLinkedReportStatus($task, MaintenanceTask::STATUS_APPROVED);
            $this->recordTaskActivity(
                $task,
                $request->user(),
                'task_approved',
                sprintf(
                    'Status changed from "%s" to "%s" by %s. Maintenance log #%s created.',
                    MaintenanceTask::STATUS_PENDING_APPROVAL,
                    MaintenanceTask::STATUS_APPROVED,
                    $request->user()->name,
                    $log->id,
                ),
            );
            $task->assignee->notify(new MaintenanceTaskApproved($task));

            return ['task' => $task, 'log' => $log];
        });
        $task = $result['task'];
        $task->load(['asset.category', 'asset.location', 'assignee', 'assigner']);

        return response()->json([
            'success' => true,
            'data' => (new MaintenanceTaskResource($task))->resolve(),
            'maintenance_log_id' => $result['log']->id,
        ]);
    }

    public function reject(
        RejectMaintenanceTaskRequest $request,
        MaintenanceTask $maintenanceTask,
    ): JsonResponse {
        $task = DB::transaction(function () use ($request, $maintenanceTask): MaintenanceTask {
            $task = MaintenanceTask::query()
                ->whereKey($maintenanceTask->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorize('reject', $task);

            if (
                $task->status !== MaintenanceTask::STATUS_PENDING_APPROVAL
                || ! $task->canTransitionTo(MaintenanceTask::STATUS_IN_PROGRESS)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Only a submitted task can be rejected.',
                ]);
            }

            $reason = trim($request->validated()['reason']);
            $task->update([
                'status' => MaintenanceTask::STATUS_IN_PROGRESS,
                'rejected_at' => now(),
                'rejected_by' => $request->user()->id,
                'rejection_reason' => $reason,
            ]);
            $this->syncLinkedReportStatus($task, $task->status);
            $this->recordTaskActivity(
                $task,
                $request->user(),
                'task_rejected',
                sprintf(
                    'Status changed from "%s" to "%s" by %s. Rejection reason: %s',
                    MaintenanceTask::STATUS_PENDING_APPROVAL,
                    MaintenanceTask::STATUS_IN_PROGRESS,
                    $request->user()->name,
                    $reason,
                ),
            );
            $task->assignee->notify(new MaintenanceTaskRejected($task));

            return $task;
        });
        $task->load(['asset.category', 'asset.location', 'assignee', 'assigner']);

        return response()->json([
            'success' => true,
            'data' => (new MaintenanceTaskResource($task))->resolve(),
        ]);
    }

    public function pendingApprovals(IndexPendingMaintenanceTasksRequest $request): JsonResponse
    {
        $tasks = MaintenanceTask::query()
            ->where('status', MaintenanceTask::STATUS_PENDING_APPROVAL)
            ->with(['asset.category', 'asset.location', 'assignee', 'assigner'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => MaintenanceTaskResource::collection($tasks->getCollection())->resolve(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function update(
        UpdateMaintenanceTaskRequest $request,
        MaintenanceTask $maintenanceTask,
        AssetActivityLogger $assetActivityLogger,
    ): JsonResponse {
        $task = DB::transaction(function () use ($request, $maintenanceTask, $assetActivityLogger): MaintenanceTask {
            $task = MaintenanceTask::query()
                ->whereKey($maintenanceTask->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorize('update', $task);
            $data = $request->validated();
            if ($request->user()->role !== config('maintenance_tasks.roles.admin')) {
                unset($data['asset_id'], $data['assigned_to']);
            }
            if (
                isset($data['status'])
                && $data['status'] !== $task->status
                && (
                    ! $task->canTransitionTo($data['status'])
                    || $task->status === MaintenanceTask::STATUS_PENDING_APPROVAL
                )
            ) {
                throw ValidationException::withMessages([
                    'status' => 'This task cannot transition to the selected status through this endpoint.',
                ]);
            }
            if (isset($data['asset_id'])) {
                $linkedReport = $this->linkedReportForTask($task);
                if ($linkedReport && $linkedReport->asset_id !== (int) $data['asset_id']) {
                    throw ValidationException::withMessages([
                        'asset_id' => 'A task linked to an end-user report must remain assigned to that report’s asset.',
                    ]);
                }
            }
            $original = $task->getRawOriginal();
            $assetWasReassigned = isset($data['asset_id'])
                && (int) $data['asset_id'] !== (int) $original['asset_id'];

            if (isset($data['status']) && $data['status'] !== $task->status) {
                if ($data['status'] === MaintenanceTask::STATUS_ACKNOWLEDGED && $task->acknowledged_at === null) {
                    $data['acknowledged_at'] = now();
                }
                if ($data['status'] === MaintenanceTask::STATUS_IN_PROGRESS && $task->started_at === null) {
                    $data['started_at'] = now();
                }
            }

            $task->update($data);
            if ($assetWasReassigned) {
                $task->update(['previous_asset_status' => null]);
            }
            if (isset($data['status'])) {
                $this->syncLinkedReportStatus($task, $data['status']);
            }
            $changes = $this->describeChanges($task, $original, array_keys($data));

            if ($changes['fields'] !== []) {
                $this->recordChangedFields($task, $request->user(), $changes);
            }

            if ($data['asset_id'] ?? false) {
                $asset = Asset::query()
                    ->whereKey($task->asset_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->ensureAssetCanBeMaintained($asset);
                if ($asset->status !== 'Under Maintenance') {
                    $task->update(['previous_asset_status' => $asset->status]);
                    $assetOriginal = $asset->getRawOriginal();
                    $asset->update(['status' => 'Under Maintenance']);
                    $assetActivityLogger->logUpdated(
                        $asset,
                        $request->user(),
                        $assetOriginal,
                        $task,
                    );
                }
            }

            if (
                $request->user()->role === config('maintenance_tasks.roles.maintenance')
                && $task->status === MaintenanceTask::STATUS_ACKNOWLEDGED
                && $task->getOriginal('status') !== MaintenanceTask::STATUS_ACKNOWLEDGED
            ) {
                $this->notifyTaskAdmins($task);
            }

            return $task;
        });
        $this->attachLinkedReports(new Collection([$task]));
        $task->load(['asset.category', 'asset.location', 'assignee', 'assigner']);

        return response()->json([
            'success' => true,
            'data' => (new MaintenanceTaskResource($task))->resolve(),
        ]);
    }

    public function destroy(MaintenanceTask $maintenanceTask): JsonResponse
    {
        $this->authorize('delete', $maintenanceTask);
        DB::transaction(function () use ($maintenanceTask): void {
            $task = MaintenanceTask::query()
                ->whereKey($maintenanceTask->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->authorize('delete', $task);
            if (! $task->canTransitionTo(MaintenanceTask::STATUS_CANCELLED)) {
                throw ValidationException::withMessages([
                    'status' => 'This task cannot be cancelled from its current status.',
                ]);
            }
            $originalStatus = $task->status;
            $task->update([
                'status' => MaintenanceTask::STATUS_CANCELLED,
            ]);
            $this->syncLinkedReportStatus($task, MaintenanceTask::STATUS_CANCELLED);
            $this->recordTaskActivity(
                $task,
                request()->user(),
                'task_cancelled',
                sprintf(
                    'Status changed from "%s" to "%s" by task cancellation.',
                    $originalStatus,
                    MaintenanceTask::STATUS_CANCELLED,
                ),
            );
            $task->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Maintenance task deleted successfully.',
        ]);
    }

    private function describeChanges(MaintenanceTask $task, array $original, array $fields): array
    {
        $descriptions = [];
        $changedFields = [];

        foreach (array_unique($fields) as $field) {
            if (in_array($field, ['started_at', 'completed_at'], true)) {
                continue;
            }

            $oldValue = $original[$field] ?? null;
            $newValue = $task->getAttributes()[$field] ?? null;
            if ($oldValue == $newValue) {
                continue;
            }

            $changedFields[] = $field;
            $label = str_replace('_', ' ', ucfirst($field));
            $descriptions[] = sprintf(
                '%s: "%s" -> "%s"',
                $label,
                $this->formatValue($field, $oldValue),
                $this->formatValue($field, $newValue),
            );
        }

        return [
            'fields' => $changedFields,
            'descriptions' => $descriptions,
        ];
    }

    private function notifyAssigneeOnce(MaintenanceTask $task, User $assignee): void
    {
        $alreadyNotified = $assignee->notifications()
            ->get()
            ->contains(fn ($notification): bool => ($notification->data['task_id'] ?? null) === $task->id
                && ($notification->data['type'] ?? null) === 'maintenance_task_assigned');

        if (! $alreadyNotified) {
            $assignee->notify(new MaintenanceTaskAssigned($task));
        }
    }

    private function notifyTaskAdmins(MaintenanceTask $task): void
    {
        $admins = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->get();
        $notificationType = 'maintenance_task_acknowledged';

        foreach ($admins as $admin) {
            $alreadyNotified = $admin->notifications()
                ->get()
                ->contains(fn ($notification): bool => ($notification->data['task_id'] ?? null) === $task->id
                    && ($notification->data['type'] ?? null) === $notificationType);

            if (! $alreadyNotified) {
                $admin->notify(new MaintenanceTaskUpdated($task));
            }
        }
    }

    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'None';
        }

        return match ($field) {
            'assigned_to' => (string) User::query()->find($value)?->name,
            'asset_id' => (string) Asset::query()->withTrashed()->find($value)?->asset_tag,
            default => (string) $value,
        };
    }

    private function recordChangedFields(MaintenanceTask $task, User $user, array $changes): void
    {
        $groups = [
            'assigned_to' => [
                'fields' => ['assigned_to'],
                'action' => 'task_reassigned',
            ],
            'status' => [
                'fields' => ['status'],
                'action' => $task->status === MaintenanceTask::STATUS_CANCELLED
                    ? 'task_cancelled'
                    : 'task_status_changed',
            ],
            'report' => [
                'fields' => ['problem', 'remarks'],
                'action' => 'task_report_updated',
            ],
            'details' => [
                'fields' => ['asset_id', 'title', 'instructions', 'priority', 'due_date'],
                'action' => 'task_updated',
            ],
        ];

        foreach ($groups as $group) {
            $descriptions = [];
            foreach ($changes['fields'] as $index => $field) {
                if (in_array($field, $group['fields'], true)) {
                    $descriptions[] = $changes['descriptions'][$index];
                }
            }

            if ($descriptions !== []) {
                $this->recordTaskActivity(
                    $task,
                    $user,
                    $group['action'],
                    'Updated task values: '.implode('; ', $descriptions).'.',
                );
            }
        }
    }

    private function recordTaskActivity(
        MaintenanceTask $task,
        User $user,
        string $action,
        string $description,
    ): void {
        $task->activityLogs()->create([
            'user_id' => $user->id,
            'asset_id' => $task->asset_id,
            'action' => $action,
            'description' => $description,
        ]);
    }

    private function ensureAssetCanBeMaintained(Asset $asset): void
    {
        if ($asset->status === 'Disposed') {
            throw ValidationException::withMessages([
                'asset_id' => 'A disposed asset cannot be assigned a maintenance task.',
            ]);
        }
    }

    private function reportLinkDescription(string $referenceNo): string
    {
        return 'Linked end-user report: '.$referenceNo.'.';
    }

    private function reportHasActiveTask(string $referenceNo): bool
    {
        return ActivityLog::query()
            ->where('action', 'end_user_report_linked')
            ->where('description', $this->reportLinkDescription($referenceNo))
            ->whereHas('maintenanceTask', fn (Builder $query) => $query->where('status', '!=', 'Cancelled'))
            ->exists();
    }

    private function linkedReportForTask(MaintenanceTask $task): ?AssetRequest
    {
        $activity = $task->activityLogs()
            ->where('action', 'end_user_report_linked')
            ->latest('id')
            ->first();

        if (! $activity || ! preg_match('/^Linked end-user report: (.+)\.$/', $activity->description, $matches)) {
            return null;
        }

        return AssetRequest::query()
            ->with('reporter')
            ->where('reference_no', $matches[1])
            ->first();
    }

    private function attachLinkedReports(Collection $tasks): void
    {
        if ($tasks->isEmpty()) {
            return;
        }

        $links = ActivityLog::query()
            ->whereIn('maintenance_task_id', $tasks->modelKeys())
            ->where('action', 'end_user_report_linked')
            ->orderByDesc('id')
            ->get(['maintenance_task_id', 'description'])
            ->unique('maintenance_task_id')
            ->mapWithKeys(function (ActivityLog $activity): array {
                if (! preg_match('/^Linked end-user report: (.+)\.$/', $activity->description, $matches)) {
                    return [];
                }

                return [$activity->maintenance_task_id => $matches[1]];
            });
        $reports = AssetRequest::query()
            ->with('reporter')
            ->whereIn('reference_no', $links->values()->unique())
            ->get()
            ->keyBy('reference_no');

        foreach ($tasks as $task) {
            $task->setRelation('linkedReport', $reports->get($links->get($task->id)));
        }
    }

    private function syncLinkedReportStatus(MaintenanceTask $task, string $taskStatus): void
    {
        $report = $this->linkedReportForTask($task);
        if (! $report) {
            return;
        }

        $reportStatus = match ($taskStatus) {
            MaintenanceTask::STATUS_APPROVED => AssetRequestStatus::Resolved,
            MaintenanceTask::STATUS_CANCELLED => AssetRequestStatus::Pending,
            default => AssetRequestStatus::InReview,
        };

        if ($report->status !== $reportStatus) {
            $report->update(['status' => $reportStatus]);
        }
    }
}
