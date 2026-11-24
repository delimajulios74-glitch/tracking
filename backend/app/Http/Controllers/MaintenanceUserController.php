<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexMaintenanceUserRequest;
use App\Http\Requests\StoreMaintenanceUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MaintenanceUserController extends Controller
{
    public function index(IndexMaintenanceUserRequest $request): JsonResponse
    {
        $users = User::query()
            ->when($request->validated('role'), fn ($query, string $role) => $query->where('role', $role))
            ->when($request->validated('role') === config('maintenance_tasks.roles.maintenance'), fn ($query) => $query->where(
                'is_active',
                true,
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users->getCollection())->resolve(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(StoreMaintenanceUserRequest $request): JsonResponse
    {
        $user = new User([
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
            'name' => $request->validated('name'),
            'password' => $request->validated('password'),
        ]);
        $user->role = $request->validated('role', config('roles.maintenance'));
        $user->save();

        Log::info('Managed user recovery email changed.', [
            'actor_user_id' => $request->user()->id,
            'target_user_id' => $user->id,
            'old_email_masked' => null,
            'new_email_masked' => $this->maskEmail($user->email),
        ]);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ], 201);
    }

    public function update(UpdateManagedUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $emailChange = null;

        DB::transaction(function () use ($request, $user, $validated, &$emailChange): void {
            $target = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $willBeActive = array_key_exists('is_active', $validated)
                ? filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN)
                : $target->is_active;
            $willBeAdmin = ($validated['role'] ?? $target->role) === 'admin';
            $willLoseAdminAccess = $target->role === 'admin'
                && (! $willBeAdmin || ! $willBeActive);

            if (
                $willLoseAdminAccess
                && User::query()
                    ->where('role', 'admin')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    'user' => 'The last active administrator cannot be demoted or deactivated.',
                ]);
            }

            if ($target->is($request->user()) && (! $willBeActive || ! $willBeAdmin)) {
                throw ValidationException::withMessages([
                    'user' => 'You cannot deactivate or demote your own account.',
                ]);
            }

            if (array_key_exists('email', $validated) && $target->email !== $validated['email']) {
                $emailChange = [
                    'old_email_masked' => $this->maskEmail($target->email),
                    'new_email_masked' => $this->maskEmail($validated['email']),
                ];
                $target->email = $validated['email'];
            }

            $target->fill(array_intersect_key($validated, array_flip(['name', 'username'])));
            if (array_key_exists('role', $validated)) {
                $target->role = $validated['role'];
            }
            if (array_key_exists('is_active', $validated)) {
                $target->is_active = $willBeActive;
            }
            $target->save();
            $user->setRawAttributes($target->getAttributes(), true);
        });

        if ($emailChange !== null) {
            Log::info('Managed user recovery email changed.', [
                'actor_user_id' => $request->user()->id,
                'target_user_id' => $user->id,
                ...$emailChange,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $target = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($target->is($request->user())) {
                throw ValidationException::withMessages([
                    'user' => 'You cannot delete your own account.',
                ]);
            }

            if (
                $target->role === 'admin'
                && $target->is_active
                && User::query()
                    ->where('role', 'admin')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    'user' => 'The last active administrator cannot be deleted.',
                ]);
            }

            $target->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'User account deleted.',
        ]);
    }

    private function maskEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        [$localPart, $domain] = explode('@', $email, 2);

        return mb_substr($localPart, 0, 1).'***@'.$domain;
    }
}
