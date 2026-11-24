<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            Password::sendResetLink($request->validated());
        } catch (Throwable $exception) {
            Log::error('Password reset notification could not be queued.', [
                'exception_class' => $exception::class,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "If that email is registered, you'll receive a reset link shortly.",
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table(config('session.table'))
                    ->where('user_id', $user->getAuthIdentifier())
                    ->delete();

                DB::table('personal_access_tokens')
                    ->where('tokenable_type', $user->getMorphClass())
                    ->where('tokenable_id', $user->getAuthIdentifier())
                    ->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'This reset link is invalid or has expired.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset. You can now sign in.',
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt([
            ...$request->validated(),
            'is_active' => true,
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        $request->session()->regenerate();

        if ($request->user()->role === 'end_user') {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'login',
                'description' => 'End User signed in.',
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()),
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'You have been logged out.',
        ]);
    }
}
