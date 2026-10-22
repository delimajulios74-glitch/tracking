<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

class AssetLookupController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'conditions' => config('assets.conditions'),
                'statuses' => config('assets.statuses'),
            ],
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function locations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Location::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
