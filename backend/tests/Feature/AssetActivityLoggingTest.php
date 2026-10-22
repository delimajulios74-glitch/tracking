<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_create_update_and_delete_are_logged_by_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Equipment']);
        $firstLocation = Location::create(['name' => 'Office A']);
        $secondLocation = Location::create(['name' => 'Office B']);

        $response = $this->actingAs($user, 'web')
            ->postJson('/api/assets', $this->assetData($category, $firstLocation))
            ->assertCreated()
            ->assertJsonPath('data.asset_tag', 'ASSET-001');

        $asset = Asset::findOrFail($response->json('data.id'));

        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'created',
            'description' => 'Created asset ASSET-001.',
        ]);

        $this->actingAs($user, 'web')
            ->patchJson("/api/assets/{$asset->id}", [
                'location_id' => $secondLocation->id,
                'condition' => 'Fair',
                'status' => 'In Use',
            ])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'location changed',
            'description' => 'Location changed from "Office A" to "Office B".',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'condition changed',
            'description' => 'Condition changed from "Good" to "Fair".',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'status changed',
            'description' => 'Status changed from "Available" to "In Use".',
        ]);

        $this->actingAs($user, 'web')
            ->getJson("/api/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('data.activity_logs.0.user.name', $user->name);

        $this->actingAs($user, 'web')
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertOk();

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('activity_logs', [
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'action' => 'deleted',
        ]);
    }

    private function assetData(Category $category, Location $location): array
    {
        return [
            'asset_tag' => 'ASSET-001',
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
