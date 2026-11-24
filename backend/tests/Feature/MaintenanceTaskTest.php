<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceTask;
use App\Models\User;
use App\Notifications\MaintenanceTaskApproved;
use App\Notifications\MaintenanceTaskRejected;
use App\Notifications\MaintenanceTaskSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_link_an_end_user_report_to_a_task_and_status_follows_maintenance(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $endUser = User::factory()->create(['role' => 'end_user']);
        $report = AssetRequest::create([
            'reference_no' => 'RPT-2026-LINKED',
            'asset_id' => $asset->id,
            'location_id' => $asset->location_id,
            'reported_by' => $endUser->id,
            'type' => 'damage',
            'description' => 'The printer is making a grinding sound.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/asset-requests')
            ->assertOk()
            ->assertJsonPath('data.0.reference_no', $report->reference_no)
            ->assertJsonPath('data.0.reporter.name', $endUser->name);

        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', array_merge(
                $this->taskData($asset, $maintenanceUser),
                ['report_reference_no' => $report->reference_no],
            ))
            ->assertCreated()
            ->assertJsonPath('data.linked_report.reference_no', $report->reference_no)
            ->assertJsonPath('data.linked_report.status', 'in_review');
        $taskId = $created->json('data.id');

        $this->assertSame('in_review', $report->fresh()->status->value);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/asset-requests')
            ->assertOk()
            ->assertJsonPath('data.0.reference_no', $report->reference_no)
            ->assertJsonPath('data.0.maintenance_task.title', $created->json('data.title'))
            ->assertJsonPath('data.0.maintenance_task.assigned_to', $maintenanceUser->name);
        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $taskId,
            'action' => 'end_user_report_linked',
            'description' => 'Linked end-user report: '.$report->reference_no.'.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/maintenance-tasks')
            ->assertOk()
            ->assertJsonPath('data.0.linked_report.reference_no', $report->reference_no);

        $otherAsset = $this->createAsset(['asset_tag' => 'OTHER-REPORT-ASSET']);
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$taskId}", ['asset_id' => $otherAsset->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_id']);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$taskId}", [
                'status' => 'In Progress',
                'problem' => 'The paper feed roller is worn.',
            ])
            ->assertOk();

        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$taskId}", [
                'status' => 'Completed',
                'problem' => 'Replaced the paper feed roller and tested the printer.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('in_review', $report->fresh()->status->value);
        $this->actingAs($endUser, 'sanctum')
            ->getJson('/api/end-user/reports/'.$report->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_admin_can_assign_reassign_edit_and_cancel_tasks_with_activity_history(): void
    {
        [$admin, $firstMaintenanceUser, $asset] = $this->makeTaskActors();

        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', $this->taskData($asset, $firstMaintenanceUser))
            ->assertCreated()
            ->assertJsonPath('data.asset_id', $asset->id)
            ->assertJsonPath('data.assignee.id', $firstMaintenanceUser->id)
            ->assertJsonPath('data.priority', 'High')
            ->assertJsonPath('data.status', MaintenanceTask::STATUS_ASSIGNED);

        $taskId = $created->json('data.id');
        $this->assertSame('Under Maintenance', $asset->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $taskId,
            'asset_id' => $asset->id,
            'user_id' => $admin->id,
            'action' => 'task_assigned',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $taskId,
            'asset_id' => $asset->id,
            'user_id' => $admin->id,
            'action' => 'status changed',
            'description' => 'Status changed from "Available" to "Under Maintenance".',
        ]);

        $secondMaintenanceUser = User::factory()->create(['role' => 'maintenance']);
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$taskId}", [
                'assigned_to' => $secondMaintenanceUser->id,
                'title' => 'Inspect and repair printer',
            ])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $secondMaintenanceUser->id)
            ->assertJsonPath('data.title', 'Inspect and repair printer');

        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $taskId,
            'action' => 'task_reassigned',
        ]);
        $reassignmentDescription = DB::table('activity_logs')
            ->where('maintenance_task_id', $taskId)
            ->where('action', 'task_reassigned')
            ->value('description');
        $this->assertStringContainsString($firstMaintenanceUser->name, $reassignmentDescription);
        $this->assertStringContainsString($secondMaintenanceUser->name, $reassignmentDescription);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$taskId}", ['status' => 'Cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cancelled');

        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $taskId,
            'action' => 'task_cancelled',
        ]);
    }

    public function test_admin_can_assign_task_to_asset_already_under_maintenance_without_changing_status(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors(['status' => 'Under Maintenance']);

        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', $this->taskData($asset, $maintenanceUser))
            ->assertCreated();

        $this->assertSame('Under Maintenance', $asset->fresh()->status);
        $this->assertDatabaseHas('maintenance_tasks', [
            'id' => $created->json('data.id'),
            'previous_asset_status' => null,
        ]);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'action' => 'task_assigned',
        ]);
    }

    public function test_maintenance_user_sees_and_updates_only_own_task(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $otherMaintenanceUser = User::factory()->create(['role' => 'maintenance']);
        $ownTask = $this->createTask($asset, $maintenanceUser, $admin);
        $otherTask = $this->createTask($asset, $otherMaintenanceUser, $admin, ['title' => 'Other task']);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->getJson('/api/maintenance-tasks')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownTask->id);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->getJson("/api/maintenance-tasks/{$otherTask->id}")
            ->assertForbidden();

        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$ownTask->id}", [
                'asset_id' => null,
                'assigned_to' => null,
                'status' => 'In Progress',
                'problem' => 'Paper jams while printing.',
                'remarks' => 'Needs a replacement roller.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'In Progress')
            ->assertJsonPath('data.problem', 'Paper jams while printing.')
            ->assertJsonPath('data.remarks', 'Needs a replacement roller.');

        $this->assertSame($asset->id, $ownTask->fresh()->asset_id);
        $this->assertSame($maintenanceUser->id, $ownTask->fresh()->assigned_to);
        $this->assertNotNull($ownTask->fresh()->started_at);
        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $ownTask->id,
            'user_id' => $maintenanceUser->id,
            'action' => 'task_report_updated',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $ownTask->id,
            'user_id' => $maintenanceUser->id,
            'action' => 'task_status_changed',
        ]);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$ownTask->id}", [
                'title' => 'Unauthorized title change',
                'status' => 'Completed',
                'problem' => 'Problem found.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);

        $this->assertSame('Inspect printer', $ownTask->fresh()->title);
        $this->assertSame('In Progress', $ownTask->fresh()->status);
    }

    public function test_generic_task_updates_cannot_set_completion_or_approval_statuses(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $task = $this->createTask($asset, $maintenanceUser, $admin, ['status' => 'In Progress']);

        foreach ([
            MaintenanceTask::STATUS_COMPLETED,
            MaintenanceTask::STATUS_PENDING_APPROVAL,
            MaintenanceTask::STATUS_APPROVED,
        ] as $status) {
            $this->actingAs($maintenanceUser, 'sanctum')
                ->patchJson("/api/maintenance-tasks/{$task->id}", ['status' => $status])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);
            $this->actingAs($admin, 'sanctum')
                ->patchJson("/api/maintenance-tasks/{$task->id}", ['status' => $status])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);
        }

        $this->assertSame(MaintenanceTask::STATUS_IN_PROGRESS, $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_submit_and_approve_create_approved_log_and_defer_side_effects(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $endUser = User::factory()->create(['role' => 'end_user']);
        $report = AssetRequest::create([
            'reference_no' => 'RPT-2026-APPROVAL',
            'asset_id' => $asset->id,
            'location_id' => $asset->location_id,
            'reported_by' => $endUser->id,
            'type' => 'damage',
            'description' => 'The printer is making a grinding sound.',
        ]);
        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', array_merge(
                $this->taskData($asset, $maintenanceUser),
                ['report_reference_no' => $report->reference_no],
            ))
            ->assertCreated();
        $task = MaintenanceTask::findOrFail($created->json('data.id'));

        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$task->id}", [
                'status' => MaintenanceTask::STATUS_IN_PROGRESS,
            ])
            ->assertOk();

        $this->actingAs($maintenanceUser, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/submit", [
                'problem' => 'The paper feed roller was worn.',
                'completion_notes' => 'Cleared the obstruction and tested the feed.',
                'maintenance_performed' => 'Replaced and tested the paper feed roller.',
                'parts_replaced' => 'Paper feed roller',
                'updated_condition' => 'Poor',
                'updated_asset_status' => 'Disposed',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceTask::STATUS_PENDING_APPROVAL);

        $task->refresh();
        $this->assertNotNull($task->submitted_at);
        $this->assertNull($task->completed_at);
        $this->assertSame('Good', $asset->fresh()->condition);
        $this->assertSame('Under Maintenance', $asset->fresh()->status);
        $this->assertSame('in_review', $report->fresh()->status->value);
        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $task->id,
            'user_id' => $maintenanceUser->id,
            'action' => 'task_submitted_for_approval',
        ]);
        $submittedNotification = $admin->notifications()
            ->where('type', MaintenanceTaskSubmitted::class)
            ->first();
        $this->assertNotNull($submittedNotification);
        $this->assertSame($task->id, $submittedNotification->data['task_id']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceTask::STATUS_APPROVED);

        $task->refresh();
        $this->assertSame($admin->id, $task->approved_by);
        $this->assertNotNull($task->approved_at);
        $this->assertEquals($task->submitted_at, $task->completed_at);
        $this->assertSame('Poor', $asset->fresh()->condition);
        $this->assertSame('Disposed', $asset->fresh()->status);
        $this->assertSame('resolved', $report->fresh()->status->value);
        $this->assertDatabaseCount('maintenance_logs', 1);
        $log = MaintenanceLog::query()->where('task_id', $task->id)->firstOrFail();
        $this->assertSame('Repair', $log->maintenance_type);
        $this->assertSame($maintenanceUser->name, $log->performed_by);
        $this->assertSame('Paper feed roller', $log->parts_replaced);
        $this->assertStringContainsString('Replaced and tested', $log->description_of_work);
        $approvedNotification = $maintenanceUser->notifications()
            ->where('type', MaintenanceTaskApproved::class)
            ->first();
        $this->assertNotNull($approvedNotification);
        $this->assertSame($task->id, $approvedNotification->data['task_id']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/maintenance-logs')
            ->assertOk()
            ->assertJsonPath('data.0.task_id', $task->id)
            ->assertJsonPath('data.0.task_status', MaintenanceTask::STATUS_APPROVED);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
        $this->assertDatabaseCount('maintenance_logs', 1);
    }

    public function test_approval_restores_previous_asset_status_and_lists_approved_and_manual_maintenance_history(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $asset->update(['status' => 'In Use']);
        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', $this->taskData($asset, $maintenanceUser))
            ->assertCreated();
        $task = MaintenanceTask::findOrFail($created->json('data.id'));

        $this->assertSame('Under Maintenance', $asset->fresh()->status);
        $this->assertSame('In Use', $task->previous_asset_status);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$task->id}", [
                'status' => MaintenanceTask::STATUS_IN_PROGRESS,
            ])
            ->assertOk();
        $this->actingAs($maintenanceUser, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/submit", [
                'problem' => 'The device stopped working.',
                'completion_notes' => 'Repaired and tested the device.',
                'updated_condition' => 'Fair',
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/approve")
            ->assertOk();

        $task->refresh();
        $this->assertSame('In Use', $asset->fresh()->status);
        $this->assertSame('Fair', $asset->fresh()->condition);

        MaintenanceLog::create([
            'asset_id' => $asset->id,
            'maintenance_type' => 'Inspection',
            'date' => $task->submitted_at->toDateString(),
            'performed_by' => 'Property Custodian',
            'description_of_work' => 'Manual inspection entry.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/assets?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'In Use')
            ->assertJsonPath('data.0.condition', 'Fair')
            ->assertJsonPath('data.0.last_maintenance_date', $task->submitted_at->toDateString())
            ->assertJsonPath('data.0.last_maintenance_task_id', $task->id);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/assets/{$asset->id}/maintenance-logs")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.task_id', null)
            ->assertJsonPath('data.0.description_of_work', 'Manual inspection entry.')
            ->assertJsonPath('data.1.task_id', $task->id);
    }

    public function test_approval_falls_back_to_available_for_legacy_task_without_previous_asset_status(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors([
            'status' => 'Under Maintenance',
        ]);
        $task = $this->createTask($asset, $maintenanceUser, $admin, [
            'status' => MaintenanceTask::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
            'problem' => 'The device stopped working.',
            'completion_notes' => 'Repaired and tested the device.',
        ]);

        $this->assertNull($task->previous_asset_status);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/approve")
            ->assertOk();

        $this->assertSame('Available', $asset->fresh()->status);
    }

    public function test_submission_authorization_and_invalid_states_are_enforced(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $anotherMaintenanceUser = User::factory()->create(['role' => 'maintenance']);
        $ownTask = $this->createTask($asset, $maintenanceUser, $admin, [
            'status' => MaintenanceTask::STATUS_IN_PROGRESS,
        ]);
        $otherTask = $this->createTask($asset, $anotherMaintenanceUser, $admin, [
            'status' => MaintenanceTask::STATUS_IN_PROGRESS,
        ]);
        $payload = [
            'problem' => 'Problem found.',
            'completion_notes' => 'Work was completed.',
        ];

        $this->actingAs($anotherMaintenanceUser, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$ownTask->id}/submit", $payload)
            ->assertForbidden();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$ownTask->id}/submit", $payload)
            ->assertForbidden();

        foreach ([MaintenanceTask::STATUS_ASSIGNED, MaintenanceTask::STATUS_CANCELLED, MaintenanceTask::STATUS_APPROVED] as $status) {
            $task = $this->createTask($asset, $maintenanceUser, $admin, ['status' => $status]);
            $this->actingAs($maintenanceUser, 'sanctum')
                ->postJson("/api/maintenance-tasks/{$task->id}/submit", $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);
        }

        $this->actingAs($maintenanceUser, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$otherTask->id}/approve")
            ->assertForbidden();
        $this->actingAs($maintenanceUser, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$otherTask->id}/reject", ['reason' => 'Needs more work.'])
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$ownTask->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$ownTask->id}/reject", ['reason' => 'Needs more work.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $pendingTask = $this->createTask($asset, $maintenanceUser, $admin, [
            'status' => MaintenanceTask::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ]);
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/maintenance-tasks/{$pendingTask->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
        $this->assertSame(MaintenanceTask::STATUS_PENDING_APPROVAL, $pendingTask->fresh()->status);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->getJson('/api/maintenance-tasks-pending')
            ->assertForbidden();
    }

    public function test_rejection_requires_reason_and_allows_maintenance_resubmission(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $task = $this->createTask($asset, $maintenanceUser, $admin, [
            'status' => MaintenanceTask::STATUS_PENDING_APPROVAL,
            'submitted_at' => now()->subMinutes(5),
            'problem' => 'The paper feed roller was worn.',
            'completion_notes' => 'Cleaned and tested the feed.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/reject", ['reason' => '     '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/reject", ['reason' => 'Replace the roller.'])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceTask::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.rejection_reason', 'Replace the roller.');

        $task->refresh();
        $this->assertSame($admin->id, $task->rejected_by);
        $this->assertNotNull($task->rejected_at);
        $rejectionDescription = DB::table('activity_logs')
            ->where('maintenance_task_id', $task->id)
            ->where('action', 'task_rejected')
            ->value('description');
        $this->assertStringContainsString('Replace the roller.', $rejectionDescription);
        $rejectedNotification = $maintenanceUser->notifications()
            ->where('type', MaintenanceTaskRejected::class)
            ->first();
        $this->assertNotNull($rejectedNotification);
        $this->assertSame('Replace the roller.', $rejectedNotification->data['reason']);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$task->id}", ['remarks' => 'Roller replacement completed.'])
            ->assertOk();
        $this->actingAs($maintenanceUser, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/submit", [
                'problem' => 'The paper feed roller was worn.',
                'completion_notes' => 'Replaced the roller and tested the feed.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', MaintenanceTask::STATUS_PENDING_APPROVAL)
            ->assertJsonPath('data.rejection_reason', null);

        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $task->id,
            'action' => 'task_rejected',
        ]);
        $this->assertSame(1, $admin->notifications()
            ->where('type', MaintenanceTaskSubmitted::class)
            ->count());
    }

    public function test_pending_approvals_are_admin_only_and_paginated_oldest_first(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $tasks = [];
        for ($index = 0; $index < 21; $index++) {
            $tasks[] = $this->createTask($asset, $maintenanceUser, $admin, [
                'title' => 'Approval task '.$index,
                'status' => MaintenanceTask::STATUS_PENDING_APPROVAL,
                'submitted_at' => now()->subMinutes(21 - $index),
            ]);
        }
        $this->createTask($asset, $maintenanceUser, $admin, [
            'status' => MaintenanceTask::STATUS_IN_PROGRESS,
            'title' => 'Not awaiting approval',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/maintenance-tasks-pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('data.0.id', $tasks[0]->id)
            ->assertJsonPath('data.19.id', $tasks[19]->id)
            ->assertJsonPath('data.0.assignee.id', $maintenanceUser->id)
            ->assertJsonPath('data.0.asset.id', $asset->id);
    }

    public function test_approval_transaction_rolls_back_if_maintenance_log_creation_fails(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $task = $this->createTask($asset, $maintenanceUser, $admin, [
            'status' => MaintenanceTask::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
            'problem' => 'Problem found.',
            'completion_notes' => 'Work performed.',
        ]);
        MaintenanceLog::create([
            'asset_id' => $asset->id,
            'task_id' => $task->id,
            'maintenance_type' => 'Repair',
            'date' => now()->toDateString(),
            'performed_by' => $maintenanceUser->name,
            'description_of_work' => 'Existing linked log.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/maintenance-tasks/{$task->id}/approve")
            ->assertServerError();

        $this->assertSame(MaintenanceTask::STATUS_PENDING_APPROVAL, $task->fresh()->status);
        $this->assertNull($task->fresh()->approved_at);
        $this->assertDatabaseCount('maintenance_logs', 1);
        $this->assertDatabaseMissing('activity_logs', [
            'maintenance_task_id' => $task->id,
            'action' => 'task_approved',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $maintenanceUser->id,
            'notifiable_type' => User::class,
            'type' => MaintenanceTaskApproved::class,
        ]);
    }

    public function test_admin_task_filters_search_and_soft_delete_work(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $matching = $this->createTask($asset, $maintenanceUser, $admin, [
            'title' => 'Repair office printer',
            'priority' => 'Urgent',
            'due_date' => now()->addDays(3)->toDateString(),
        ]);
        $this->createTask($asset, $maintenanceUser, $admin, [
            'title' => 'Inspect equipment',
            'priority' => 'Low',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/maintenance-tasks?q=OFFICE%20PRIN&status='.urlencode(MaintenanceTask::STATUS_ASSIGNED).'&priority=Urgent&assigned_to='.$maintenanceUser->id.'&due_date_from='.now()->addDay()->toDateString().'&due_date_to='.now()->addDays(5)->toDateString().'&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 1);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/maintenance-tasks/{$matching->id}")
            ->assertOk();

        $this->assertSoftDeleted('maintenance_tasks', ['id' => $matching->id]);
        $this->assertDatabaseHas('activity_logs', [
            'maintenance_task_id' => $matching->id,
            'action' => 'task_cancelled',
        ]);
    }

    public function test_tasks_are_ordered_by_most_recent_assignment_or_update(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $olderTask = $this->createTask($asset, $maintenanceUser, $admin, [
            'title' => 'Older task with an earlier due date',
            'due_date' => '2026-11-24',
        ]);
        $recentTask = $this->createTask($asset, $maintenanceUser, $admin, [
            'title' => 'Recently assigned task with a later due date',
            'due_date' => '2026-12-10',
        ]);

        DB::table('maintenance_tasks')->where('id', $olderTask->id)->update([
            'created_at' => '2026-11-01 09:00:00',
            'updated_at' => '2026-11-01 09:00:00',
        ]);
        DB::table('maintenance_tasks')->where('id', $recentTask->id)->update([
            'created_at' => '2026-11-02 09:00:00',
            'updated_at' => '2026-11-02 09:00:00',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/maintenance-tasks')
            ->assertOk()
            ->assertJsonPath('data.0.id', $recentTask->id)
            ->assertJsonPath('data.1.id', $olderTask->id);
    }

    public function test_invalid_task_assignees_assets_and_roles_are_rejected(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $inactiveUser = User::factory()->create(['role' => 'maintenance', 'is_active' => false]);
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', array_merge($this->taskData($asset, $maintenanceUser), [
                'assigned_to' => $inactiveUser->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);

        $otherRoleUser = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', array_merge($this->taskData($asset, $maintenanceUser), [
                'assigned_to' => $otherRoleUser->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);

        $disposedAsset = $this->createAsset(['status' => 'Disposed']);
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', $this->taskData($disposedAsset, $maintenanceUser))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_id']);

        $asset->delete();
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', $this->taskData($asset, $maintenanceUser))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_id']);
    }

    public function test_only_admin_can_assign_edit_or_cancel_tasks_and_guests_are_rejected(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $task = $this->createTask($asset, $maintenanceUser, $admin);
        $anotherMaintenanceUser = User::factory()->create(['role' => 'maintenance']);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->postJson('/api/maintenance-tasks', $this->taskData($asset, $maintenanceUser))
            ->assertForbidden();
        $this->actingAs($maintenanceUser, 'sanctum')
            ->deleteJson("/api/maintenance-tasks/{$task->id}")
            ->assertForbidden();
        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$task->id}", ['assigned_to' => $anotherMaintenanceUser->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', $this->taskData($asset, $maintenanceUser))
            ->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/maintenance-tasks')->assertUnauthorized();
        $this->postJson('/api/maintenance-tasks', $this->taskData($asset, $maintenanceUser))->assertUnauthorized();
    }

    private function makeTaskActors(array $assetOverrides = []): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $maintenanceUser = User::factory()->create(['role' => 'maintenance']);

        return [$admin, $maintenanceUser, $this->createAsset($assetOverrides)];
    }

    private function createAsset(array $overrides = []): Asset
    {
        $category = Category::firstOrCreate(['name' => 'Task Equipment']);
        $location = Location::firstOrCreate(['name' => 'Task Office']);

        return Asset::create(array_merge([
            'asset_tag' => 'TASK-ASSET-'.uniqid(),
            'asset_name' => 'Office Printer',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'description' => 'Printer for maintenance task tests.',
            'purchase_date' => now()->subYear()->toDateString(),
            'purchase_cost' => 12000,
            'condition' => 'Good',
            'status' => 'Available',
        ], $overrides));
    }

    private function taskData(Asset $asset, User $assignee): array
    {
        return [
            'asset_id' => $asset->id,
            'assigned_to' => $assignee->id,
            'title' => 'Inspect printer',
            'instructions' => 'Check the paper feed and test printing.',
            'priority' => 'High',
            'due_date' => now()->addDays(5)->toDateString(),
        ];
    }

    private function createTask(
        Asset $asset,
        User $assignee,
        User $assigner,
        array $overrides = [],
    ): MaintenanceTask {
        return MaintenanceTask::create(array_merge([
            'asset_id' => $asset->id,
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'title' => 'Inspect printer',
            'instructions' => 'Check the paper feed.',
            'priority' => 'Medium',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => MaintenanceTask::STATUS_ASSIGNED,
        ], $overrides));
    }
}
