<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_read_update_and_delete_maintenance_logs_with_activity_entries(): void
    {
        [$user, $asset] = $this->adminAndAsset();

        $created = $this->actingAs($user, 'web')
            ->postJson("/api/assets/{$asset->id}/maintenance-logs", $this->logData())
            ->assertCreated()
            ->assertJsonPath('data.asset_id', $asset->id)
            ->assertJsonPath('data.maintenance_type', 'Repair')
            ->assertJsonPath('data.asset.asset_tag', $asset->asset_tag);

        $logId = $created->json('data.id');
        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'maintenance_logged',
        ]);

        $this->actingAs($user, 'web')
            ->getJson("/api/maintenance-logs/{$logId}")
            ->assertOk()
            ->assertJsonPath('data.description_of_work', 'Replaced a worn component.');

        $this->actingAs($user, 'web')
            ->patchJson("/api/maintenance-logs/{$logId}", [
                'maintenance_type' => 'Preventive',
                'date' => now()->subDay()->toDateString(),
                'performed_by' => 'Facilities team',
                'cost' => 150,
            ])
            ->assertOk()
            ->assertJsonPath('data.maintenance_type', 'Preventive');

        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'maintenance_updated',
        ]);
        $updateDescription = DB::table('activity_logs')
            ->where('asset_id', $asset->id)
            ->where('action', 'maintenance_updated')
            ->value('description');
        $this->assertStringContainsString('Repair', $updateDescription);
        $this->assertStringContainsString('->', $updateDescription);

        $this->actingAs($user, 'web')
            ->deleteJson("/api/maintenance-logs/{$logId}")
            ->assertOk();

        $this->assertDatabaseMissing('maintenance_logs', ['id' => $logId]);
        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'maintenance_deleted',
        ]);
    }

    public function test_maintenance_logs_can_be_filtered_searched_and_paginated(): void
    {
        [$user, $asset] = $this->adminAndAsset();
        $matching = MaintenanceLog::create(array_merge($this->logData(), [
            'asset_id' => $asset->id,
            'description_of_work' => 'Replaced a damaged fan.',
            'date' => now()->subDays(2)->toDateString(),
            'cost' => 80,
        ]));
        MaintenanceLog::create(array_merge($this->logData(), [
            'asset_id' => $asset->id,
            'maintenance_type' => 'Inspection',
            'description_of_work' => 'Checked equipment condition.',
            'date' => now()->subDays(10)->toDateString(),
            'cost' => 0,
        ]));

        $this->actingAs($user, 'web')
            ->getJson('/api/maintenance-logs?q=damaged&asset_id='.$asset->id.'&maintenance_type=Repair&date_from='.now()->subDays(5)->toDateString().'&cost_min=50&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 1);

        $this->actingAs($user, 'web')
            ->getJson('/api/maintenance-logs?date_to='.now()->toDateString().'&cost_max=200')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($user, 'web')
            ->getJson("/api/assets/{$asset->id}/maintenance-logs")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.maintenance_cost_total', '80.00');

        $this->actingAs($user, 'web')
            ->getJson('/api/maintenance-types')
            ->assertOk()
            ->assertJsonPath('data', ['Preventive', 'Repair', 'Inspection', 'Cleaning']);
    }

    public function test_invalid_log_data_and_deleted_assets_are_rejected(): void
    {
        [$user, $asset] = $this->adminAndAsset();

        $this->actingAs($user, 'web')
            ->postJson("/api/assets/{$asset->id}/maintenance-logs", [
                'maintenance_type' => 'Emergency',
                'date' => now()->addDay()->toDateString(),
                'performed_by' => '',
                'description_of_work' => '',
                'cost' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['maintenance_type', 'date', 'performed_by', 'description_of_work', 'cost']);

        $this->actingAs($user, 'web')
            ->postJson('/api/assets/999999/maintenance-logs', $this->logData())
            ->assertNotFound();

        $asset->delete();
        $this->actingAs($user, 'web')
            ->postJson("/api/assets/{$asset->id}/maintenance-logs", $this->logData())
            ->assertNotFound();
    }

    public function test_soft_deleted_assets_keep_their_maintenance_history_intact(): void
    {
        [$user, $asset] = $this->adminAndAsset();
        $log = MaintenanceLog::create(array_merge($this->logData(), ['asset_id' => $asset->id]));
        $asset->delete();

        $this->actingAs($user, 'web')
            ->getJson('/api/maintenance-logs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $log->id)
            ->assertJsonPath('data.0.asset.id', $asset->id)
            ->assertJsonPath('data.0.asset.deleted_at', $asset->fresh()->deleted_at->toJSON());

        $this->assertDatabaseHas('maintenance_logs', ['id' => $log->id, 'asset_id' => $asset->id]);
    }

    public function test_maintenance_role_cannot_write_logs(): void
    {
        $user = User::factory()->create(['role' => 'maintenance']);
        [, $asset] = $this->adminAndAsset();
        $log = MaintenanceLog::create(array_merge($this->logData(), ['asset_id' => $asset->id]));

        $this->actingAs($user, 'web')
            ->postJson("/api/assets/{$asset->id}/maintenance-logs", $this->logData())
            ->assertForbidden();
        $this->actingAs($user, 'web')
            ->patchJson("/api/maintenance-logs/{$log->id}", ['remarks' => 'Not allowed'])
            ->assertForbidden();
        $this->actingAs($user, 'web')
            ->deleteJson("/api/maintenance-logs/{$log->id}")
            ->assertForbidden();
    }

    private function adminAndAsset(): array
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Equipment']);
        $location = Location::create(['name' => 'Office A']);
        $asset = Asset::create([
            'asset_tag' => 'ASSET-MAINT-001',
            'asset_name' => 'Printer',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'description' => 'Office printer',
            'purchase_date' => now()->subYear()->toDateString(),
            'purchase_cost' => 12000,
            'condition' => 'Good',
            'status' => 'Available',
        ]);

        return [$user, $asset];
    }

    private function logData(): array
    {
        return [
            'maintenance_type' => 'Repair',
            'date' => now()->subDays(3)->toDateString(),
            'performed_by' => 'Service provider',
            'description_of_work' => 'Replaced a worn component.',
            'parts_replaced' => 'Roller',
            'cost' => 100,
            'remarks' => 'Operational after repair.',
        ];
    }
}
