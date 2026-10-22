<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_users_can_view_assets_but_cannot_use_admin_crud_routes(): void
    {
        $maintenanceUser = User::factory()->create(['role' => 'maintenance']);
        $asset = $this->createAsset();

        $this->actingAs($maintenanceUser, 'web')
            ->getJson('/api/assets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $asset->id);

        $this->actingAs($maintenanceUser, 'web')
            ->getJson("/api/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $asset->id);

        $this->actingAs($maintenanceUser, 'web')
            ->postJson('/api/assets', $this->assetData())
            ->assertForbidden();

        $this->actingAs($maintenanceUser, 'web')
            ->patchJson("/api/assets/{$asset->id}", ['asset_name' => 'Changed'])
            ->assertForbidden();

        $this->actingAs($maintenanceUser, 'web')
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertForbidden();

        $this->assertSame('Laptop', $asset->fresh()->asset_name);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_maintenance_users_can_update_only_condition_for_assets_under_maintenance(): void
    {
        $maintenanceUser = User::factory()->create(['role' => 'maintenance']);
        $asset = $this->createAsset(['status' => 'Under Maintenance']);

        $this->actingAs($maintenanceUser, 'web')
            ->patchJson("/api/assets/{$asset->id}/condition", [
                'condition' => 'Poor',
                'asset_name' => 'Must not change',
                'status' => 'Available',
            ])
            ->assertOk()
            ->assertJsonPath('data.condition', 'Poor');

        $this->assertSame('Laptop', $asset->fresh()->asset_name);
        $this->assertSame('Under Maintenance', $asset->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'user_id' => $maintenanceUser->id,
            'action' => 'condition changed',
            'description' => 'Condition changed from "Good" to "Poor".',
        ]);
    }

    public function test_maintenance_users_cannot_change_condition_on_assets_not_under_maintenance(): void
    {
        $maintenanceUser = User::factory()->create(['role' => 'maintenance']);
        $asset = $this->createAsset();

        $this->actingAs($maintenanceUser, 'web')
            ->patchJson("/api/assets/{$asset->id}/condition", ['condition' => 'Poor'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset']);

        $this->assertSame('Good', $asset->fresh()->condition);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_guests_cannot_access_asset_routes(): void
    {
        $asset = $this->createAsset();

        $this->getJson('/api/assets')->assertUnauthorized();
        $this->getJson("/api/assets/{$asset->id}")->assertUnauthorized();
        $this->patchJson("/api/assets/{$asset->id}/condition", ['condition' => 'Poor'])->assertUnauthorized();
    }

    public function test_authenticated_users_can_load_asset_form_options(): void
    {
        $user = User::factory()->create(['role' => 'maintenance']);

        $this->actingAs($user, 'web')
            ->getJson('/api/asset-options')
            ->assertOk()
            ->assertJsonPath('data.conditions.0', 'Good')
            ->assertJsonPath('data.statuses.0', 'Available');
    }

    private function createAsset(array $overrides = []): Asset
    {
        $category = Category::create(['name' => 'Equipment']);
        $location = Location::create(['name' => 'Office A']);

        return Asset::create(array_merge($this->assetData($category, $location), $overrides));
    }

    private function assetData(?Category $category = null, ?Location $location = null): array
    {
        $category ??= Category::firstOrCreate(['name' => 'Equipment']);
        $location ??= Location::firstOrCreate(['name' => 'Office A']);

        return [
            'asset_tag' => 'ASSET-'.uniqid(),
            'asset_name' => 'Laptop',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'description' => 'Municipal office laptop',
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 35000,
            'condition' => 'Good',
            'status' => 'Available',
        ];
    }
}
