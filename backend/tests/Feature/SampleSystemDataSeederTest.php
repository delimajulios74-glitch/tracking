<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceTask;
use App\Models\User;
use Database\Seeders\SampleSystemDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SampleSystemDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_data_seeder_is_complete_and_repeatable(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        User::factory()->create(['role' => 'maintenance', 'is_active' => true]);
        User::factory()->create(['role' => 'end_user', 'is_active' => true]);

        $seeder = app(SampleSystemDataSeeder::class);
        $seeder->run();
        $seeder->run();

        $this->assertSame(200, Asset::query()->where('asset_tag', 'like', 'DEMO-LGU-2026-%')->count());
        $this->assertSame(30, AssetRequest::query()->where('reference_no', 'like', 'SAMPLE-RPT-2026-%')->count());
        $this->assertSame(30, MaintenanceTask::query()->where('title', 'like', '[SAMPLE DATA] SAMPLE-MNT-2026-%')->count());
        $this->assertSame(30, MaintenanceLog::query()->where('remarks', 'like', '[SAMPLE DATA]%')->count());
        $this->assertSame(20, MaintenanceTask::query()
            ->where('title', 'like', '[SAMPLE DATA] SAMPLE-MNT-2026-%')
            ->where('status', 'Completed')
            ->count());
        $this->assertSame(5, MaintenanceTask::query()
            ->where('title', 'like', '[SAMPLE DATA] SAMPLE-MNT-2026-%')
            ->where('status', 'In Progress')
            ->count());
        $this->assertSame(5, MaintenanceTask::query()
            ->where('title', 'like', '[SAMPLE DATA] SAMPLE-MNT-2026-%')
            ->where('status', 'Pending')
            ->count());
        $this->assertSame(30, AssetRequest::query()
            ->where('reference_no', 'like', 'SAMPLE-RPT-2026-%')
            ->whereIn('status', ['in_review', 'resolved'])
            ->count());
        $this->assertSame(75, ActivityLog::query()->where('description', 'like', '[SAMPLE DATA]%')->count());
        $this->assertSame(30, ActivityLog::query()->where('action', 'end_user_report_linked')->count());
        $this->assertSame(200, Asset::query()
            ->where('asset_tag', 'like', 'DEMO-LGU-2026-%')
            ->distinct()
            ->count('asset_tag'));
        $this->assertSame(10, Category::query()
            ->whereHas('assets', fn ($query) => $query->where('asset_tag', 'like', 'DEMO-LGU-2026-%'))
            ->count());
        $this->assertSame(0, Asset::query()
            ->where('asset_tag', 'like', 'DEMO-LGU-2026-%')
            ->whereNotIn('condition', config('assets.conditions'))
            ->orWhere(function ($query): void {
                $query->where('asset_tag', 'like', 'DEMO-LGU-2026-%')
                    ->whereNotIn('status', config('assets.statuses'));
            })
            ->count());
    }

    public function test_sample_data_seeder_requires_existing_active_role_accounts_before_writing(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);

        try {
            app(SampleSystemDataSeeder::class)->run();
            $this->fail('The seeder should require existing maintenance and end-user accounts.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('active admin, maintenance user, and end_user', $exception->getMessage());
        }

        $this->assertSame(0, Asset::query()->count());
        $this->assertSame(0, AssetRequest::query()->count());
    }

    public function test_sample_data_seeder_stops_before_writing_when_a_reserved_asset_tag_is_in_use(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        User::factory()->create(['role' => 'maintenance', 'is_active' => true]);
        User::factory()->create(['role' => 'end_user', 'is_active' => true]);
        $category = Category::create(['name' => 'Existing category']);
        $location = Location::create(['name' => 'Existing location']);
        $existingAsset = Asset::create([
            'asset_tag' => 'DEMO-LGU-2026-0001',
            'asset_name' => 'Existing real asset',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'description' => 'Existing record that must not be changed.',
            'purchase_date' => '2025-01-01',
            'purchase_cost' => 1000,
            'condition' => 'Good',
            'status' => 'Available',
            'remarks' => 'Real record.',
        ]);

        try {
            app(SampleSystemDataSeeder::class)->run();
            $this->fail('The seeder should stop on a reserved property-number collision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already used by a non-sample asset', $exception->getMessage());
        }

        $this->assertSame('Existing real asset', $existingAsset->fresh()->asset_name);
        $this->assertSame(1, Asset::query()->count());
        $this->assertSame(0, AssetRequest::query()->count());
        $this->assertSame(0, MaintenanceTask::query()->count());
    }
}
