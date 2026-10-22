<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_creation_requires_valid_asset_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'web')
            ->postJson('/api/assets', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'asset_tag',
                'asset_name',
                'category_id',
                'location_id',
                'description',
                'purchase_date',
                'purchase_cost',
                'condition',
                'status',
            ]);

        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_asset_creation_rejects_duplicate_tags_invalid_lookups_and_disallowed_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Equipment']);
        $location = Location::create(['name' => 'Office A']);
        $this->createAsset($category, $location);

        $this->actingAs($admin, 'web')
            ->postJson('/api/assets', [
                ...$this->assetData($category, $location),
                'asset_tag' => 'ASSET-DUPLICATE',
                'category_id' => 999,
                'condition' => 'Unknown',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_tag', 'category_id', 'condition']);

        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_asset_update_rejects_invalid_values_without_mutating_asset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Equipment']);
        $location = Location::create(['name' => 'Office A']);
        $asset = $this->createAsset($category, $location);

        $this->actingAs($admin, 'web')
            ->patchJson("/api/assets/{$asset->id}", [
                'purchase_cost' => -1,
                'status' => 'Unknown',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purchase_cost', 'status']);

        $this->assertSame('Available', $asset->fresh()->status);
        $this->assertSame('35000.00', $asset->fresh()->purchase_cost);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    private function createAsset(Category $category, Location $location): Asset
    {
        return Asset::create($this->assetData($category, $location));
    }

    private function assetData(Category $category, Location $location): array
    {
        return [
            'asset_tag' => 'ASSET-DUPLICATE',
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
