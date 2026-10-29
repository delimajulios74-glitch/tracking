<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_reassign_edit_and_cancel_tasks_with_activity_history(): void
    {
        [$admin, $firstMaintenanceUser, $asset] = $this->makeTaskActors();

        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', $this->taskData($asset, $firstMaintenanceUser))
            ->assertCreated()
            ->assertJsonPath('data.asset_id', $asset->id)
            ->assertJsonPath('data.assignee.id', $firstMaintenanceUser->id)
            ->assertJsonPath('data.priority', 'High')
            ->assertJsonPath('data.status', 'Pending');

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

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/maintenance-tasks', $this->taskData($asset, $maintenanceUser))
            ->assertCreated();

        $this->assertSame('Under Maintenance', $asset->fresh()->status);
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
                'status' => 'In Progress',
                'problem' => 'Paper jams while printing.',
                'remarks' => 'Needs a replacement roller.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'In Progress')
            ->assertJsonPath('data.problem', 'Paper jams while printing.')
            ->assertJsonPath('data.remarks', 'Needs a replacement roller.');

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

    public function test_task_status_completion_requires_problem_and_completed_tasks_are_locked_for_assignee(): void
    {
        [$admin, $maintenanceUser, $asset] = $this->makeTaskActors();
        $task = $this->createTask($asset, $maintenanceUser, $admin, ['status' => 'In Progress']);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$task->id}", [
                'status' => 'Completed',
                'problem' => '   ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['problem']);

        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$task->id}", [
                'status' => 'Completed',
                'problem' => 'The printer paper feed was obstructed.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Completed');

        $this->assertNotNull($task->fresh()->completed_at);
        $this->actingAs($maintenanceUser, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$task->id}", ['remarks' => 'No longer editable'])
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/maintenance-tasks/{$task->id}", ['status' => 'In Progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'In Progress');

        $this->assertNull($task->fresh()->completed_at);
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
            ->getJson('/api/maintenance-tasks?q=printer&priority=Urgent&assigned_to='.$maintenanceUser->id.'&due_date_from='.now()->addDay()->toDateString().'&due_date_to='.now()->addDays(5)->toDateString().'&per_page=1')
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
            'status' => 'Pending',
        ], $overrides));
    }
}
