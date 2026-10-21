<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'maintenance.user',
            'password' => 'password123',
        ]);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/login', [
                'username' => 'MAINTENANCE.USER',
                'password' => 'password123',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'maintenance')
            ->assertJsonMissingPath('data.password');
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        User::factory()->create([
            'username' => 'inactive.user',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/login', [
                'username' => 'inactive.user',
                'password' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_only_admins_can_create_maintenance_users_and_roles_are_not_request_controlled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'web')
            ->withHeaders($this->spaHeaders())
            ->postJson('/api/users', [
                'username' => 'new.maintenance',
                'name' => 'New Maintenance',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'admin',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'maintenance');

        $createdUser = User::where('username', 'new.maintenance')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $createdUser->password));
    }

    public function test_maintenance_users_cannot_create_accounts(): void
    {
        $maintenanceUser = User::factory()->create();

        $this->actingAs($maintenanceUser, 'web')
            ->withHeaders($this->spaHeaders())
            ->postJson('/api/users', [
                'username' => 'blocked.user',
                'name' => 'Blocked User',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'blocked.user']);
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->withHeaders($this->spaHeaders())
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_seeder_creates_property_custodian_with_a_hashed_password(): void
    {
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('secret')
            ->once()
            ->with('Enter a password for the admin account (minimum 8 characters)')
            ->andReturn('private-password');
        $command->shouldReceive('secret')
            ->once()
            ->with('Confirm the admin password')
            ->andReturn('private-password');

        app(AdminUserSeeder::class)
            ->setCommand($command)
            ->run();

        $admin = User::where('username', 'admin')->firstOrFail();
        $this->assertSame('Property Custodian', $admin->name);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('private-password', $admin->password));
    }

    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ];
    }
}
