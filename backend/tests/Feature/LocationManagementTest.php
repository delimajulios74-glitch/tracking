<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_locations_with_buildings_and_asset_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $location = Location::create([
            'name' => 'Computer Lab',
            'building' => 'Admin Building',
        ]);
        $this->createAsset($location);
        $this->createAsset($location);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/locations')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Computer Lab')
            ->assertJsonPath('data.0.building', 'Admin Building')
            ->assertJsonPath('data.0.assets_count', 2);
    }

    public function test_admin_can_create_a_location_with_a_building(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/locations', [
                'name' => 'Science Lab',
                'building' => 'Admin Building',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Science Lab')
            ->assertJsonPath('data.building', 'Admin Building')
            ->assertJsonPath('data.assets_count', 0);

        $this->assertDatabaseHas('locations', [
            'name' => 'Science Lab',
            'building' => 'Admin Building',
        ]);
    }

    public function test_location_creation_requires_a_name_and_building(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/locations', ['name' => 'Computer Lab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['building']);

        $this->assertDatabaseMissing('locations', ['name' => 'Computer Lab']);
    }

    public function test_non_admin_users_cannot_manage_locations(): void
    {
        $maintenance = User::factory()->create(['role' => 'maintenance']);

        $this->actingAs($maintenance, 'sanctum')
            ->getJson('/api/locations')
            ->assertForbidden();

        $this->actingAs($maintenance, 'sanctum')
            ->postJson('/api/locations', [
                'name' => 'Restricted Location',
                'building' => 'Admin Building',
            ])
            ->assertForbidden();

        $endUser = User::factory()->create(['role' => 'end_user']);
        $this->actingAs($endUser, 'sanctum')
            ->postJson('/api/locations', [
                'name' => 'Restricted Location',
                'building' => 'Admin Building',
            ])
            ->assertForbidden();
    }

    private function createAsset(Location $location): Asset
    {
        $category = Category::firstOrCreate(['name' => 'Equipment']);

        return Asset::create([
            'asset_tag' => 'LOC-'.uniqid(),
            'asset_name' => 'Test equipment',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'description' => 'Location count test asset.',
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 100,
            'condition' => 'Good',
            'status' => 'Available',
        ]);
    }
}
