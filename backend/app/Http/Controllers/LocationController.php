<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::create($request->validated());
        $location->loadCount('assets');

        return response()->json([
            'success' => true,
            'data' => $location->only(['id', 'name', 'building', 'assets_count']),
        ], 201);
    }

    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        $location->update($request->validated());
        $location->loadCount('assets');

        return response()->json([
            'success' => true,
            'data' => $location->only(['id', 'name', 'building', 'assets_count']),
        ]);
    }
}
