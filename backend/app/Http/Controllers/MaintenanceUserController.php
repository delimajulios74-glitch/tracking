<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MaintenanceUserController extends Controller
{
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
}
