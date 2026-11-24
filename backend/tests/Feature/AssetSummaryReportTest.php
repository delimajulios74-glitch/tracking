<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_a_purchase_date_filtered_category_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ict = Category::create(['name' => 'ICT Equipment']);
        $office = Category::create(['name' => 'Office Equipment']);
        $location = Location::create(['name' => 'Office A']);

        $this->createAsset($ict, $location, '2025-05-01', 'In Use');
        $this->createAsset($ict, $location, '2025-05-05', 'Under Maintenance');
        $this->createAsset($ict, $location, '2025-05-10', 'Disposed');
        $this->createAsset($ict, $location, '2025-05-03', 'Available');
        $this->createAsset($office, $location, '2025-04-30', 'In Use');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/asset-summary?date_from=2025-05-01&date_to=2025-05-10')
            ->assertOk()
            ->assertJsonPath('data.summary.total_assets', 4)
            ->assertJsonPath('data.summary.in_use', 1)
            ->assertJsonPath('data.summary.under_maintenance', 1)
            ->assertJsonPath('data.summary.out_of_service', 1)
            ->assertJsonPath('data.summary.available', 1)
            ->assertJsonPath('data.categories.0.category', 'ICT Equipment')
            ->assertJsonPath('data.categories.0.total', 4)
            ->assertJsonPath('data.categories.0.in_use', 1)
            ->assertJsonPath('data.categories.0.under_maintenance', 1)
            ->assertJsonPath('data.categories.0.out_of_service', 1)
            ->assertJsonCount(1, 'data.categories');
    }

    public function test_report_defaults_to_all_assets_through_today_when_dates_are_omitted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'ICT Equipment']);
        $location = Location::create(['name' => 'Office A']);

        $this->createAsset($category, $location, '2024-01-15', 'In Use');
        $this->createAsset($category, $location, '2025-10-10', 'Available');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/asset-summary')
            ->assertOk()
            ->assertJsonPath('data.date_from', '2024-01-15')
            ->assertJsonPath('data.date_to', now()->toDateString())
            ->assertJsonPath('data.summary.total_assets', 2)
            ->assertJsonPath('data.categories.0.total', 2);
    }

    public function test_report_requires_valid_ordered_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/asset-summary?date_from=invalid&date_to=2025-05-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/asset-summary?date_from=2025-05-10&date_to=2025-05-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_only_admins_can_access_the_asset_summary_report(): void
    {
        foreach (['maintenance', 'end_user'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/reports/asset-summary?date_from=2025-05-01&date_to=2025-05-10')
                ->assertForbidden();
        }

        $this->getJson('/api/reports/asset-summary?date_from=2025-05-01&date_to=2025-05-10')
            ->assertForbidden();
    }

    private function createAsset(Category $category, Location $location, string $purchaseDate, string $status): Asset
    {
        return Asset::create([
            'asset_tag' => 'RPT-'.uniqid(),
            'asset_name' => 'Report test asset',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'description' => 'Asset summary report test.',
            'purchase_date' => $purchaseDate,
            'purchase_cost' => 100,
            'condition' => 'Good',
            'status' => $status,
        ]);
    }
}
