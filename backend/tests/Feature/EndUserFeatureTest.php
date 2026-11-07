<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EndUserFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_user_can_search_equipment_without_receiving_financial_or_disposal_fields(): void
    {
        $user = User::factory()->create(['role' => 'end_user']);
        $asset = $this->createAsset(['asset_name' => 'Office Printer']);
        $this->createAsset(['asset_name' => 'Disposed Printer', 'status' => 'Disposed']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/end-user/equipment?q=Printer&status=Available')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.asset_tag', $asset->asset_tag)
            ->assertJsonPath('data.0.category', 'Equipment')
            ->assertJsonPath('data.0.location', 'Office A')
            ->assertJsonMissingPath('data.0.purchase_date')
            ->assertJsonMissingPath('data.0.purchase_cost')
            ->assertJsonMissingPath('data.0.remarks');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/end-user/equipment/{$asset->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.purchase_cost')
            ->assertJsonMissingPath('data.purchase_date')
            ->assertJsonMissingPath('data.remarks');
    }

    public function test_end_user_cannot_view_disposed_equipment_or_access_admin_and_maintenance_routes(): void
    {
        $user = User::factory()->create(['role' => 'end_user']);
        $disposedAsset = $this->createAsset(['status' => 'Disposed']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/end-user/equipment/{$disposedAsset->id}")
            ->assertNotFound();

        foreach ([
            '/api/assets',
            '/api/users',
            '/api/maintenance-logs',
            '/api/maintenance-tasks',
        ] as $path) {
            $this->actingAs($user, 'sanctum')->getJson($path)->assertForbidden();
        }

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/assets', ['asset_name' => 'Not allowed'])
            ->assertForbidden();
    }

    public function test_end_user_can_submit_reports_with_and_without_a_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'end_user']);
        $asset = $this->createAsset();

        $this->actingAs($user, 'sanctum')
            ->post('/api/end-user/reports', [
                'asset_id' => $asset->id,
                'location_id' => $asset->location_id,
                'description' => 'The power cable is damaged.',
                'photo' => UploadedFile::fake()->create('damage.jpg', 100, 'image/jpeg'),
                'reported_by' => 99999,
                'status' => 'resolved',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.type', 'damage')
            ->assertJsonPath('data.asset.id', $asset->id);

        $report = AssetRequest::firstOrFail();
        $this->assertSame($user->id, $report->reported_by);
        $this->assertStringStartsWith('RPT-'.now()->format('Y').'-', $report->reference_no);
        $this->assertNotNull($report->photo_path);
        Storage::disk('public')->assertExists($report->photo_path);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'action' => 'asset_request_submitted',
        ]);
        $this->actingAs($user, 'sanctum')
            ->get("/api/end-user/reports/{$report->id}/photo")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->post('/api/end-user/reports', [
                'asset_id' => $asset->id,
                'location_id' => $asset->location_id,
                'description' => 'The printer is not powering on.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.photo_url', null);
    }

    public function test_end_user_cannot_read_another_users_report(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $otherUser = User::factory()->create(['role' => 'end_user']);
        $asset = $this->createAsset();
        $report = AssetRequest::create([
            'reference_no' => 'RPT-2026-OTHER',
            'asset_id' => $asset->id,
            'location_id' => $asset->location_id,
            'reported_by' => $owner->id,
            'type' => 'damage',
            'description' => 'Private report details.',
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/end-user/reports/{$report->id}")
            ->assertNotFound()
            ->assertJsonMissing(['description' => 'Private report details.']);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/end-user/reports')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson("/api/end-user/reports/{$report->id}/photo")
            ->assertNotFound();
    }

    public function test_report_submission_validates_required_fields_and_photo_type(): void
    {
        $user = User::factory()->create(['role' => 'end_user']);
        $asset = $this->createAsset();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/end-user/reports', [
                'asset_id' => $asset->id,
                'location_id' => $asset->location_id,
                'photo' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description', 'photo']);

        $this->assertDatabaseCount('asset_requests', 0);
    }

    public function test_end_user_can_update_name_and_change_password_without_editing_sensitive_account_fields(): void
    {
        $user = User::factory()->create([
            'role' => 'end_user',
            'username' => 'end.user',
            'password' => 'old-password',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account', [
                'name' => 'Updated End User',
                'username' => 'changed.username',
                'role' => 'admin',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated End User')
            ->assertJsonPath('data.username', 'end.user')
            ->assertJsonPath('data.role', 'end_user')
            ->assertJsonPath('data.is_active', true);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/password', [
                'current_password' => 'incorrect',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/account/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'asset_id' => null,
            'action' => 'password_changed',
        ]);
    }

    public function test_end_user_login_is_recorded_in_activity_logs(): void
    {
        User::factory()->create([
            'role' => 'end_user',
            'username' => 'end.user',
            'password' => 'password123',
        ]);

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/login', [
            'username' => 'end.user',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'login',
            'description' => 'End User signed in.',
            'asset_id' => null,
        ]);
    }

    private function createAsset(array $overrides = []): Asset
    {
        $category = Category::firstOrCreate(['name' => 'Equipment']);
        $location = Location::firstOrCreate(['name' => 'Office A']);

        return Asset::create(array_merge([
            'asset_tag' => 'PR-'.uniqid(),
            'asset_name' => 'Laptop',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'description' => 'Municipal equipment.',
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 35000,
            'condition' => 'Good',
            'status' => 'Available',
            'remarks' => 'Internal note.',
        ], $overrides));
    }
}
