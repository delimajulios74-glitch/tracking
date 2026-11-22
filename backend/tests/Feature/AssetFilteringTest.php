<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_can_be_searched_filtered_sorted_and_paginated(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Equipment']);
        $location = Location::create(['name' => 'Office A']);

        $matchingAsset = $this->createAsset($category, $location, [
            'asset_tag' => 'ASSET-002',
            'brand' => 'Dell',
            'purchase_date' => '2026-03-10',
        ]);
        $this->createAsset($category, $location, [
            'asset_tag' => 'ASSET-001',
            'brand' => 'HP',
            'purchase_date' => '2026-03-11',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/assets?q=DELL&category_id='.$category->id.'&location_id='.$location->id.'&status=Available&condition=Good&purchase_date_from=2026-03-01&purchase_date_to=2026-03-31&sort_by=asset_tag&sort_direction=asc&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $matchingAsset->id)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_asset_search_matches_partial_case_insensitive_asset_and_lookup_fields_without_duplicates(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Office Equipment']);
        $location = Location::create(['name' => 'Main Office', 'building' => 'North Annex']);
        $asset = $this->createAsset($category, $location, [
            'asset_tag' => 'TRN-2026-001',
            'asset_name' => 'Desktop Terminal',
            'description' => 'Finance department workstation',
            'serial_number' => 'SERIAL-ABC-123',
        ]);
        $otherCategory = Category::create(['name' => 'Furniture']);
        $otherLocation = Location::create(['name' => 'Warehouse']);
        $this->createAsset($otherCategory, $otherLocation, [
            'asset_tag' => 'TRN-2026-002',
            'asset_name' => 'Office chair',
        ]);

        foreach ([
            '026-001',
            'DESKTOP term',
            'FINANCE depart',
            'office equip',
            'main off',
            'north annex',
            'abc-123',
        ] as $keyword) {
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/assets?q='.rawurlencode($keyword))
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $asset->id);
        }

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/assets?q=office')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/assets?q=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/assets?q=no-matching-asset')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_asset_list_is_sorted_and_paginated_by_requested_page_size(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Equipment']);
        $location = Location::create(['name' => 'Office A']);

        $this->createAsset($category, $location, ['asset_tag' => 'ASSET-002']);
        $this->createAsset($category, $location, ['asset_tag' => 'ASSET-001']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/assets?sort_by=asset_tag&sort_direction=asc&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.asset_tag', 'ASSET-002')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_invalid_asset_filter_values_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/assets?status=Unknown&sort_by=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'sort_by']);
    }

    private function createAsset(Category $category, Location $location, array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'asset_tag' => 'ASSET-DEFAULT',
            'asset_name' => 'Laptop',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'brand' => 'Generic',
            'model' => 'Model 1',
            'serial_number' => 'SERIAL-001',
            'description' => 'Municipal office laptop',
            'room_area' => 'Room 1',
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 35000,
            'condition' => 'Good',
            'status' => 'Available',
        ], $overrides));
    }
}
