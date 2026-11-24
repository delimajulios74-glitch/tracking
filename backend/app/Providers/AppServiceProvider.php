<?php

namespace App\Providers;

use App\Models\MaintenanceTask;
use App\Notifications\QueuedPasswordResetNotification;
use App\Policies\MaintenanceTaskPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(MaintenanceTask::class, MaintenanceTaskPolicy::class);

        ResetPassword::createUrlUsing(function ($user, string $token): string {
            return rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], '', '&', PHP_QUERY_RFC3986);
        });

        RateLimiter::for('forgot-password', function (Request $request): array {
            return [
                Limit::perMinute(5)->by('forgot-password:ip:'.$request->ip()),
                Limit::perMinute(5)->by(
                    'forgot-password:email:'.hash('sha256', Str::lower(trim((string) $request->input('email')))),
                ),
            ];
        });

        RateLimiter::for('reset-password', function (Request $request): array {
            return [
                Limit::perMinute(5)->by('reset-password:ip:'.$request->ip()),
                Limit::perMinute(5)->by(
                    'reset-password:email:'.hash('sha256', Str::lower(trim((string) $request->input('email')))),
                ),
            ];
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if (! $event->notification instanceof QueuedPasswordResetNotification) {
                return;
            }

            $exception = $event->data['exception'] ?? null;
            Log::error('Queued password reset email delivery failed.', [
                'user_id' => $event->notifiable->getKey(),
                'channel' => $event->channel,
                'exception_class' => $exception instanceof Throwable ? $exception::class : 'unknown',
            ]);
        });
    }
}
