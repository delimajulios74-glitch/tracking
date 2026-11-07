<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_asset_summary_locations_and_recent_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Equipment']);
        $location = Location::create(['name' => 'Municipal Office']);
        $asset = $this->createAsset($category, $location, 'In Use');
        $this->createAsset($category, $location, 'Under Maintenance');
        ActivityLog::create([
            'user_id' => $admin->id,
            'asset_id' => $asset->id,
            'action' => 'updated',
            'description' => 'Updated asset details.',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.total_assets', 2)
            ->assertJsonPath('data.summary.in_use', 1)
            ->assertJsonPath('data.summary.under_maintenance', 1)
            ->assertJsonPath('data.summary.out_of_service', 0)
            ->assertJsonPath('data.locations.0.name', 'Municipal Office')
            ->assertJsonPath('data.locations.0.total', 2)
            ->assertJsonPath('data.activity.0.description', 'Updated asset details.')
            ->assertJsonPath('data.activity.0.user_name', $admin->name);
    }

    public function test_dashboard_is_available_only_to_admins(): void
    {
        foreach (['maintenance', 'end_user'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/admin/dashboard')
                ->assertForbidden();
        }

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    private function createAsset(Category $category, Location $location, string $status): Asset
    {
        return Asset::create([
            'asset_tag' => 'DASH-'.uniqid(),
            'asset_name' => 'Dashboard test asset',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'description' => 'Admin dashboard feature test.',
            'purchase_date' => '2025-05-01',
            'purchase_cost' => 100,
            'condition' => 'Good',
            'status' => $status,
        ]);
    }
}
