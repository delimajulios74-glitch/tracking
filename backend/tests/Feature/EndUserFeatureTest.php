<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\MaintenanceTask;
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
            ->getJson('/api/end-user/equipment?q=pRin&status=Available')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.asset_tag', $asset->asset_tag)
            ->assertJsonPath('data.0.category', 'Equipment')
            ->assertJsonPath('data.0.location', 'Office A')
            ->assertJsonMissingPath('data.0.purchase_date')
            ->assertJsonMissingPath('data.0.purchase_cost')
            ->assertJsonMissingPath('data.0.remarks');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/end-user/equipment?q=mUnIcIpAl&status=Available')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $asset->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/end-user/equipment?q=internal')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/end-user/equipment?q=&status=Available')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/end-user/equipment?status=Unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

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
            '/api/asset-requests',
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
                'photo' => UploadedFile::fake()->createWithContent(
                    'damage.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true),
                ),
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
        $userPhotoResponse = $this->actingAs($user, 'sanctum')
            ->get("/api/end-user/reports/{$report->id}/photo")
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('cache-control', 'max-age=0, must-revalidate, no-store, private');
        $this->assertSame(
            Storage::disk('public')->get($report->photo_path),
            $userPhotoResponse->streamedContent(),
        );

        $this->actingAs($user, 'sanctum')
            ->get("/api/asset-requests/{$report->id}/photo")
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/asset-requests')
            ->assertOk()
            ->assertJsonPath('data.0.photo_url', route('admin.asset-requests.photo', ['assetRequest' => $report->id]));

        $adminPhotoResponse = $this->actingAs($admin, 'sanctum')
            ->get("/api/asset-requests/{$report->id}/photo")
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('cache-control', 'max-age=0, must-revalidate, no-store, private');
        $this->assertSame(
            Storage::disk('public')->get($report->photo_path),
            $adminPhotoResponse->streamedContent(),
        );

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

    public function test_end_user_can_delete_only_their_own_pending_report_and_photo(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'end_user']);
        $otherUser = User::factory()->create(['role' => 'end_user']);
        $asset = $this->createAsset();
        $pendingReport = $this->createReport($owner, $asset, [
            'reference_no' => 'RPT-2026-PENDING-DELETE',
            'photo_path' => 'asset-requests/pending.png',
        ]);
        $inReviewReport = $this->createReport($owner, $asset, [
            'reference_no' => 'RPT-2026-IN-REVIEW',
            'status' => 'in_review',
        ]);
        Storage::disk('public')->put($pendingReport->photo_path, 'photo');

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/end-user/reports/{$pendingReport->id}")
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/end-user/reports/{$inReviewReport->id}")
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/end-user/reports/{$pendingReport->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('asset_requests', ['id' => $pendingReport->id]);
        $this->assertDatabaseHas('asset_requests', ['id' => $inReviewReport->id]);
        Storage::disk('public')->assertMissing('asset-requests/pending.png');
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $owner->id,
            'asset_id' => $asset->id,
            'action' => 'asset_request_deleted',
            'description' => 'Deleted pending report RPT-2026-PENDING-DELETE.',
        ]);
    }

    public function test_admin_can_edit_and_delete_a_report_without_deleting_maintenance_history(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'end_user']);
        $technician = User::factory()->create(['role' => 'maintenance']);
        $asset = $this->createAsset();
        $replacementAsset = $this->createAsset(['asset_name' => 'Office Printer']);
        $location = Location::create(['name' => 'Records Office']);
        $report = $this->createReport($reporter, $asset, [
            'reference_no' => 'RPT-2026-ADMIN-EDIT',
            'photo_path' => 'asset-requests/old.png',
        ]);
        Storage::disk('public')->put($report->photo_path, 'old photo');
        $task = MaintenanceTask::create([
            'asset_id' => $asset->id,
            'assigned_to' => $technician->id,
            'assigned_by' => $admin->id,
            'title' => 'Inspect laptop',
            'status' => 'In Progress',
        ]);
        $linkedHistory = ActivityLog::create([
            'user_id' => $admin->id,
            'asset_id' => $asset->id,
            'maintenance_task_id' => $task->id,
            'action' => 'end_user_report_linked',
            'description' => 'Linked end-user report: '.$report->reference_no.'.',
            'created_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->post("/api/asset-requests/{$report->id}", [
                '_method' => 'PATCH',
                'location_id' => $location->id,
                'description' => 'Corrected report details.',
                'status' => 'in_review',
                'photo' => UploadedFile::fake()->createWithContent(
                    'replacement.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true),
                ),
            ])
            ->assertOk()
            ->assertJsonPath('data.asset.id', $asset->id)
            ->assertJsonPath('data.location_id', $location->id)
            ->assertJsonPath('data.description', 'Corrected report details.')
            ->assertJsonPath('data.status', 'in_review');

        $updatedReport = $report->fresh();
        $this->assertSame('RPT-2026-ADMIN-EDIT', $updatedReport->reference_no);
        $this->assertSame($reporter->id, $updatedReport->reported_by);
        Storage::disk('public')->assertMissing('asset-requests/old.png');
        Storage::disk('public')->assertExists($updatedReport->photo_path);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/asset-requests/{$report->id}", ['asset_id' => $replacementAsset->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_id']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/asset-requests/{$report->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('asset_requests', ['id' => $report->id]);
        $this->assertDatabaseHas('maintenance_tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $linkedHistory->id]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'asset_id' => $asset->id,
            'action' => 'asset_request_deleted',
            'description' => 'Deleted report RPT-2026-ADMIN-EDIT.',
        ]);
        Storage::disk('public')->assertMissing($updatedReport->photo_path);
    }

    public function test_admin_can_correct_an_unlinked_report_asset_and_remove_its_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'end_user']);
        $asset = $this->createAsset();
        $correctAsset = $this->createAsset(['asset_name' => 'Office Printer']);
        $report = $this->createReport($reporter, $asset, [
            'reference_no' => 'RPT-2026-CORRECTED-ASSET',
            'photo_path' => 'asset-requests/remove-me.png',
        ]);
        Storage::disk('public')->put($report->photo_path, 'photo');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/asset-requests/{$report->id}", [
                'asset_id' => $correctAsset->id,
                'remove_photo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.asset.id', $correctAsset->id)
            ->assertJsonPath('data.photo_url', null);

        $this->assertSame($reporter->id, $report->fresh()->reported_by);
        $this->assertSame('RPT-2026-CORRECTED-ASSET', $report->fresh()->reference_no);
        Storage::disk('public')->assertMissing('asset-requests/remove-me.png');
    }

    public function test_non_admin_roles_cannot_edit_or_delete_reports(): void
    {
        $maintenanceUser = User::factory()->create(['role' => 'maintenance']);
        $endUser = User::factory()->create(['role' => 'end_user']);
        $asset = $this->createAsset();
        $report = $this->createReport($endUser, $asset, [
            'reference_no' => 'RPT-2026-ROLE-GUARD',
        ]);

        foreach ([$maintenanceUser, $endUser] as $user) {
            $this->actingAs($user, 'sanctum')
                ->patchJson("/api/asset-requests/{$report->id}", ['description' => 'Not allowed'])
                ->assertForbidden();
            $this->actingAs($user, 'sanctum')
                ->deleteJson("/api/asset-requests/{$report->id}")
                ->assertForbidden();
        }

        $this->assertDatabaseHas('asset_requests', ['id' => $report->id]);
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

    public function test_admin_can_update_own_name_and_change_password_without_changing_role(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'username' => 'admin.user',
            'password' => 'old-password',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/account', [
                'name' => 'Updated Administrator',
                'role' => 'maintenance',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Administrator')
            ->assertJsonPath('data.role', 'admin');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/account/password', [
                'current_password' => 'old-password',
                'password' => 'new-admin-password',
                'password_confirmation' => 'new-admin-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-admin-password', $admin->fresh()->password));
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

    private function createReport(User $reporter, Asset $asset, array $overrides = []): AssetRequest
    {
        return AssetRequest::create(array_merge([
            'reference_no' => 'RPT-'.uniqid(),
            'asset_id' => $asset->id,
            'location_id' => $asset->location_id,
            'reported_by' => $reporter->id,
            'type' => 'damage',
            'description' => 'Reported equipment damage.',
            'status' => 'pending',
        ], $overrides));
    }
}
