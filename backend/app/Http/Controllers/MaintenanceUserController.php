<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

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
        $user = User::create([
            'username' => $request->validated('username'),
            'name' => $request->validated('name'),
            'password' => $request->validated('password'),
        ]);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ], 201);
    }

    public function update(UpdateManagedUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $user, $validated): void {
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

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }
}
