<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedPasswordResetNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_queued_notification_with_frontend_reset_url(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://frontend.example.test']);
        $user = User::factory()->create(['email' => 'person@example.test']);

        $response = $this->postJson('/api/forgot-password', ['email' => 'PERSON@example.test']);

        $response->assertOk()->assertExactJson([
            'success' => true,
            'message' => "If that email is registered, you'll receive a reset link shortly.",
        ]);
        Notification::assertSentTo(
            $user,
            QueuedPasswordResetNotification::class,
            function (QueuedPasswordResetNotification $notification) use ($user): bool {
                $url = $notification->toMail($user)->actionUrl;
                $query = [];
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

                return str_starts_with($url, 'https://frontend.example.test/reset-password?')
                    && $query['email'] === $user->email
                    && isset($query['token']);
            },
        );
    }

    public function test_forgot_password_dispatches_a_queued_notification(): void
    {
        Queue::fake();
        User::factory()->create(['email' => 'queued@example.test']);

        $this->postJson('/api/forgot-password', ['email' => 'queued@example.test'])
            ->assertOk();

        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_unknown_and_missing_emails_receive_the_same_generic_response_without_notification(): void
    {
        Notification::fake();
        $registeredUser = User::factory()->create(['email' => 'known@example.test']);
        $userWithoutEmail = User::factory()->create(['email' => null]);

        $knownResponse = $this->postJson('/api/forgot-password', ['email' => 'known@example.test']);
        $throttledBrokerResponse = $this->postJson('/api/forgot-password', ['email' => 'known@example.test']);
        $missingEmailResponse = $this->postJson('/api/forgot-password', ['email' => 'unknown@example.test']);
        $noAccountEmailResponse = $this->postJson('/api/forgot-password', ['email' => 'not-set@example.test']);

        $genericResponse = [
            'success' => true,
            'message' => "If that email is registered, you'll receive a reset link shortly.",
        ];
        $knownResponse->assertOk()->assertExactJson($genericResponse);
        $throttledBrokerResponse->assertOk()->assertExactJson($genericResponse);
        $missingEmailResponse->assertOk()->assertExactJson($genericResponse);
        $noAccountEmailResponse->assertOk()->assertExactJson($genericResponse);
        Notification::assertSentTo($registeredUser, QueuedPasswordResetNotification::class);
        Notification::assertNotSentTo($userWithoutEmail, QueuedPasswordResetNotification::class);
    }

    public function test_valid_reset_changes_password_rotates_remember_token_and_revokes_sessions(): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create([
            'email' => 'reset@example.test',
            'password' => 'old-password',
            'remember_token' => 'old-remember-token',
        ]);
        $token = Password::broker()->createToken($user);
        DB::table('sessions')->insert([
            'id' => 'another-user-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->id,
            'name' => 'test token',
            'token' => hash('sha256', 'test-token'),
            'abilities' => '["*"]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk()->assertJsonPath('success', true);

        $user->refresh();
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'another-user-session']);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        Event::assertDispatched(PasswordReset::class);

        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/login', [
                'username' => $user->username,
                'password' => 'old-password',
            ])
            ->assertUnprocessable();
        $this->withHeaders($this->spaHeaders())
            ->postJson('/api/login', [
                'username' => $user->username,
                'password' => 'new-password',
            ])
            ->assertOk();

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'This reset link is invalid or has expired.');
    }

    public function test_invalid_and_expired_reset_tokens_are_rejected(): void
    {
        $user = User::factory()->create(['email' => 'expired@example.test']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'This reset link is invalid or has expired.');

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 1)]);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'This reset link is invalid or has expired.');
    }

    public function test_forgot_password_is_limited_per_ip(): void
    {
        Notification::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/forgot-password', [
                'email' => "person{$attempt}@example.test",
            ])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'sixth@example.test'])
            ->assertTooManyRequests();
    }

    public function test_admin_must_create_users_with_unique_normalized_emails(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($admin, 'web')
            ->withHeaders($this->spaHeaders())
            ->postJson('/api/users', [
                'username' => 'missing.email',
                'name' => 'Missing Email',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->actingAs($admin, 'web')
            ->withHeaders($this->spaHeaders())
            ->postJson('/api/users', [
                'username' => 'duplicate.email',
                'email' => 'TAKEN@example.test',
                'name' => 'Duplicate Email',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        User::factory()->create(['email' => null]);
        User::factory()->create(['email' => null]);
        $this->assertSame(2, User::query()->whereNull('email')->count());
    }

    public function test_admin_can_add_an_email_to_an_existing_user_and_change_is_audited(): void
    {
        Log::spy();
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['email' => null]);

        $this->actingAs($admin, 'web')
            ->withHeaders($this->spaHeaders())
            ->patchJson("/api/users/{$target->id}", ['email' => '  PERSON@GMAIL.COM  '])
            ->assertOk()
            ->assertJsonPath('data.email', 'person@gmail.com');

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Managed user recovery email changed.', [
                'actor_user_id' => $admin->id,
                'target_user_id' => $target->id,
                'old_email_masked' => null,
                'new_email_masked' => 'p***@gmail.com',
            ]);

        $duplicate = User::factory()->create(['email' => 'taken@example.test']);
        $this->actingAs($admin, 'web')
            ->withHeaders($this->spaHeaders())
            ->patchJson("/api/users/{$target->id}", ['email' => 'TAKEN@EXAMPLE.TEST'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ];
    }
}
